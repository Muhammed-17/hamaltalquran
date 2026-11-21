<?php

namespace App\Services\Badges;

use App\Enums\Badges\EntitlementStatus;
use App\Enums\Badges\ShortageStatus;
use App\Models\Badges\BadgeEntitlement;
use App\Models\Badges\BadgeShortage;

/**
 * مزامنة حالة الاستحقاق والعجز من التوزيعات النشطة الفعلية.
 * العجز يعكس دائمًا الكمية غير المسلّمة من الاستحقاق:
 * shortage_quantity = required − delivered
 */
class BadgeShortageService
{
    /**
     * يُستدعى عند إنشاء الاستحقاق: إن لم يكفِ رصيد المعلم لتغطية الاستحقاق
     * كاملًا يُفتح سجل عجز (واحد فقط لكل استحقاق — قيد فرادة في القاعدة).
     */
    public function openIfUnderstocked(BadgeEntitlement $entitlement, int $teacherBalance): ?BadgeShortage
    {
        if ($teacherBalance >= $entitlement->quantity) {
            return null;
        }

        return BadgeShortage::firstOrCreate(
            ['entitlement_id' => $entitlement->id],
            [
                'student_id'         => $entitlement->student_id,
                'teacher_id'         => $entitlement->teacher_id,
                'badge_type_id'      => $entitlement->badge_type_id,
                'required_quantity'  => $entitlement->quantity,
                'delivered_quantity' => 0,
                'shortage_quantity'  => $entitlement->quantity,
                'status'             => ShortageStatus::Pending->value,
            ]
        );
    }

    /** إعادة حساب حالة الاستحقاق وعجزه بعد أي توزيع/إلغاء توزيع/تعديل كمية */
    public function syncEntitlementState(BadgeEntitlement $entitlement): void
    {
        $entitlement->refresh();
        $delivered = $entitlement->deliveredQuantity();

        if ($entitlement->status !== EntitlementStatus::Cancelled) {
            $entitlement->status = match (true) {
                $delivered <= 0 => EntitlementStatus::Pending,
                $delivered < $entitlement->quantity => EntitlementStatus::Partial,
                default => EntitlementStatus::Completed,
            };
            $entitlement->save();
        }

        $shortage = $entitlement->shortage;
        if ($shortage && $shortage->status !== ShortageStatus::Cancelled) {
            $remaining = max(0, $entitlement->quantity - $delivered);
            $shortage->required_quantity = $entitlement->quantity;
            $shortage->delivered_quantity = $delivered;
            $shortage->shortage_quantity = $remaining;
            $shortage->status = match (true) {
                $remaining === 0 => ShortageStatus::Resolved,
                $delivered > 0 => ShortageStatus::Partial,
                default => ShortageStatus::Pending,
            };
            $shortage->save();
        }
    }

    public function cancelForEntitlement(BadgeEntitlement $entitlement): void
    {
        $entitlement->shortage?->update(['status' => ShortageStatus::Cancelled->value]);
    }
}
