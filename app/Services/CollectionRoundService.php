<?php

namespace App\Services;

use App\Models\CollectionRound;
use App\Models\CollectionRoundLog;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\Circle;
use App\Models\User;
use App\Models\CollectionRoundItem;

class CollectionRoundService
{
    /**
     * تحويل نص الشهر (Y-m) إلى كائن Carbon في أول يوم من الشهر
     */
    private function parseMonth(string $periodMonth): Carbon
    {
        return Carbon::createFromFormat('Y-m', $periodMonth)->startOfMonth();
    }

    /**
     * جلب الاشتراكات المدفوعة غير المحصّلة لحلقة وشهر معينين، مجمّعة حسب المحصّل
     */
    public function getUncollectedBreakdown(int $circleId, string $periodMonth): Collection
    {
        $monthStart = $this->parseMonth($periodMonth);

        $subscriptions = Subscription::uncollected()
            ->where('circle_id', $circleId)
            ->whereDate('month', $monthStart)
            ->with(['collectedBy:id,name', 'student:id,name'])
            ->get();

        $grouped = $subscriptions->groupBy('collected_by')
            ->map(function ($items, $collectedBy) {
                $collector = $items->first()->collectedBy;

                return [
                    'id' => is_null($collectedBy) || $collectedBy === '' ? null : (int) $collectedBy,
                    'name' => $collector?->name ?? 'غير محدد',
                    'amount' => (float) $items->sum('amount'),
                    'subscription_ids' => $items->pluck('id')->toArray(),
                    'subscriptions' => $items->map(function ($sub) {
                        return [
                            'id' => $sub->id,
                            'student_name' => $sub->student?->name ?? 'غير محدد',
                            'amount' => (float) $sub->amount,
                        ];
                    })->toArray(),
                ];
            })
            ->values()
            ->sortByDesc('amount')
            ->values();

        return $grouped;
    }

    /**
     * تحديد رقم الالتحصيل التالية لحلقة وشهر معينين
     */
    public function getNextRoundNumber(int $circleId, string $periodMonth): int
    {
        $monthStart = $this->parseMonth($periodMonth);

        $lastRound = CollectionRound::where('circle_id', $circleId)
            ->whereDate('period_month', $monthStart)
            ->max('round_number');

        return $lastRound ? ($lastRound + 1) : 1;
    }

    /**
     * ملخص ا تحصيلات السابقة المؤكّدة لحلقة وشهر معينين
     */
    public function getPreviousRoundsSummary(int $circleId, string $periodMonth): Collection
    {
        $monthStart = $this->parseMonth($periodMonth);

        return CollectionRound::where('circle_id', $circleId)
            ->whereDate('period_month', $monthStart)
            ->orderBy('round_number', 'asc')
            ->get([
                'id',
                'round_number',
                'total_amount',
                'students_count',
                'status',
                'confirmed_at',
            ]);
    }

    /**
     * استبعاد الحلقات التي لديها بالفعل التحصيل معلّق لنفس الشهر
     */
    public function filterCirclesWithoutPendingRound(Collection $circles, string $periodMonth): Collection
    {
        try {
            $monthStart = $this->parseMonth($periodMonth);
        } catch (\Throwable $e) {
            return $circles;
        }

        $pendingCircleIds = CollectionRound::whereDate('period_month', $monthStart)
            ->where('status', 'pending')
            ->pluck('circle_id');

        return $circles->whereNotIn('id', $pendingCircleIds)->values();
    }

    /**
     * إنشاء وحفظ التحصيل جديدة مع عناصرها
     */
    public function storeRound(array $data, int $createdBy): CollectionRound
    {
        // 1. جلب كل المستحقات الجديدة لهذه الحلقة/الشهر
        try {
            $breakdown = $this->getUncollectedBreakdown($data['circle_id'], $data['period_month']);
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException('خطأ في جلب البيانات: ' . $e->getMessage());
        }

        // 2. التحقق من أن كل subscription_id المُرسل موجود ضمن breakdown الحالية
        $validSubscriptionIds = $breakdown->pluck('subscriptions')
            ->flatten(1)
            ->pluck('id')
            ->toArray();

        $selectedSubscriptionIds = array_map('intval', $data['selected_subscription_ids']);

        $validLookup = array_flip($validSubscriptionIds);
        $invalidIds = array_filter($selectedSubscriptionIds, fn($id) => !isset($validLookup[$id]));
        if (!empty($invalidIds)) {
            throw new \InvalidArgumentException('بعض الاشتراكات المحددة غير موجودة في المستحقات الحالية لهذه الحلقة والشهر.');
        }

        // 3. لا جولة معلّقة ثانية لنفس الحلقة والشهر (نفس قاعدة صفحة الإنشاء)
        $pendingExists = CollectionRound::where('circle_id', $data['circle_id'])
            ->whereDate('period_month', $this->parseMonth($data['period_month']))
            ->where('status', 'pending')
            ->exists();
        if ($pendingExists) {
            throw new \InvalidArgumentException('توجد جولة تحصيل معلّقة لهذه الحلقة في هذا الشهر؛ أكّدها أو احذفها أولًا.');
        }

        // 4. حماية من حالة فارغة
        if (empty($selectedSubscriptionIds)) {
            throw new \InvalidArgumentException('لا توجد اشتراكات محدَّدة لإنشاء التحصيل');
        }

        return DB::transaction(function () use ($data, $createdBy, $selectedSubscriptionIds) {
            // ✅ أ. استعلام واحد بدل 3: يجيب id + amount + collected_by لكل الاشتراكات المرشَّحة
            //    ويُستخدم فلترة is_collected=false في نفس اللحظة (حماية Race Condition)
            $stillUncollectedSubs = Subscription::whereIn('id', $selectedSubscriptionIds)
                ->where('is_collected', false)
                ->get(['id', 'amount', 'collected_by']);

            $subscriptionIds = $stillUncollectedSubs->pluck('id')->toArray();
            $studentsCount   = $stillUncollectedSubs->count();

            if ($studentsCount === 0) {
                throw new \InvalidArgumentException('لا توجد اشتراكات محدَّدة لإنشاء التحصيل');
            }

            // ✅ إعادة حساب المبلغ الإجمالي من نفس الـ Collection بدون استعلام إضافي
            $totalAmount = (float) $stillUncollectedSubs->sum('amount');

            // ب. تحديد رقم الالتحصيل التالية
            $roundNumber = $this->getNextRoundNumber($data['circle_id'], $data['period_month']);

            // ج. جلب center_id من الحلقة
            $centerId = Circle::findOrFail($data['circle_id'])->center_id;

            // استخدم created_by المُرسل لو موجود وصالح، وإلا استخدم المستخدم الحالي
            $finalCreatedBy = $data['created_by'] ?? $createdBy;

            // د. إنشاء سجل الالتحصيل
            $round = CollectionRound::create([
                'created_by'      => $finalCreatedBy,
                'confirmed_by'    => null,
                'level'           => 1,
                'center_id'       => $centerId,
                'circle_id'       => $data['circle_id'],
                'round_number'    => $roundNumber,
                'period_month'    => $this->parseMonth($data['period_month'])->toDateString(),
                'total_amount'    => $data['total_amount'],
                'students_count'  => $studentsCount,
                'status'          => 'pending',
                'supervisor_note' => $data['supervisor_note'] ?? null,
            ]);

            // ✅ هـ. بناء مصفوفة الإدخال مباشرة من $stillUncollectedSubs (بدون استعلام ثالث)
            $itemsToInsert = $stillUncollectedSubs->map(function ($sub) use ($round) {
                return [
                    'collection_round_id'   => $round->id,
                    'subscription_id'       => $sub->id,
                    'amount_at_collection'  => $sub->amount,
                    'collected_by_snapshot' => $sub->collected_by,
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ];
            })->toArray();

            // إدخال دفعي لعناصر الالتحصيل
            CollectionRoundItem::insert($itemsToInsert);

            // و. تحديث الاشتراكات كمحصّلة
            Subscription::whereIn('id', $subscriptionIds)->update(['is_collected' => true]);

            return $round->load('items');
        });
    }

    public function getEditableBreakdown(CollectionRound $round): Collection
    {
        // 1. المستحقات الجديدة (غير المُحصَّلة في أي التحصيل) — ظهرت بعد إنشاء الالتحصيل
        $newBreakdown = $this->getUncollectedBreakdown(
            $round->circle_id,
            $round->period_month->format('Y-m')
        );

        // 2. عناصر الالتحصيل الحالية (الأصلية وقت الإنشاء)
        $currentItems = $round->items()
            ->with(['subscription.student', 'collectedBySnapshot'])
            ->get()
            ->groupBy('collected_by_snapshot');

        $merged = collect();

        // أ. ابدأ بالعناصر الأصلية للالتحصيل
        foreach ($currentItems as $collectedBy => $items) {
            $id = is_null($collectedBy) || $collectedBy === '' ? null : (int) $collectedBy;

            $originalSubscriptions = $items->map(function ($item) {
                return [
                    'id' => $item->subscription_id,
                    'student_name' => $item->subscription?->student?->name ?? 'غير محدد',
                    'amount' => (float) $item->amount_at_collection,
                    'is_original' => true,
                ];
            })->toArray();

            $merged->put($id, [
                'id'                        => $id,
                'name'                      => $items->first()?->collectedBySnapshot?->name ?? 'غير محدد',
                'original_amount'           => (float) $items->sum('amount_at_collection'),
                'original_subscription_ids' => $items->pluck('subscription_id')->toArray(),
                'new_amount'                => 0.0,
                'new_subscription_ids'      => [],
                'subscriptions'             => $originalSubscriptions,
            ]);
        }

        // ب. أضف/ادمج الاشتراكات المستحقة الجديدة (لم تكن ضمن الالتحصيل وقت إنشائها)
        foreach ($newBreakdown as $item) {
            $id = $item['id'];

            if ($merged->has($id)) {
                $existing = $merged->get($id);
                $existing['new_amount'] += $item['amount'];
                $existing['new_subscription_ids'] = array_merge(
                    $existing['new_subscription_ids'],
                    $item['subscription_ids']
                );
                // إضافة الاشتراكات الجديدة إلى قائمة subscriptions مع وسمها
                foreach ($item['subscriptions'] as $sub) {
                    $sub['is_original'] = false;
                    $existing['subscriptions'][] = $sub;
                }
                $merged->put($id, $existing);
            } else {
                $newSubscriptions = collect($item['subscriptions'])->map(function ($sub) {
                    $sub['is_original'] = false;
                    return $sub;
                })->toArray();

                $merged->put($id, [
                    'id'                        => $id,
                    'name'                      => $item['name'],
                    'original_amount'           => 0.0,
                    'original_subscription_ids' => [],
                    'new_amount'                => $item['amount'],
                    'new_subscription_ids'      => $item['subscription_ids'],
                    'subscriptions'             => $newSubscriptions,
                ]);
            }
        }

        // ج. تجهيز الحقول النهائية + وضع علامة على وجود زيادة جديدة
        return $merged->values()
            ->map(function ($item) {
                $item['amount'] = $item['original_amount'] + $item['new_amount'];
                $item['subscription_ids'] = array_merge(
                    $item['original_subscription_ids'],
                    $item['new_subscription_ids']
                );
                $item['has_new_items'] = count($item['new_subscription_ids']) > 0;

                return $item;
            })
            ->sortByDesc('amount')
            ->values();
    }

    /**
     * تحديث التحصيل معلّق
     **/
    /**
     * تحديث التحصيل معلّق
     **/
    public function updateRound(CollectionRound $round, array $data, User $updatedByUser): CollectionRound
    {
        // تخزين الحالة الأصلية قبل التعديل (للتحقق لاحقًا مما إذا كانت مؤكَّد)
        $wasConfirmed = $round->status === 'confirmed';

        // السماح بالتعديل إذا كانت الالتحصيل pending، أو إذا كان القائم بالتعديل admin/general_manager (بغض النظر عن الحالة)
        $isPrivilegedOverride = $updatedByUser->hasAnyRole(['admin', 'general_manager']);

        if ($round->status !== 'pending' && !$isPrivilegedOverride) {
            throw new \InvalidArgumentException('لا يمكن تعديل تحصيل غير معلّق');
        }

        return DB::transaction(function () use ($round, $data, $updatedByUser, $wasConfirmed) {
            // 1. جلب breakdown الحالية للتحقق من صحة الـ subscription_ids المُرسلة
            $breakdown = $this->getEditableBreakdown($round);

            // 2. التحقق من أن كل subscription_id المُرسل موجود ضمن breakdown الحالية
            $validSubscriptionIds = $breakdown->pluck('subscriptions')
                ->flatten(1)
                ->pluck('id')
                ->toArray();

            $selectedSubscriptionIds = array_map('intval', $data['selected_subscription_ids']);

            $validLookup = array_flip($validSubscriptionIds);
            $invalidIds = array_filter($selectedSubscriptionIds, fn($id) => !isset($validLookup[$id]));
            if (!empty($invalidIds)) {
                throw new \InvalidArgumentException('بعض الاشتراكات المحددة غير موجودة في المستحقات الحالية لهذه الحلقة والشهر.');
            }

            if (empty($selectedSubscriptionIds)) {
                throw new \InvalidArgumentException('يجب اختيار اشتراك واحد على الأقل');
            }

            // ✅ استخراج الاشتراكات الحالية من $breakdown مباشرة بدل استعلام DB إضافي
            // (original_subscription_ids في كل مجموعة هي أصلاً عناصر الجولة الحالية)
            $currentSubscriptionIds = $breakdown
                ->pluck('original_subscription_ids')
                ->flatten()
                ->map(fn($id) => (int) $id)
                ->toArray();

            $currentLookup = array_flip($currentSubscriptionIds);
            $selectedLookup = array_flip($selectedSubscriptionIds);

            $toRemove = array_filter($currentSubscriptionIds, fn($id) => !isset($selectedLookup[$id]));
            $toAdd = array_filter($selectedSubscriptionIds, fn($id) => !isset($currentLookup[$id]));

            // حفظ القيمة القديمة للمبلغ الإجمالي قبل التحديث
            $oldTotalAmount = (float) $round->total_amount;

            // حفظ أسماء الطلاب قبل الحذف (للسجل التاريخي)
            $removedStudentNames = [];
            if (!empty($toRemove)) {
                $removedItems = CollectionRoundItem::where('collection_round_id', $round->id)
                    ->whereIn('subscription_id', $toRemove)
                    ->with('subscription.student')
                    ->get();

                $removedStudentNames = $removedItems
                    ->map(fn($item) => $item->subscription?->student?->name ?? 'غير محدد')
                    ->filter()
                    ->values()
                    ->toArray();

                Subscription::whereIn('id', $toRemove)->update(['is_collected' => false]);
                CollectionRoundItem::where('collection_round_id', $round->id)
                    ->whereIn('subscription_id', $toRemove)
                    ->delete();
            }

            // حفظ أسماء الطلاب المضافين (للسجل التاريخي)
            $addedStudentNames = [];
            if (!empty($toAdd)) {
                $availableSubscriptions = Subscription::whereIn('id', $toAdd)
                    ->where('is_collected', false)
                    ->with('student')
                    ->get();

                $addedStudentNames = $availableSubscriptions
                    ->map(fn($sub) => $sub->student?->name ?? 'غير محدد')
                    ->filter()
                    ->values()
                    ->toArray();

                // ✅ تحويل $toAdd لـ lookup set بحث O(1) بدل in_array() اللي كانت O(k)
                $toAddLookup = array_flip($toAdd);

                // بناء خريطة subscription_id => collector_id من الـ breakdown
                $subscriptionToCollector = [];
                foreach ($breakdown as $item) {
                    foreach ($item['subscriptions'] as $sub) {
                        if (isset($toAddLookup[$sub['id']])) {
                            $subscriptionToCollector[$sub['id']] = $item['id'];
                        }
                    }
                }

                $insertData = [];
                foreach ($availableSubscriptions as $sub) {
                    $insertData[] = [
                        'collection_round_id'   => $round->id,
                        'subscription_id'       => $sub->id,
                        'amount_at_collection'  => $sub->amount,
                        'collected_by_snapshot' => $subscriptionToCollector[$sub->id] ?? null,
                        'created_at'            => now(),
                        'updated_at'            => now(),
                    ];
                }

                if (!empty($insertData)) {
                    CollectionRoundItem::insert($insertData);
                    Subscription::whereIn('id', $availableSubscriptions->pluck('id'))
                        ->update(['is_collected' => true]);
                }
            }

            // إعادة حساب الإجماليات
            $finalItems = $round->items()->get();
            $totalAmount = $finalItems->sum('amount_at_collection');
            $studentsCount = $finalItems->count();

            // total_amount يُستخدَم من القيمة المُدخَلة يدويًا من المشرف، وليس المحسوبة تلقائيًا
            $updateData = [
                'total_amount'    => $data['total_amount'],
                'students_count'  => $studentsCount,
                'supervisor_note' => $data['supervisor_note'] ?? $round->supervisor_note,
            ];

            // تحديث created_by لو مُرسل من admin/general_manager
            if (isset($data['created_by'])) {
                $updateData['created_by'] = $data['created_by'];
            }

            if (!empty($round->manager_note)) {
                $updateData['manager_note_addressed'] = true;
            }

            // إذا كانت الالتحصيل مؤكَّد سابقًا وتم تعديلها من قبل admin، نعيدها لـ pending
            if ($wasConfirmed) {
                $updateData['status'] = 'pending';
                $updateData['confirmed_by'] = null;
                $updateData['confirmed_at'] = null;
            }

            $round->update($updateData);

            // ─── بناء السجل التاريخي ───
            $logParts = [];

            foreach ($removedStudentNames as $name) {
                $logParts[] = 'تمت إزالة اشتراك ' . $name;
            }

            foreach ($addedStudentNames as $name) {
                $logParts[] = 'تمت إضافة اشتراك ' . $name;
            }

            $newTotalAmount = (float) $data['total_amount'];
            if (abs($oldTotalAmount - $newTotalAmount) > 0.001) {
                $logParts[] = 'تغيّر المبلغ الإجمالي من ' . number_format($oldTotalAmount, 2) . ' إلى ' . number_format($newTotalAmount, 2) . ' جنيه';
            }

            if ($wasConfirmed) {
                $logParts[] = 'تم إرجاع الجولة من حالة مؤكَّد إلى معلّق تلقائيًا نتيجة هذا التعديل';
            }

            if (!empty($logParts)) {
                // ✅ استخدام نفس الموديل الممرر، بدون استعلام تاني
                $isAdmin = $updatedByUser->hasRole('admin');

                if ($isAdmin) {
                    $displayUserId = $round->created_by;
                } else {
                    $displayUserId = $updatedByUser->id;
                }

                CollectionRoundLog::create([
                    'collection_round_id' => $round->id,
                    'description'         => implode('، ', $logParts),
                    'created_by'          => $displayUserId,
                    'created_at'          => now(),
                ]);
            }

            return $round->fresh('items');
        });
    }

    /**
     * حذف التحصيل بالكامل (حصريًا للإدارة)
     */
    public function destroyRound(CollectionRound $collectionRound): void
    {
        DB::transaction(function () use ($collectionRound) {
            $subscriptionIds = $collectionRound->items()->pluck('subscription_id')->toArray();

            // تحرير الاشتراكات المرتبطة لتصبح متاحة لتحصيلات جديدة
            Subscription::whereIn('id', $subscriptionIds)->update(['is_collected' => false]);

            // حذف عناصر التحصيل ثم التحصيل نفسها
            $collectionRound->items()->delete();
            $collectionRound->delete();
        });
    }
}
