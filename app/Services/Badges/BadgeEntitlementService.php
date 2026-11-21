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
     * الاستحقاق يُحفظ كاملًا، ويُفتح عجز تلقائيًا عند نقص الرصيد.
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

            $teacherBalance = $this->balances->teacherBalance($entitlement->teacher_id, $entitlement->badge_type_id);
            $this->shortages->openIfUnderstocked($entitlement, $teacherBalance);

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
                    $available = $this->balances->teacherBalance($entitlement->teacher_id, $entitlement->badge_type_id);
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
}
