<?php

namespace App\Services\Badges;

use App\Models\Badges\BadgeCollectionItem;
use App\Models\Badges\BadgeDistribution;
use App\Models\Badges\BadgeStockMovement;
use App\Models\Badges\BadgeTeacherHandover;
use Illuminate\Support\Facades\DB;

/**
 * المصدر الموحد لحساب أرصدة الأوسمة — لا تُخزَّن الأرصدة في أي جدول.
 *
 * رصيد الإدارة = حركات المخزون (افتتاحي + إضافة + مرتجع + تسويات بإشارتها)
 *               − التسليمات النشطة للمعلمين
 * رصيد المعلم  = التسليمات النشطة − التوزيعات النشطة − المرتجعات للإدارة
 *               + المحصَّل فعليًا في جولات معتمدة (عاد لعهدته)
 * رصيد الطالب  = التوزيعات النشطة − المحصَّل فعليًا في جولات معتمدة
 * (الاستحقاقات غير المسلّمة لا تدخل رصيد الطالب، وغير المحصَّل والمفقود
 *  لا يُعدّ مرتجعًا لأحد)
 */
class BadgeBalanceService
{
    public function adminBalance(int $badgeTypeId): int
    {
        $movements = (int) BadgeStockMovement::where('badge_type_id', $badgeTypeId)
            ->selectRaw("SUM(CASE WHEN movement_type = 'adjustment' THEN quantity ELSE ABS(quantity) END) as total")
            ->value('total');

        $handedOver = (int) BadgeTeacherHandover::active()
            ->where('badge_type_id', $badgeTypeId)
            ->sum('quantity');

        return $movements - $handedOver;
    }

    public function teacherBalance(int $teacherUserId, int $badgeTypeId): int
    {
        $received = (int) BadgeTeacherHandover::active()
            ->where('teacher_id', $teacherUserId)
            ->where('badge_type_id', $badgeTypeId)
            ->sum('quantity');

        $distributed = (int) BadgeDistribution::active()
            ->where('teacher_id', $teacherUserId)
            ->where('badge_type_id', $badgeTypeId)
            ->sum('quantity');

        $returnedToAdmin = (int) BadgeStockMovement::where('movement_type', 'return')
            ->where('badge_type_id', $badgeTypeId)
            ->where('source_type', 'teacher')
            ->where('source_id', $teacherUserId)
            ->sum('quantity');

        // المحصّل الصالح فقط يعود لعهدة المعلم — التالف رجع لكنه غير صالح
        $collectedBack = (int) BadgeCollectionItem::where('badge_type_id', $badgeTypeId)
            ->where('collected_quantity', '>', 0)
            ->where('status', '!=', 'damaged')
            ->whereHas('round', fn ($q) => $q
                ->where('status', 'completed')
                ->where('teacher_id', $teacherUserId))
            ->sum('collected_quantity');

        return $received - $distributed - $returnedToAdmin + $collectedBack;
    }

    public function studentBalance(int $studentId, int $badgeTypeId): int
    {
        $distributed = (int) BadgeDistribution::active()
            ->where('student_id', $studentId)
            ->where('badge_type_id', $badgeTypeId)
            ->sum('quantity');

        $collected = (int) BadgeCollectionItem::where('student_id', $studentId)
            ->where('badge_type_id', $badgeTypeId)
            ->whereHas('round', fn ($q) => $q->where('status', 'completed'))
            ->sum('collected_quantity');

        return $distributed - $collected;
    }

    /** خريطة أرصدة الإدارة لكل الأنواع: [badge_type_id => balance] */
    public function adminBalances(): array
    {
        $movements = BadgeStockMovement::selectRaw("badge_type_id, SUM(CASE WHEN movement_type = 'adjustment' THEN quantity ELSE ABS(quantity) END) as total")
            ->groupBy('badge_type_id')->pluck('total', 'badge_type_id');

        $handedOver = BadgeTeacherHandover::active()
            ->selectRaw('badge_type_id, SUM(quantity) as total')
            ->groupBy('badge_type_id')->pluck('total', 'badge_type_id');

        $result = [];
        foreach ($movements->keys()->merge($handedOver->keys())->unique() as $typeId) {
            $result[$typeId] = (int) ($movements[$typeId] ?? 0) - (int) ($handedOver[$typeId] ?? 0);
        }

        return $result;
    }

    /** خريطة أرصدة معلم لكل الأنواع: [badge_type_id => balance] */
    public function teacherBalances(int $teacherUserId): array
    {
        $typeIds = collect()
            ->merge(BadgeTeacherHandover::where('teacher_id', $teacherUserId)->pluck('badge_type_id'))
            ->merge(BadgeDistribution::where('teacher_id', $teacherUserId)->pluck('badge_type_id'))
            ->unique();

        $result = [];
        foreach ($typeIds as $typeId) {
            $result[$typeId] = $this->teacherBalance($teacherUserId, (int) $typeId);
        }

        return $result;
    }

    /** إجمالي المحصَّل فعليًا (جولات معتمدة فقط) */
    public function totalCollected(?int $badgeTypeId = null): int
    {
        return (int) BadgeCollectionItem::when($badgeTypeId, fn ($q) => $q->where('badge_type_id', $badgeTypeId))
            ->whereHas('round', fn ($q) => $q->where('status', 'completed'))
            ->sum('collected_quantity');
    }

    /** إجمالي التالف المرجع في جولات معتمدة (سُوّي من الطلاب ولم يدخل عهدة صالحة) */
    public function totalDamaged(?int $badgeTypeId = null): int
    {
        return (int) BadgeCollectionItem::when($badgeTypeId, fn ($q) => $q->where('badge_type_id', $badgeTypeId))
            ->where('status', 'damaged')
            ->whereHas('round', fn ($q) => $q->where('status', 'completed'))
            ->sum('collected_quantity');
    }

    /** إجمالي المفقود المسجل في جولات معتمدة */
    public function totalMissing(?int $badgeTypeId = null): int
    {
        return (int) BadgeCollectionItem::when($badgeTypeId, fn ($q) => $q->where('badge_type_id', $badgeTypeId))
            ->where('status', 'missing')
            ->whereHas('round', fn ($q) => $q->where('status', 'completed'))
            ->selectRaw('SUM(distributed_quantity - collected_quantity) as total')
            ->value('total');
    }

    public function lockType(int $badgeTypeId): void
    {
        DB::table('badge_types')->where('id', $badgeTypeId)->lockForUpdate()->first();
    }
}
