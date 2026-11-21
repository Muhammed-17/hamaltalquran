<?php

namespace App\Services\Badges;

use App\Enums\Badges\StockMovementType;
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
     * تسجيل حركة مخزون مركزي (افتتاحي / إضافة / تسوية).
     * المرتجعات من المعلمين تمر عبر returnFromTeacher لخصمها من عهدة المعلم.
     */
    public function recordMovement(
        BadgeType $type,
        StockMovementType $movementType,
        int $quantity,
        User $actor,
        ?string $notes = null,
        ?string $sourceType = null,
        ?int $sourceId = null,
    ): BadgeStockMovement {
        return DB::transaction(function () use ($type, $movementType, $quantity, $actor, $notes, $sourceType, $sourceId) {
            $this->balances->lockType($type->id);

            if ($movementType === StockMovementType::Adjustment) {
                if ($quantity === 0) {
                    throw ValidationException::withMessages(['quantity' => 'كمية التسوية لا يمكن أن تكون صفرًا.']);
                }
                if (empty($notes)) {
                    throw ValidationException::withMessages(['notes' => 'يجب توثيق سبب التسوية في الملاحظات.']);
                }
            } elseif ($quantity <= 0) {
                throw ValidationException::withMessages(['quantity' => 'الكمية يجب أن تكون أكبر من صفر.']);
            }

            $newBalance = $this->balances->adminBalance($type->id) + ($movementType === StockMovementType::Adjustment ? $quantity : abs($quantity));
            if ($newBalance < 0) {
                throw ValidationException::withMessages(['quantity' => 'التسوية ستجعل رصيد الإدارة سالبًا. الرصيد الحالي: ' . $this->balances->adminBalance($type->id)]);
            }

            $movement = BadgeStockMovement::create([
                'badge_type_id'    => $type->id,
                'movement_type'    => $movementType->value,
                'quantity'         => $quantity,
                'source_type'      => $sourceType,
                'source_id'        => $sourceId,
                'destination_type' => 'admin',
                'destination_id'   => null,
                'notes'            => $notes,
                'created_by'       => $actor->id,
                'created_at'       => now(),
            ]);

            $this->logger->log('stock.' . $movementType->value, $movement, newValues: $movement->only(['badge_type_id', 'movement_type', 'quantity']), user: $actor);

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
                'movement_type'    => StockMovementType::ReturnToAdmin->value,
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
