<?php

namespace App\Services\Badges;

use App\Enums\Badges\CollectionItemStatus;
use App\Enums\Badges\CollectionRoundStatus;
use App\Models\Badges\BadgeCollectionItem;
use App\Models\Badges\BadgeCollectionRound;
use App\Models\Badges\BadgeDistribution;
use App\Models\Circle;
use App\Models\Scopes\CenterScope;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BadgeCollectionService
{
    public function __construct(private BadgeLogService $logger) {}

    /** الكميات المحجوزة في جولات أخرى (معتمدة + مسودات قائمة) لكل توزيع */
    private function reservedByDistribution(): \Illuminate\Support\Collection
    {
        return BadgeCollectionItem::whereNotNull('distribution_id')
            ->whereHas('round', fn ($q) => $q->whereIn('status', ['completed', 'draft']))
            ->selectRaw('distribution_id, SUM(collected_quantity) as total')
            ->groupBy('distribution_id')
            ->pluck('total', 'distribution_id');
    }

    /**
     * التوزيعات القابلة للتحصيل في حلقة مع المحصّل سابقًا والمتبقّي من كل منها —
     * نفس منطق إنشاء الجولة تمامًا، وتُستخدم أيضًا في تفصيل صفحة الإنشاء.
     * كل عنصر: ['distribution' => BadgeDistribution, 'already_collected' => int, 'remaining' => int]
     */
    public function collectableForCircle(Circle $circle): \Illuminate\Support\Collection
    {
        $reserved = $this->reservedByDistribution();

        return BadgeDistribution::active()
            // التحصيل إداري عابر للفروع والمراكز: يُتجاوز نطاق المركز هنا فقط
            ->whereIn('student_id', $circle->students()->withoutGlobalScope(CenterScope::class)->pluck('id'))
            ->whereHas('entitlement', fn ($q) => $q->where('circle_id', $circle->id))
            ->with(['student', 'badgeType', 'teacher'])
            ->orderBy('student_id')
            ->get()
            ->map(fn ($d) => [
                'distribution'      => $d,
                'already_collected' => (int) ($reserved[$d->id] ?? 0),
                'remaining'         => $d->quantity - (int) ($reserved[$d->id] ?? 0),
            ])
            ->filter(fn ($row) => $row['remaining'] > 0)
            ->values();
    }

    /**
     * تفصيل صفحة إنشاء الجولة: كل طلاب الحلقة مرتبين بالاسم، ولكل طالب
     * توزيعاته القابلة للتحصيل وإجمالي متبقّيها (طالب بلا توزيعات = قائمة
     * فارغة)، و«types»: صف واحد لكل نوع بإجمالياته — المستحق والموزّع
     * والقابل للتحصيل من المسلّم وصافي المستحق القابل للخصم بالاستبدال.
     */
    public function breakdownForCircle(Circle $circle): \Illuminate\Support\Collection
    {
        $collectable = $this->collectableForCircle($circle);
        $byStudent = $collectable->groupBy(fn ($row) => $row['distribution']->student_id);

        $students = $circle->students()->withoutGlobalScope(CenterScope::class)->orderBy('name')->get();

        // إجماليات الأنواع من استحقاقات الحلقة (المستهلك بالاستبدال يُصفّى)
        $entitlements = \App\Models\Badges\BadgeEntitlement::where('circle_id', $circle->id)
            ->where('status', '!=', 'cancelled')
            ->whereIn('student_id', $students->pluck('id'))
            ->with(['activeDistributions', 'badgeType'])
            ->get();
        $redeemedShortage = \App\Models\Badges\BadgeRedemptionItem::where('shortage_quantity', '>', 0)
            ->whereHas('redemption', fn ($q) => $q->where('status', 'completed'))
            ->selectRaw('entitlement_id, SUM(shortage_quantity) as total')
            ->groupBy('entitlement_id')
            ->pluck('total', 'entitlement_id');
        $collectableByType = $collectable->groupBy(fn ($row) => $row['distribution']->student_id . ':' . $row['distribution']->badge_type_id);

        $typesByStudent = [];
        foreach ($entitlements as $e) {
            $delivered = (int) $e->activeDistributions->sum('quantity');
            $key = $e->student_id . ':' . $e->badge_type_id;
            $typesByStudent[$e->student_id][$e->badge_type_id] ??= [
                'type'        => $e->badgeType,
                'type_id'     => $e->badge_type_id,
                'entitled'    => 0,
                'delivered'   => 0,
                'collectable' => (int) ($collectableByType->get($key, collect())->sum('remaining')),
                'owed'        => 0,
            ];
            $typesByStudent[$e->student_id][$e->badge_type_id]['entitled'] += (int) $e->quantity;
            $typesByStudent[$e->student_id][$e->badge_type_id]['delivered'] += $delivered;
            $typesByStudent[$e->student_id][$e->badge_type_id]['owed'] += max(
                0,
                (int) $e->quantity - $delivered - (int) ($redeemedShortage[$e->id] ?? 0)
            );
        }

        return $students->map(function ($student) use ($byStudent, $typesByStudent) {
            $items = $byStudent->get($student->id, collect());
            $types = collect($typesByStudent[$student->id] ?? [])
                ->filter(fn ($t) => $t['collectable'] > 0 || $t['owed'] > 0)
                ->sortBy(fn ($t) => $t['type']?->name)
                ->values();

            return [
                'student'         => $student,
                'items'           => $items,
                'types'           => $types,
                'total_remaining' => (int) $items->sum('remaining'),
                'total_owed'      => (int) $types->sum('owed'),
            ];
        });
    }

    /** جولة الجلسة المسودة المطابقة للسياق (حلقة + مسؤول + تاريخ) إن وُجدت */
    public function sessionRound(Circle $circle, int $teacherUserId, string $date): ?BadgeCollectionRound
    {
        return BadgeCollectionRound::where('circle_id', $circle->id)
            ->where('teacher_id', $teacherUserId)
            ->whereDate('collection_date', $date)
            ->where('status', CollectionRoundStatus::Draft->value)
            ->latest('id')
            ->first();
    }

    /** جولة تحصيل معتمدة لهذه الحلقة في شهر التاريخ المعطى (إن وُجدت) */
    public function completedRoundInMonth(Circle $circle, string $date, ?int $excludeRoundId = null): ?BadgeCollectionRound
    {
        $start = \Carbon\Carbon::parse($date)->startOfMonth();

        return BadgeCollectionRound::with('teacher')
            ->where('circle_id', $circle->id)
            ->where('status', CollectionRoundStatus::Completed->value)
            ->whereBetween('collection_date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])
            ->when($excludeRoundId, fn ($q) => $q->where('id', '!=', $excludeRoundId))
            ->latest('id')
            ->first();
    }

    /** رسالة منع تحصيل الحلقة مرتين في الشهر الواحد */
    public function monthBlockMessage(BadgeCollectionRound $done): string
    {
        $date = \Carbon\Carbon::parse($done->collection_date)->toDateString();
        $by = $done->teacher?->name ?? '—';

        return "تم تحصيل هذه الحلقة بالفعل في هذا الشهر بتاريخ {$date} بواسطة {$by} — لا يمكن تحصيل الحلقة أكثر من مرة في الشهر الواحد.";
    }

    /** جولة الجلسة: الموجودة أو جولة مسودة فارغة جديدة تُبنى طالبًا طالبًا */
    public function findOrCreateSessionRound(Circle $circle, int $teacherUserId, string $date, User $actor): BadgeCollectionRound
    {
        $existing = $this->sessionRound($circle, $teacherUserId, $date);
        if ($existing) {
            return $existing;
        }

        // قاعدة الشهر: لا تُحصَّل الحلقة أكثر من مرة في الشهر الواحد
        if ($done = $this->completedRoundInMonth($circle, $date)) {
            throw ValidationException::withMessages(['collection_date' => $this->monthBlockMessage($done)]);
        }

        return BadgeCollectionRound::create([
            'circle_id'       => $circle->id,
            'teacher_id'      => $teacherUserId,
            'collection_date' => $date,
            'status'          => CollectionRoundStatus::Draft->value,
            'notes'           => null,
            'created_by'      => $actor->id,
        ]);
    }

    /**
     * تسوية طالب داخل جولة الجلسة — تُحسم نتيجته نهائيًا من لحظة الحفظ:
     * لكل نوع (سليم يُحصَّل + تالف/مفقود + خصم من المستحق)، وتُوزَّع الكميات
     * على توزيعاته من الأقدم. إعادة التسوية في نفس الجولة تستبدل بياناته
     * القديمة بالكامل (تُحذف بنوده ويُعكس خصمه ثم يُبنى من جديد).
     * $rows: [badge_type_id => ['good' => int, 'damaged' => int, 'owed' => int]]
     */
    public function settleStudent(BadgeCollectionRound $round, int $studentId, array $rows, User $actor): void
    {
        DB::transaction(function () use ($round, $studentId, $rows, $actor) {
            $locked = BadgeCollectionRound::lockForUpdate()->findOrFail($round->id);
            if (!$locked->isDraft()) {
                throw ValidationException::withMessages(['round' => 'لا يمكن تسوية طلاب في جولة غير مسودة.']);
            }

            $this->releaseStudent($locked, $studentId, $actor);

            $circle = Circle::findOrFail($locked->circle_id);
            $group = $this->breakdownForCircle($circle)->first(fn ($g) => $g['student']->id === $studentId);
            if (!$group) {
                throw ValidationException::withMessages(['student_id' => 'الطالب ليس من طلاب هذه الحلقة.']);
            }

            $types = collect($group['types'])->keyBy('type_id');
            foreach (array_keys($rows) as $typeId) {
                if (!$types->has((int) $typeId)) {
                    throw ValidationException::withMessages(['amounts' => 'نوع وسام غير متاح لهذا الطالب في هذه الحلقة.']);
                }
            }

            $owedByType = [];
            foreach ($types as $typeId => $t) {
                $good = (int) ($rows[$typeId]['good'] ?? 0);
                $damaged = (int) ($rows[$typeId]['damaged'] ?? 0);
                $owed = (int) ($rows[$typeId]['owed'] ?? 0);
                $typeName = $t['type']?->name;

                if ($good + $damaged > (int) $t['collectable']) {
                    throw ValidationException::withMessages(['amounts' => "مجموع السليم والتالف/المفقود لنوع «{$typeName}» يتجاوز ما في يد الطالب ({$t['collectable']})."]);
                }
                if ($owed > (int) $t['owed']) {
                    throw ValidationException::withMessages(['amounts' => "المخصوم من المستحق لنوع «{$typeName}» يتجاوز إجمالي المستحق ({$t['owed']})."]);
                }
                if ($owed > 0) {
                    $owedByType[$typeId] = $owed;
                }

                // التوزيع على بنود الطالب لهذا النوع من الأقدم: سليم ثم تالف/مفقود
                $goodLeft = $good;
                $dmgLeft = $damaged;
                $studentRows = collect($group['items'])
                    ->filter(fn ($row) => (int) $row['distribution']->badge_type_id === (int) $typeId)
                    ->sortBy(fn ($row) => $row['distribution']->id)
                    ->values();
                foreach ($studentRows as $row) {
                    $remaining = (int) $row['remaining'];
                    $g = min($remaining, $goodLeft);
                    $goodLeft -= $g;
                    $d = min($remaining - $g, $dmgLeft);
                    $dmgLeft -= $d;
                    $collected = $g + $d;

                    BadgeCollectionItem::create([
                        'round_id'           => $locked->id,
                        'distribution_id'    => $row['distribution']->id,
                        'student_id'         => $studentId,
                        'badge_type_id'      => (int) $typeId,
                        'distributed_quantity' => $remaining,
                        'collected_quantity' => $collected,
                        'status'             => $collected === 0
                            ? CollectionItemStatus::NotCollected->value
                            : ($d > 0 ? CollectionItemStatus::Damaged->value : CollectionItemStatus::fromQuantities($remaining, $collected)->value),
                    ]);
                }
            }

            if ($owedByType !== []) {
                app(BadgeRedemptionService::class)->consumeOwedForRound($locked, $studentId, $owedByType, $actor);
            }

            $this->logger->log('collection.student_settled', $locked, user: $actor);
        });
    }

    /** تحرير طالب من جولة مسودة: حذف بنوده وعكس خصم مستحقه داخلها */
    public function releaseStudent(BadgeCollectionRound $round, int $studentId, User $actor): void
    {
        $round->items()->where('student_id', $studentId)->delete();
        app(BadgeRedemptionService::class)->cancelForStudent($round, $studentId, $actor);
    }

    /**
     * الطلاب المُسوَّون داخل جولة: خريطة student_id => صفوف أنواعه المحفوظة
     * (الموزّع عند الحفظ، السليم، التالف/المفقود، المخصوم من المستحق)
     */
    public function settledStudents(BadgeCollectionRound $round): \Illuminate\Support\Collection
    {
        $deducted = \App\Models\Badges\BadgeRedemptionItem::query()
            ->where('shortage_quantity', '>', 0)
            ->whereHas('redemption', fn ($q) => $q
                ->where('collection_round_id', $round->id)
                ->where('status', 'completed'))
            ->get()
            ->groupBy(fn ($i) => $i->redemption->student_id . ':' . $i->badge_type_id)
            ->map(fn ($items) => (int) $items->sum('shortage_quantity'));

        $roundItems = $round->items()
            ->whereNotNull('badge_type_id')
            ->with('badgeType')
            ->get();

        // إجماليات الاستحقاق والتوزيع والمستحق المتاح للخصم (باستثناء خصم هذه
        // الجولة نفسه) — لعرض الطالب المُسوّى وإعادة تسويته بنفس الأرقام
        $redeemedOther = \App\Models\Badges\BadgeRedemptionItem::where('shortage_quantity', '>', 0)
            ->whereHas('redemption', fn ($q) => $q->where('status', 'completed')
                ->where(fn ($w) => $w->whereNull('collection_round_id')->orWhere('collection_round_id', '!=', $round->id)))
            ->selectRaw('entitlement_id, SUM(shortage_quantity) as total')
            ->groupBy('entitlement_id')
            ->pluck('total', 'entitlement_id');
        $entitlementTotals = \App\Models\Badges\BadgeEntitlement::where('circle_id', $round->circle_id)
            ->where('status', '!=', 'cancelled')
            ->whereIn('student_id', $roundItems->pluck('student_id')->unique())
            ->with('activeDistributions')
            ->get()
            ->groupBy(fn ($e) => $e->student_id . ':' . $e->badge_type_id)
            ->map(fn ($es) => [
                'entitled' => (int) $es->sum('quantity'),
                'delivered' => (int) $es->sum(fn ($e) => $e->activeDistributions->sum('quantity')),
                'owed_available' => (int) $es->sum(fn ($e) => max(0, (int) $e->quantity - (int) $e->activeDistributions->sum('quantity') - (int) ($redeemedOther[$e->id] ?? 0))),
            ]);

        return $roundItems
            ->groupBy('student_id')
            ->map(fn ($items, $studentId) => [
                'types' => $items->groupBy('badge_type_id')->map(fn ($typeItems, $typeId) => [
                    'type' => $typeItems->first()->badgeType,
                    'distributed' => (int) $typeItems->sum('distributed_quantity'),
                    'good' => (int) $typeItems->sum('collected_quantity') - (int) $typeItems->where('status', CollectionItemStatus::Damaged->value)->sum('collected_quantity'),
                    'damaged' => (int) $typeItems->where('status', CollectionItemStatus::Damaged->value)->sum('collected_quantity'),
                    'owed_deducted' => (int) ($deducted[$studentId . ':' . $typeId] ?? 0),
                    'entitled' => (int) ($entitlementTotals[$studentId . ':' . $typeId]['entitled'] ?? 0),
                    'delivered' => (int) ($entitlementTotals[$studentId . ':' . $typeId]['delivered'] ?? 0),
                    'owed_available' => (int) ($entitlementTotals[$studentId . ':' . $typeId]['owed_available'] ?? 0),
                ])->values(),
            ]);
    }

    /**
     * إنشاء جولة تحصيل (مسودة) لحلقة:
     * تُدرج كل التوزيعات النشطة غير المحصلة بالكامل لطلاب الحلقة،
     * وصفًا صفريًا لكل طالب لا أوسمة لديه حتى تكون الجولة شاملة الجميع.
     * عند تمرير $selectedDistributionIds (من صفحة الإنشاء) تُدرج المحددة فقط،
     * وبدونها (null) تُدرج كل القابلة للتحصيل كما كان دائمًا.
     */
    public function createRound(Circle $circle, int $teacherUserId, string $date, User $actor, ?string $notes = null, ?array $selectedDistributionIds = null, ?array $distributionTakes = null): BadgeCollectionRound
    {
        return DB::transaction(function () use ($circle, $teacherUserId, $date, $actor, $notes, $selectedDistributionIds, $distributionTakes) {
            $round = BadgeCollectionRound::create([
                'circle_id'       => $circle->id,
                'teacher_id'      => $teacherUserId,
                'collection_date' => $date,
                'status'          => CollectionRoundStatus::Draft->value,
                'notes'           => $notes,
                'created_by'      => $actor->id,
            ]);

            $students = $circle->students()->withoutGlobalScope(CenterScope::class)->orderBy('name')->get();

            $collectable = $this->collectableForCircle($circle);
            if ($selectedDistributionIds !== null) {
                // اختيار فارغ بلا كميات = خطأ (سلوك REPORT 109)؛ أما مع تمرير
                // الكميات (حتى فارغة) فالجولة قد تكون خصم مستحق فقط بلا بنود
                if ($collectable->isNotEmpty() && count($selectedDistributionIds) === 0 && $distributionTakes === null) {
                    throw ValidationException::withMessages([
                        'selected_distribution_ids' => 'اختر توزيعًا واحدًا على الأقل لإنشاء الجولة.',
                    ]);
                }
                $selected = array_map('intval', $selectedDistributionIds);
                $collectable = $collectable
                    ->filter(fn ($row) => in_array($row['distribution']->id, $selected, true))
                    ->values();
            }

            $studentsWithItems = [];
            foreach ($collectable as $row) {
                $distribution = $row['distribution'];

                BadgeCollectionItem::create([
                    'round_id'             => $round->id,
                    'student_id'           => $distribution->student_id,
                    'distribution_id'      => $distribution->id,
                    'badge_type_id'        => $distribution->badge_type_id,
                    'distributed_quantity' => $row['remaining'],
                    // كمية التحصيل المختارة من صفحة الإنشاء تُعبَّأ مسبقًا
                    'collected_quantity'   => $distributionTakes !== null
                        ? min((int) ($distributionTakes[$distribution->id] ?? 0), (int) $row['remaining'])
                        : 0,
                    'status'               => CollectionItemStatus::NotCollected->value,
                ]);
                $studentsWithItems[$distribution->student_id] = true;
            }

            // طلاب بلا أوسمة للتحصيل: صف صفري لشمول سجل الجولة
            foreach ($students as $student) {
                if (!isset($studentsWithItems[$student->id])) {
                    BadgeCollectionItem::create([
                        'round_id'             => $round->id,
                        'student_id'           => $student->id,
                        'distribution_id'      => null,
                        'badge_type_id'        => null,
                        'distributed_quantity' => 0,
                        'collected_quantity'   => 0,
                        'status'               => CollectionItemStatus::NotCollected->value,
                    ]);
                }
            }

            $this->logger->log('collection.created', $round, newValues: $round->only(['circle_id', 'teacher_id', 'collection_date']), user: $actor);

            return $round;
        });
    }

    /** تحديث بند تحصيل داخل مسودة: لا تحصيل فوق الموزع، والفقدان بكمية محصلة صفر */
    public function updateItem(BadgeCollectionItem $item, int $collected, ?string $status, ?string $notes, User $actor): BadgeCollectionItem
    {
        return DB::transaction(function () use ($item, $collected, $status, $notes, $actor) {
            $item = BadgeCollectionItem::lockForUpdate()->findOrFail($item->id);
            $round = BadgeCollectionRound::lockForUpdate()->findOrFail($item->round_id);

            if (!$round->isDraft()) {
                throw ValidationException::withMessages(['round' => 'لا يمكن تعديل بنود جولة غير مسودة.']);
            }
            if ($collected < 0 || $collected > $item->distributed_quantity) {
                throw ValidationException::withMessages(['collected_quantity' => 'الكمية المحصلة لا يمكن أن تتجاوز الكمية الموزعة للطالب.']);
            }

            // «مفقود» و«تالف» حالتان يدويتان لا تُستنتجان من الكميات:
            // المفقود لم يرجع أصلًا (محصّل = صفر)، والتالف رجع بعدد يُسجَّل
            // في collected_quantity لكنه لا يعود لعهدة المعلم الصالحة
            $resolvedStatus = match ($status) {
                CollectionItemStatus::Missing->value => CollectionItemStatus::Missing,
                CollectionItemStatus::Damaged->value => CollectionItemStatus::Damaged,
                default => CollectionItemStatus::fromQuantities($item->distributed_quantity, $collected),
            };

            if ($resolvedStatus === CollectionItemStatus::Missing && $collected > 0) {
                throw ValidationException::withMessages(['collected_quantity' => 'الوسام المفقود لا يمكن أن تكون له كمية محصلة.']);
            }
            if ($resolvedStatus === CollectionItemStatus::Damaged && $collected === 0) {
                throw ValidationException::withMessages(['collected_quantity' => 'الوسام التالف رجع فعليًا: سجّل عدد القطع التالفة المرجعة.']);
            }

            $item->update([
                'collected_quantity' => $collected,
                'status'             => $resolvedStatus->value,
                'notes'              => $notes,
            ]);

            return $item;
        });
    }

    /** اعتماد الجولة: من هنا فقط تدخل الكميات المحصلة في الأرصدة */
    public function complete(BadgeCollectionRound $round, User $actor): BadgeCollectionRound
    {
        return DB::transaction(function () use ($round, $actor) {
            $round = BadgeCollectionRound::lockForUpdate()->findOrFail($round->id);

            if (!$round->isDraft()) {
                throw ValidationException::withMessages(['round' => 'يمكن اعتماد الجولات المسودة فقط.']);
            }

            // قاعدة الشهر: اعتماد ثانٍ لنفس الحلقة في نفس الشهر ممنوع ولو بدأت الجلسة قبله
            if ($done = $this->completedRoundInMonth($round->circle, $round->collection_date->toDateString(), $round->id)) {
                throw ValidationException::withMessages(['round' => $this->monthBlockMessage($done)]);
            }

            $round->update(['status' => CollectionRoundStatus::Completed->value]);

            $this->logger->log('collection.completed', $round, user: $actor);

            return $round;
        });
    }

    public function cancel(BadgeCollectionRound $round, User $actor, ?string $reason = null): BadgeCollectionRound
    {
        return DB::transaction(function () use ($round, $actor, $reason) {
            $round = BadgeCollectionRound::lockForUpdate()->findOrFail($round->id);

            if (!$round->isDraft()) {
                throw ValidationException::withMessages(['round' => 'يمكن إلغاء الجولات المسودة فقط؛ الجولات المعتمدة نهائية.']);
            }

            $round->update(['status' => CollectionRoundStatus::Cancelled->value]);

            // عكس خصومات المستحق المنفذة ضمن هذه الجولة (تعود ديونًا مفتوحة)
            app(BadgeRedemptionService::class)->cancelForRound($round, $actor);

            $this->logger->log('collection.cancelled', $round, reason: $reason, user: $actor);

            return $round;
        });
    }
}
