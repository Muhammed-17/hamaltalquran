<?php

namespace App\Policies;

use App\Models\CompetitionExaminerLevel;
use App\Models\CompetitionParticipant;
use App\Models\User;

/**
 * صلاحيات اختبار مشارك في مسابقة.
 *
 * هذه الـ policy لم تكن موجودة أصلًا، فكانت كل استدعاءات
 * authorize('examine'/'review', $participant) — في بوابة المختبر
 * وطلب حفظ الإجابة ونتيجة الأدمن اليدوية — تفشل للجميع وتُغلق
 * تدفق التصحيح بالكامل.
 *
 * القاعدة:
 * - مستخدم بسجل مختبر (Examiner): يجب أن يكون مسندًا لمستوى المشارك
 *   نفسه في نفس المسابقة (CompetitionExaminerLevel).
 * - غير المختبر (وضع الأدمن): صلاحية «examine competition participants».
 */
class CompetitionParticipantPolicy
{
    public function examine(User $user, CompetitionParticipant $participant): bool
    {
        $examiner = $user->examiner;

        if (!$examiner) {
            return $user->can('examine competition participants');
        }

        return CompetitionExaminerLevel::query()
            ->where('competition_level_id', $participant->competition_level_id)
            ->whereHas('competitionExaminer', fn ($q) => $q
                ->where('competition_id', $participant->competition_id)
                ->where('examiner_id', $examiner->id))
            ->exists();
    }

    public function review(User $user, CompetitionParticipant $participant): bool
    {
        return $this->examine($user, $participant);
    }
}
