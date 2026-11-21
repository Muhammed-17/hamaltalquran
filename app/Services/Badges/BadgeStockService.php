<?php

namespace App\Services\Badges;

use App\Models\Badges\BadgeStockMovement;
use App\Models\Badges\BadgeType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BadgeStockService
{
    public function __construct(
        private BadgeBalanceService $balances,
        private BadgeLogService $logger,
    ) {}

    /**
     * تسجيل حركة مخزون مركزي. لا يوجد «نوع حركة»:
     * كمية موجبة = دخول مخزون (افتتاحي أو إضافة أو دفعة طباعة)،
     * وكمية سالبة = تسوية/تصحيح وسببها إلزامي في الملاحظات.
     * المرتجعات من المعلمين تمر عبر returnFromTeacher لخصمها من عهدة المعلم.
     */
    public function recordMovement(
        BadgeType $type,
        int $quantity,
        User $actor,
        ?string $notes = null,
    ): BadgeStockMovement {
        return DB::transaction(function () use ($type, $quantity, $actor, $notes) {
            $this->balances->lockType($type->id);

            if ($quantity === 0) {
                throw ValidationException::withMessages(['quantity' => 'الكمية لا يمكن أن تكون صفرًا.']);
            }
            if ($quantity < 0 && empty($notes)) {
                throw ValidationException::withMessages(['notes' => 'التسوية بالخصم تتطلب توثيق السبب في الملاحظات.']);
            }

            if ($this->balances->adminBalance($type->id) + $quantity < 0) {
                throw ValidationException::withMessages(['quantity' => 'هذه الحركة ستجعل رصيد الإدارة سالبًا. الرصيد الحالي: ' . $this->balances->adminBalance($type->id)]);
            }

            $movement = BadgeStockMovement::create([
                'badge_type_id'    => $type->id,
                'quantity'         => $quantity,
                'destination_type' => 'admin',
                'destination_id'   => null,
                'notes'            => $notes,
                'created_by'       => $actor->id,
                'created_at'       => now(),
            ]);

            $this->logger->log('stock.movement', $movement, newValues: $movement->only(['badge_type_id', 'quantity']), user: $actor);

            return $movement;
        });
    }

    /** إرجاع أوسمة من عهدة معلم إلى الإدارة: تُخصم من رصيده وتُضاف للإدارة */
    public function returnFromTeacher(User $teacher, BadgeType $type, int $quantity, User $actor, ?string $notes = null): BadgeStockMovement
    {
        return DB::transaction(function () use ($teacher, $type, $quantity, $actor, $notes) {
            $this->balances->lockType($type->id);

            if ($quantity <= 0) {
                throw ValidationException::withMessages(['quantity' => 'الكمية يجب أن تكون أكبر من صفر.']);
            }

            $teacherBalance = $this->balances->teacherBalance($teacher->id, $type->id);
            if ($quantity > $teacherBalance) {
                throw ValidationException::withMessages(['quantity' => "الكمية أكبر من رصيد المعلم الحالي ({$teacherBalance})."]);
            }

            $movement = BadgeStockMovement::create([
                'badge_type_id'    => $type->id,
                'quantity'         => $quantity,
                'source_type'      => 'teacher',
                'source_id'        => $teacher->id,
                'destination_type' => 'admin',
                'destination_id'   => null,
                'notes'            => $notes,
                'created_by'       => $actor->id,
                'created_at'       => now(),
            ]);

            $this->logger->log('stock.return', $movement, newValues: $movement->only(['badge_type_id', 'quantity', 'source_id']), user: $actor);

            return $movement;
        });
    }
}
