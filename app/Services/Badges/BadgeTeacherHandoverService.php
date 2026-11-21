<?php

namespace App\Services\Badges;

use App\Models\Badges\BadgeTeacherHandover;
use App\Models\Badges\BadgeType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BadgeTeacherHandoverService
{
    public function __construct(
        private BadgeBalanceService $balances,
        private BadgeLogService $logger,
    ) {}

    /** تسليم أوسمة من الإدارة لمعلم: خصم من الإدارة وإضافة لعهدة المعلم في معاملة واحدة */
    public function handover(User $teacher, BadgeType $type, int $quantity, string $date, User $actor, ?string $notes = null): BadgeTeacherHandover
    {
        return DB::transaction(function () use ($teacher, $type, $quantity, $date, $actor, $notes) {
            $this->balances->lockType($type->id);

            if ($quantity <= 0) {
                throw ValidationException::withMessages(['quantity' => 'الكمية يجب أن تكون أكبر من صفر.']);
            }

            $adminBalance = $this->balances->adminBalance($type->id);
            if ($quantity > $adminBalance) {
                throw ValidationException::withMessages(['quantity' => "الكمية أكبر من رصيد الإدارة الحالي ({$adminBalance})."]);
            }

            $handover = BadgeTeacherHandover::create([
                'teacher_id'    => $teacher->id,
                'badge_type_id' => $type->id,
                'quantity'      => $quantity,
                'handover_date' => $date,
                'notes'         => $notes,
                'status'        => 'active',
                'created_by'    => $actor->id,
            ]);

            $this->logger->log('handover.created', $handover, newValues: $handover->only(['teacher_id', 'badge_type_id', 'quantity']), user: $actor);

            return $handover;
        });
    }

    /** إلغاء تسليم (عكسي موثق — لا حذف): يُشترط أن تكون الكمية ما زالت في عهدة المعلم */
    public function cancel(BadgeTeacherHandover $handover, User $actor, ?string $reason = null): BadgeTeacherHandover
    {
        return DB::transaction(function () use ($handover, $actor, $reason) {
            $handover = BadgeTeacherHandover::lockForUpdate()->findOrFail($handover->id);
            $this->balances->lockType($handover->badge_type_id);

            if ($handover->isCancelled()) {
                throw ValidationException::withMessages(['handover' => 'هذا التسليم ملغي بالفعل.']);
            }

            $teacherBalance = $this->balances->teacherBalance($handover->teacher_id, $handover->badge_type_id);
            if ($teacherBalance < $handover->quantity) {
                throw ValidationException::withMessages(['handover' => 'لا يمكن إلغاء التسليم: جزء من الكمية وُزّع أو أُرجع بالفعل.']);
            }

            $handover->update([
                'status'       => 'cancelled',
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
            ]);

            $this->logger->log('handover.cancelled', $handover, reason: $reason, user: $actor);

            return $handover;
        });
    }
}
