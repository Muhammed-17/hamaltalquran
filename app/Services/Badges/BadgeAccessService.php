<?php

namespace App\Services\Badges;

use App\Models\Circle;
use App\Models\Teacher;
use App\Models\User;
use App\Services\UserAccessService;
use Illuminate\Support\Facades\DB;

/**
 * نطاق الوصول الخاص بنظام الأوسمة، مبني على UserAccessService العام:
 * - الوصول لحلقة = canAccessCircle (معلمها/مشرف فرعها/مدير مركزها/إدارة)
 * - مسؤولية معلم عن حلقة = معلم رئيسي/مساعد فيها أو مشرف على فرعها
 */
class BadgeAccessService
{
    public function __construct(private UserAccessService $access) {}

    public function canAccessCircle(User $user, int $circleId): bool
    {
        return $this->access->canAccessCircle($user, $circleId);
    }

    /** هل المعلم (users.id) مسؤول عن هذه الحلقة؟ */
    public function teacherResponsibleForCircle(int $teacherUserId, int $circleId): bool
    {
        $teacher = Teacher::where('user_id', $teacherUserId)->first();
        if (!$teacher) {
            return false;
        }

        $circle = Circle::find($circleId);
        if (!$circle) {
            return false;
        }

        $teachesCircle = DB::table('circle_teacher')
            ->where('teacher_id', $teacher->id)
            ->where('circle_id', $circleId)
            ->exists();

        if ($teachesCircle) {
            return true;
        }

        // مشرف على فرع الحلقة
        return $circle->branch_id && DB::table('branch_teacher')
            ->where('teacher_id', $teacher->id)
            ->where('branch_id', $circle->branch_id)
            ->exists();
    }

    /** هل يقع هذا المعلم (users.id) ضمن نطاق وصول المستخدم؟ */
    public function teacherInScope(User $user, int $teacherUserId): bool
    {
        if ($user->hasRole(['admin', 'general_manager'])) {
            return true;
        }
        if ($user->id === $teacherUserId) {
            return true;
        }

        return $this->access->accessibleTeachers($user)
            ->where('user_id', $teacherUserId)
            ->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | سلسلة العهدة الهرمية
    | الرتب: مدير النظام 5 ← مدير عام 4 ← مدير فرع 3 ← مشرف 2 ← معلم 1
    | التسليم لتحت فقط (رتبة أقل حتمًا) وداخل نطاق المُسلِّم، والخصم من
    | عهدة المُسلِّم — إلا مدير النظام فيسلّم من المخزون المركزي.
    |--------------------------------------------------------------------------
    */

    /** رتبة العهدة للمستخدم (0 = ليس من طاقم العهدة) */
    public function custodyRank(User $user): int
    {
        return match (true) {
            $user->hasRole('admin') => 5,
            $user->hasRole('general_manager') => 4,
            $user->hasRole('manager') => 3,
            $user->hasRole('supervisor') => 2,
            $user->hasRole('teacher') => 1,
            default => 0,
        };
    }

    public function custodyRoleLabel(User $user): string
    {
        return match ($this->custodyRank($user)) {
            5 => 'مدير النظام',
            4 => 'مدير عام',
            3 => 'مدير فرع',
            2 => 'مشرف',
            1 => 'معلم',
            default => '—',
        };
    }

    /** هل يستطيع المُسلِّم تسليم هذا المستلم؟ (رتبة أقل + داخل النطاق) */
    public function canHandoverTo(User $giver, User $recipient): bool
    {
        $giverRank = $this->custodyRank($giver);
        $recipientRank = $this->custodyRank($recipient);

        if ($recipientRank === 0 || $giverRank <= $recipientRank) {
            return false;
        }

        return $this->recipientInScope($giver, $recipient);
    }

    /** نطاق المستلم بالنسبة للمُسلِّم حسب دور المُسلِّم */
    public function recipientInScope(User $giver, User $recipient): bool
    {
        if ($giver->hasRole(['admin', 'general_manager'])) {
            return true;
        }

        if ($giver->hasRole('manager')) {
            $giverCenter = $this->userCenterId($giver);
            $recipientCenter = $this->userCenterId($recipient);

            return $giverCenter !== null && $recipientCenter === $giverCenter;
        }

        if ($giver->hasRole('supervisor')) {
            // معلمو الحلقات التي يشرف عليها فقط
            return $this->access->accessibleTeachers($giver)
                ->where('user_id', $recipient->id)
                ->exists();
        }

        return false;
    }

    /** مركز المستخدم (من سجل معلمه) — أساس نطاق مدير الفرع والمستلمين */
    public function userCenterId(User $user): ?int
    {
        return $user->teacher?->branch?->center_id;
    }

    /** قائمة المستلمين المسموح للمستخدم التسليم لهم (رتبة أقل + نطاقه) */
    public function selectableRecipients(User $giver)
    {
        if ($giver->hasRole(['admin', 'general_manager'])) {
            $candidates = User::with('roles')->where('status', 'active')->orderBy('name')->get();
        } elseif ($giver->hasRole('manager')) {
            $centerId = $this->userCenterId($giver);
            if (!$centerId) {
                return collect();
            }
            $userIds = Teacher::whereHas('branch', fn ($b) => $b->where('center_id', $centerId))->pluck('user_id');
            $candidates = User::with('roles')->whereIn('id', $userIds)->where('status', 'active')->orderBy('name')->get();
        } elseif ($giver->hasRole('supervisor')) {
            $userIds = $this->access->accessibleTeachers($giver)->pluck('user_id');
            $candidates = User::with('roles')->whereIn('id', $userIds)->where('status', 'active')->orderBy('name')->get();
        } else {
            return collect();
        }

        $giverRank = $this->custodyRank($giver);

        return $candidates->filter(fn (User $u) => $u->id !== $giver->id
            && $this->custodyRank($u) > 0
            && $this->custodyRank($u) < $giverRank)->values();
    }

    /**
     * هل هذا المستخدم (أي رتبة) مسؤول عن توزيع حلقة؟
     * مدير النظام/العام: أي حلقة. مدير الفرع: حلقات مركزه.
     * المشرف/المعلم: بالمسؤولية المباشرة (يعلّمها أو يشرف على فرعها).
     */
    public function staffResponsibleForCircle(User $user, int $circleId): bool
    {
        if ($user->hasRole(['admin', 'general_manager'])) {
            return true;
        }

        if ($user->hasRole('manager')) {
            $circle = Circle::find($circleId);

            return $circle?->branch?->center_id
                && $this->access->accessibleCenters($user)->whereKey($circle->branch->center_id)->exists();
        }

        return $this->teacherResponsibleForCircle($user->id, $circleId);
    }

    /** قائمة المعلمين (مستخدمين) القابلين للاختيار في النماذج حسب نطاق المستخدم */
    public function selectableTeachers(User $user)
    {
        if ($user->hasRole(['admin', 'general_manager'])) {
            return User::whereHas('teacher')->orderBy('name')->get(['id', 'name']);
        }

        $teacherIds = $this->access->accessibleTeachers($user)->pluck('user_id');
        if (!$teacherIds->contains($user->id) && $user->teacher) {
            $teacherIds->push($user->id);
        }

        return User::whereIn('id', $teacherIds)->orderBy('name')->get(['id', 'name']);
    }
}
