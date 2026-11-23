<?php

namespace App\Services\Badges;

use App\Enums\Badges\EntitlementStatus;
use App\Models\Badges\BadgeDistribution;
use App\Models\Badges\BadgeEntitlement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BadgeDistributionService
{
    public function __construct(
        private BadgeBalanceService $balances,
        private BadgeShortageService $shortages,
        private BadgeLogService $logger,
    ) {}

    /**
     * توزيع فعلي على طالب ضمن استحقاق:
     * - لا يتجاوز الكمية المستحقة المتبقية
     * - لا يتجاوز رصيد المعلم الحالي
     * - يُخصم من عهدة المعلم لحظة التسليم فقط
     * - يرتبط بالعجز المفتوح إن وجد، وتتحدث الحالات تلقائيًا
     */
    public function distribute(BadgeEntitlement $entitlement, int $quantity, string $date, User $actor, ?string $notes = null): BadgeDistribution
    {
        return DB::transaction(function () use ($entitlement, $quantity, $date, $actor, $notes) {
            $entitlement = BadgeEntitlement::lockForUpdate()->findOrFail($entitlement->id);
            $this->balances->lockType($entitlement->badge_type_id);

            if ($entitlement->status === EntitlementStatus::Cancelled) {
                throw ValidationException::withMessages(['entitlement_id' => 'لا يمكن التوزيع على استحقاق ملغي.']);
            }
            if ($quantity <= 0) {
                throw ValidationException::withMessages(['quantity' => 'الكمية يجب أن تكون أكبر من صفر.']);
            }

            // المتبقي القابل للتوزيع يخصم المستهلك بالاستبدال (الكلي أو الجزئي)
            $remaining = $entitlement->distributableRemaining();
            if ($quantity > $remaining) {
                throw ValidationException::withMessages(['quantity' => "الكمية أكبر من المتبقي من الاستحقاق ({$remaining})."]);
            }

            $distributorBalance = $this->balances->distributorBalance($entitlement->teacher_id, $entitlement->badge_type_id);
            if ($quantity > $distributorBalance) {
                throw ValidationException::withMessages(['quantity' => "الكمية أكبر من رصيد الموزّع الحالي ({$distributorBalance})."]);
            }

            $shortage = $entitlement->shortage;
            $distribution = BadgeDistribution::create([
                'entitlement_id'    => $entitlement->id,
                'student_id'        => $entitlement->student_id,
                'teacher_id'        => $entitlement->teacher_id,
                'badge_type_id'     => $entitlement->badge_type_id,
                'quantity'          => $quantity,
                'distribution_date' => $date,
                'shortage_id'       => ($shortage && $shortage->isOpen()) ? $shortage->id : null,
                'notes'             => $notes,
                'status'            => 'active',
                'created_by'        => $actor->id,
            ]);

            $this->shortages->syncEntitlementState($entitlement);

            $this->logger->log('distribution.created', $distribution, newValues: $distribution->only(['entitlement_id', 'student_id', 'badge_type_id', 'quantity']), user: $actor);
            if ($shortage && $shortage->fresh()->status->value === 'resolved') {
                $this->logger->log('shortage.resolved', $shortage, user: $actor);
            }

            return $distribution;
        });
    }

    /** إلغاء توزيع (عكسي موثق): يعيد الكمية لعهدة المعلم ويفتح العجز من جديد إن لزم */
    public function cancel(BadgeDistribution $distribution, User $actor, ?string $reason = null): BadgeDistribution
    {
        return DB::transaction(function () use ($distribution, $actor, $reason) {
            $distribution = BadgeDistribution::lockForUpdate()->findOrFail($distribution->id);

            if ($distribution->isCancelled()) {
                throw ValidationException::withMessages(['distribution' => 'هذا التوزيع ملغي بالفعل.']);
            }
            if ($distribution->collectedQuantity() > 0) {
                throw ValidationException::withMessages(['distribution' => 'لا يمكن إلغاء توزيع تم تحصيل جزء منه في جولة معتمدة.']);
            }

            $distribution->update([
                'status'       => 'cancelled',
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
            ]);

            $this->shortages->syncEntitlementState($distribution->entitlement);

            $this->logger->log('distribution.cancelled', $distribution, reason: $reason, user: $actor);

            return $distribution;
        });
    }
}
