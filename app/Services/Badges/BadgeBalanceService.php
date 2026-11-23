<?php

namespace App\Services\Badges;

use App\Models\Badges\BadgeCollectionItem;
use App\Models\Badges\BadgeDistribution;
use App\Models\Badges\BadgeRedemptionItem;
use App\Models\Badges\BadgeStockMovement;
use App\Models\Badges\BadgeTeacherHandover;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * المصدر الموحد لحساب أرصدة الأوسمة — لا تُخزَّن الأرصدة في أي جدول.
 *
 * رصيد الإدارة = حركات المخزون (بإشارتها) − التسليمات النشطة الصادرة
 *               من الإدارة المركزية فقط (source_user_id = null)
 * رصيد العهدة لأي مستخدم = ما استلمه − ما سلّمه لغيره من عهدته
 *               − التوزيعات النشطة − المرتجعات للإدارة
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
            ->selectRaw("SUM(quantity) as total")
            ->value('total');

        // يُخصم من المركزي فقط ما خرج منه مباشرة — تسليمات العهد بين
        // المستخدمين تنقل العهدة ولا تمس المخزون المركزي
        $handedOver = (int) BadgeTeacherHandover::active()
            ->where('badge_type_id', $badgeTypeId)
            ->whereNull('source_user_id')
            ->sum('quantity');

        // وتوزيعات الأدمن المباشرة للطلاب تخرج من المركزي أيضًا
        $distributedByAdmin = (int) BadgeDistribution::active()
            ->where('badge_type_id', $badgeTypeId)
            ->whereIn('teacher_id', $this->adminUserIds())
            ->sum('quantity');

        return $movements - $handedOver - $distributedByAdmin;
    }

    public function teacherBalance(int $teacherUserId, int $badgeTypeId): int
    {
        $received = (int) BadgeTeacherHandover::active()
            ->where('teacher_id', $teacherUserId)
            ->where('badge_type_id', $badgeTypeId)
            ->sum('quantity');

        // ما سلّمه هذا المستخدم لغيره من عهدته الشخصية
        $given = (int) BadgeTeacherHandover::active()
            ->where('source_user_id', $teacherUserId)
            ->where('badge_type_id', $badgeTypeId)
            ->sum('quantity');

        $distributed = (int) BadgeDistribution::active()
            ->where('teacher_id', $teacherUserId)
            ->where('badge_type_id', $badgeTypeId)
            ->sum('quantity');

        // الإرجاع للإدارة يُميَّز بمصدره (معلم) لا بنوع حركة
        $returnedToAdmin = (int) BadgeStockMovement::where('badge_type_id', $badgeTypeId)
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

        return $received - $given - $distributed - $returnedToAdmin + $collectedBack;
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
        $movements = BadgeStockMovement::selectRaw("badge_type_id, SUM(quantity) as total")
            ->groupBy('badge_type_id')->pluck('total', 'badge_type_id');

        $handedOver = BadgeTeacherHandover::active()
            ->whereNull('source_user_id')
            ->selectRaw('badge_type_id, SUM(quantity) as total')
            ->groupBy('badge_type_id')->pluck('total', 'badge_type_id');

        $distributedByAdmin = BadgeDistribution::active()
            ->whereIn('teacher_id', $this->adminUserIds())
            ->selectRaw('badge_type_id, SUM(quantity) as total')
            ->groupBy('badge_type_id')->pluck('total', 'badge_type_id');

        $result = [];
        foreach ($movements->keys()->merge($handedOver->keys())->merge($distributedByAdmin->keys())->unique() as $typeId) {
            $result[$typeId] = (int) ($movements[$typeId] ?? 0) - (int) ($handedOver[$typeId] ?? 0) - (int) ($distributedByAdmin[$typeId] ?? 0);
        }

        return $result;
    }

    /** خريطة أرصدة معلم لكل الأنواع: [badge_type_id => balance] */
    public function teacherBalances(int $teacherUserId): array
    {
        $typeIds = collect()
            ->merge(BadgeTeacherHandover::where('teacher_id', $teacherUserId)->pluck('badge_type_id'))
            ->merge(BadgeTeacherHandover::where('source_user_id', $teacherUserId)->pluck('badge_type_id'))
            ->merge(BadgeDistribution::where('teacher_id', $teacherUserId)->pluck('badge_type_id'))
            ->unique();

        $result = [];
        foreach ($typeIds as $typeId) {
            $result[$typeId] = $this->teacherBalance($teacherUserId, (int) $typeId);
        }

        return $result;
    }

    /** معرّفات مستخدمي الإدارة — الأدمن يوزّع من المخزون المركزي لا من عهدة شخصية */
    private function adminUserIds(): array
    {
        return User::role('admin')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * رصيد الموزّع الفعلي لنوع واحد: الأدمن من المخزون المركزي، وأي موزّع
     * آخر من عهدته الشخصية. هذا هو الرصيد الذي تُبنى عليه قرارات التوزيع
     * وفتح العجز وما تعرضه الصفحات من «رصيد الموزّع».
     */
    public function distributorBalance(int $userId, int $badgeTypeId): int
    {
        $user = User::find($userId);

        return ($user && $user->hasRole('admin'))
            ? $this->adminBalance($badgeTypeId)
            : $this->teacherBalance($userId, $badgeTypeId);
    }

    /** خريطة أرصدة الموزّع لكل الأنواع: [badge_type_id => balance] */
    public function distributorBalances(int $userId): array
    {
        $user = User::find($userId);

        return ($user && $user->hasRole('admin'))
            ? $this->adminBalances()
            : $this->teacherBalances($userId);
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
