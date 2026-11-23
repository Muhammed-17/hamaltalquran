<?php

namespace App\Services\Badges;

use App\Enums\Badges\EntitlementStatus;
use App\Models\Badges\BadgeEntitlement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BadgeEntitlementService
{
    public function __construct(
        private BadgeBalanceService $balances,
        private BadgeShortageService $shortages,
        private BadgeLogService $logger,
        private BadgeDistributionService $distributions,
    ) {}

    /**
     * تسجيل استحقاق طالب. لا يُشترط توفر رصيد لدى المعلم:
     * الاستحقاق يُحفظ كاملًا، ويُفتح سجل متبقٍّ (نواقص) لأي جزء غير مسلّم
     * بغض النظر عن رصيد الموزّع.
     */
    public function create(array $data, User $actor): BadgeEntitlement
    {
        return DB::transaction(function () use ($data, $actor) {
            $entitlement = BadgeEntitlement::create([
                ...$data,
                'batch_id'   => $data['batch_id'] ?? (string) \Illuminate\Support\Str::uuid(),
                'status'     => EntitlementStatus::Pending->value,
                'created_by' => $actor->id,
            ]);

            $this->shortages->openForEntitlement($entitlement);

            $this->logger->log('entitlement.created', $entitlement, newValues: $entitlement->only(['student_id', 'circle_id', 'teacher_id', 'badge_type_id', 'quantity']), user: $actor);

            return $entitlement;
        });
    }

    /**
     * الصفحة الموحدة: استحقاقات لعدة أنواع دفعة واحدة لطالب واحد،
     * مع تسليم فوري اختياري: يُوزَّع من كل نوع المتاح من رصيد الموزّع،
     * والباقي يبقى استحقاقًا مفتوحًا بعجز — كل ذلك في معاملة واحدة.
     *
     * @param  array<string, mixed>  $baseData  student_id, circle_id, teacher_id, entitlement_date, notes
     * @param  array<int, int>  $quantitiesByType  badge_type_id => quantity
     * @return array{entitlements: array<int, BadgeEntitlement>, distributed: int}
     */
    public function createMultiple(array $baseData, array $quantitiesByType, bool $instant, User $actor): array
    {
        return DB::transaction(function () use ($baseData, $quantitiesByType, $instant, $actor) {
            $created = [];
            $distributedTotal = 0;
            // كل أنواع العملية الواحدة تشترك في معرّف دفعة واحد
            $batchId = (string) \Illuminate\Support\Str::uuid();

            foreach ($quantitiesByType as $typeId => $quantity) {
                $quantity = (int) $quantity;
                if ($quantity <= 0) {
                    continue;
                }

                $entitlement = $this->create([
                    ...$baseData,
                    'batch_id'      => $batchId,
                    'badge_type_id' => (int) $typeId,
                    'quantity'      => $quantity,
                ], $actor);

                if ($instant) {
                    $available = $this->balances->distributorBalance($entitlement->teacher_id, $entitlement->badge_type_id);
                    $toDistribute = min($quantity, $available);

                    if ($toDistribute > 0) {
                        $this->distributions->distribute(
                            $entitlement,
                            $toDistribute,
                            $baseData['entitlement_date'],
                            $actor,
                            $baseData['notes'] ?? null,
                        );
                        $distributedTotal += $toDistribute;
                    }
                }

                $created[] = $entitlement;
            }

            if ($created === []) {
                throw ValidationException::withMessages([
                    'quantities' => 'أدخل كمية أكبر من صفر لنوع واحد على الأقل.',
                ]);
            }

            return ['entitlements' => $created, 'distributed' => $distributedTotal];
        });
    }

    public function update(BadgeEntitlement $entitlement, array $data, User $actor): BadgeEntitlement
    {
        return DB::transaction(function () use ($entitlement, $data, $actor) {
            $entitlement = BadgeEntitlement::lockForUpdate()->findOrFail($entitlement->id);

            if ($entitlement->status === EntitlementStatus::Cancelled) {
                throw ValidationException::withMessages(['entitlement' => 'لا يمكن تعديل استحقاق ملغي.']);
            }

            if (isset($data['quantity']) && (int) $data['quantity'] < $entitlement->deliveredQuantity()) {
                throw ValidationException::withMessages(['quantity' => 'الكمية الجديدة أقل مما تم توزيعه بالفعل.']);
            }

            $old = $entitlement->only(['quantity', 'entitlement_date', 'notes']);
            $entitlement->update($data);
            $this->shortages->syncEntitlementState($entitlement);

            $this->logger->log('entitlement.updated', $entitlement, oldValues: $old, newValues: $entitlement->only(['quantity', 'entitlement_date', 'notes']), user: $actor);

            return $entitlement;
        });
    }

    public function cancel(BadgeEntitlement $entitlement, User $actor, ?string $reason = null): BadgeEntitlement
    {
        return DB::transaction(function () use ($entitlement, $actor, $reason) {
            $entitlement = BadgeEntitlement::lockForUpdate()->findOrFail($entitlement->id);

            if ($entitlement->status === EntitlementStatus::Cancelled) {
                throw ValidationException::withMessages(['entitlement' => 'هذا الاستحقاق ملغي بالفعل.']);
            }
            if ($entitlement->deliveredQuantity() > 0) {
                throw ValidationException::withMessages(['entitlement' => 'لا يمكن إلغاء استحقاق تم توزيع جزء منه. ألغِ التوزيعات أولًا.']);
            }

            $entitlement->update(['status' => EntitlementStatus::Cancelled->value]);
            $this->shortages->cancelForEntitlement($entitlement);

            $this->logger->log('entitlement.cancelled', $entitlement, reason: $reason, user: $actor);

            return $entitlement;
        });
    }

    /**
     * صفحة التسليم اليومي: لكل طالب/نوع تصل الكمية المستهدفة لإجمالي اليوم،
     * فتُطبَّق كفرق على استحقاقات اليوم القائمة عند نفس الموزّع والحلقة:
     * - زيادة: استحقاق جديد بالفرق (بدفعة واحدة لكل طالب في هذا الحفظ).
     * - نقصان لهدف موجب: تقليل استحقاقات اليوم من الأحدث، بحدّ أرضي = ما سُلّم
     *   فعليًا منها (ما زاد عن الحد يُبلَّغ ولا يُنفَّذ).
     * - هدف صفر في الحفظ الجماعي (إزالة العلامة): حذف فعلي — تُلغى توزيعات
     *   استحقاقات اليوم ثم تُلغى الاستحقاقات نفسها؛ والمحصّل في جولة معتمدة
     *   يُبلَّغ ولا يُحذف.
     * - مع التسليم الفوري: يُسلَّم من رصيد الموزّع ضد أقدم متبقّيات الطالب
     *   المفتوحة لهذا النوع عند هذا الموزّع (تشمل أيامًا سابقة) حتى النفاد.
     *
     * @param  array{date: string, circle_id: int|string, teacher_id: int|string}  $ctx
     * @param  array<int|string, array<int|string, mixed>>  $rows  student_id => [badge_type_id => targetQty]
     * @param  array<int>  $order  معرّفات الطلاب بترتيب تعديل المعلّم لصفوفهم: رصيد الموزّع
     *                              المشترك يُستهلك بهذا الترتيب (ترتيب خدمته الفعلية للطلاب)،
     *                              ومن ليس في القائمة يأتي بعدها بترتيب وصوله.
     * @return array{students: int, created: int, distributed: int, floored: array<int, array{student: string, type: string, quantity: int}>}
     */
    public function processDailySheet(array $ctx, array $rows, bool $instant, User $actor, array $order = [], array $deliverMap = []): array
    {
        return DB::transaction(function () use ($ctx, $rows, $instant, $actor, $order, $deliverMap) {
            $date = $ctx['date'];
            $circleId = (int) $ctx['circle_id'];
            $distributorId = (int) $ctx['teacher_id'];

            if ($order !== []) {
                $rank = array_flip(array_map('intval', $order));
                $keys = array_keys($rows);
                usort($keys, fn ($a, $b) => ($rank[(int) $a] ?? PHP_INT_MAX) <=> ($rank[(int) $b] ?? PHP_INT_MAX));
                $sorted = [];
                foreach ($keys as $key) {
                    $sorted[$key] = $rows[$key];
                }
                $rows = $sorted;
            }

            $created = 0;
            $distributed = 0;
            $touchedStudents = 0;
            $floored = [];

            foreach ($rows as $studentId => $typeQtys) {
                $studentId = (int) $studentId;
                // كل أنواع الطالب في هذا الحفظ تشترك في معرّف دفعة واحد
                $batchId = (string) \Illuminate\Support\Str::uuid();
                $studentTouched = false;

                foreach ((array) $typeQtys as $typeId => $target) {
                    $typeId = (int) $typeId;
                    $target = (int) $target;

                    $todayEnts = BadgeEntitlement::query()
                        ->where('student_id', $studentId)
                        ->where('circle_id', $circleId)
                        ->where('teacher_id', $distributorId)
                        ->where('badge_type_id', $typeId)
                        ->whereDate('entitlement_date', $date)
                        ->where('status', '!=', EntitlementStatus::Cancelled->value)
                        ->orderBy('id')
                        ->get();

                    $delta = $target - (int) $todayEnts->sum('quantity');

                    // خانة «تسليم فوري» لهذه الخلية (أو العلم العام): التسليم قرار لكل وسام
                    $deliverSubmitted = isset($deliverMap[$studentId][$typeId]);
                    $deliverWanted = $deliverSubmitted && (int) $deliverMap[$studentId][$typeId] === 1;
                    $cellInstant = $instant || $deliverWanted;

                    // إزالة علامة «تسليم فوري» عن وسام سُلّم اليوم بالكامل (والاستحقاق
                    // باقٍ): يُلغى تسليم اليوم ويرجع الوسام لرصيد الموزّع؛ ما دخل
                    // جولة تحصيل معتمدة يُبلَّغ ولا يُلغى
                    $deliveredToday = (int) $todayEnts->sum(fn ($e) => $e->deliveredQuantity());
                    if ($deliverSubmitted && !$deliverWanted && $target > 0
                        && (int) $todayEnts->sum('quantity') > 0 && $deliveredToday >= (int) $todayEnts->sum('quantity')) {
                        foreach ($todayEnts as $ent) {
                            foreach ($ent->activeDistributions()->orderByDesc('id')->get() as $dist) {
                                if ($dist->collectedQuantity() > 0) {
                                    $floored[] = [
                                        'student'  => \App\Models\Student::withoutGlobalScopes()->find($studentId)?->name ?? "#{$studentId}",
                                        'type'     => \App\Models\Badges\BadgeType::find($typeId)?->name ?? "#{$typeId}",
                                        'quantity' => (int) $dist->quantity,
                                    ];
                                    continue;
                                }
                                $this->distributions->cancel($dist, $actor, 'إلغاء تسليم من صفحة التسليم اليومي');
                                $studentTouched = true;
                            }
                        }
                    }

                    // التسليم الفوري ضد أقدم المتبقّيات المفتوحة عند هذا الموزّع
                    $settle = function () use ($studentId, $circleId, $distributorId, $typeId, $date, $actor, &$distributed, &$studentTouched) {
                        $open = BadgeEntitlement::query()
                            ->where('student_id', $studentId)
                            ->where('circle_id', $circleId)
                            ->where('teacher_id', $distributorId)
                            ->where('badge_type_id', $typeId)
                            ->whereIn('status', [EntitlementStatus::Pending->value, EntitlementStatus::Partial->value])
                            ->orderBy('id')
                            ->get();

                        foreach ($open as $ent) {
                            $remaining = $ent->remainingQuantity();
                            if ($remaining <= 0) {
                                continue;
                            }
                            $available = $this->balances->distributorBalance($distributorId, $typeId);
                            if ($available <= 0) {
                                break;
                            }
                            $give = min($remaining, $available);
                            $this->distributions->distribute($ent, $give, $date, $actor);
                            $distributed += $give;
                            $studentTouched = true;
                        }
                    };

                    if ($delta > 0) {
                        // ترتيب مقصود: تُسوّى الديون الأقدم من الرصيد أولًا، ثم يُسجَّل
                        // استحقاق اليوم — فيُفتح عجزه على الرصيد الحقيقي المتبقي
                        if ($cellInstant) {
                            $settle();
                        }
                        $this->create([
                            'student_id'       => $studentId,
                            'circle_id'        => $circleId,
                            'teacher_id'       => $distributorId,
                            'badge_type_id'    => $typeId,
                            'quantity'         => $delta,
                            'entitlement_date' => $date,
                            'batch_id'         => $batchId,
                        ], $actor);
                        $created++;
                        $studentTouched = true;
                        if ($cellInstant) {
                            $settle();
                        }
                    } elseif ($cellInstant && $delta === 0) {
                        // خانة «تسليم فوري»: الاستحقاق مسجّل من قبل، والتسليم وحده هو المطلوب الآن
                        $settle();
                    } elseif ($delta < 0 && $target === 0 && !$cellInstant) {
                        // إزالة العلامة في الحفظ الجماعي = حذف فعلي لاستحقاقات اليوم من
                        // هذا النوع: تُلغى توزيعاتها أولًا (تعود الأوسمة لرصيد الموزّع)
                        // ثم تُلغى هي. الاستحقاق الذي دخل جزء منه جولة تحصيل معتمدة
                        // لا يُحذف — يُبلَّغ عنه ويبقى كما هو (حذفًا كليًا أو لا شيء).
                        foreach ($todayEnts->sortByDesc('id') as $ent) {
                            $blocked = $ent->activeDistributions()->get()
                                ->contains(fn ($d) => $d->collectedQuantity() > 0);
                            if ($blocked) {
                                $floored[] = [
                                    'student'  => \App\Models\Student::withoutGlobalScopes()->find($studentId)?->name ?? "#{$studentId}",
                                    'type'     => \App\Models\Badges\BadgeType::find($typeId)?->name ?? "#{$typeId}",
                                    'quantity' => (int) $ent->quantity,
                                ];
                                continue;
                            }
                            foreach ($ent->activeDistributions()->orderByDesc('id')->get() as $dist) {
                                $this->distributions->cancel($dist, $actor, 'حذف من صفحة التسليم اليومي');
                            }
                            $this->cancel($ent, $actor, 'حذف من صفحة التسليم اليومي');
                            $studentTouched = true;
                        }
                    } elseif ($delta < 0) {
                        $cut = -$delta;
                        foreach ($todayEnts->sortByDesc('id') as $ent) {
                            if ($cut <= 0) {
                                break;
                            }
                            $reducible = (int) $ent->quantity - $ent->deliveredQuantity();
                            if ($reducible <= 0) {
                                continue;
                            }
                            $take = min($reducible, $cut);
                            if ($take >= (int) $ent->quantity) {
                                $this->cancel($ent, $actor, 'إلغاء من صفحة التسليم اليومي');
                            } else {
                                $this->update($ent, ['quantity' => (int) $ent->quantity - $take], $actor);
                            }
                            $cut -= $take;
                            $studentTouched = true;
                        }
                        if ($cut > 0) {
                            $floored[] = [
                                'student'  => \App\Models\Student::withoutGlobalScopes()->find($studentId)?->name ?? "#{$studentId}",
                                'type'     => \App\Models\Badges\BadgeType::find($typeId)?->name ?? "#{$typeId}",
                                'quantity' => $cut,
                            ];
                        }
                    }
                }

                if ($studentTouched) {
                    $touchedStudents++;
                }
            }

            return [
                'students'    => $touchedStudents,
                'created'     => $created,
                'distributed' => $distributed,
                'floored'     => $floored,
            ];
        });
    }
}
