<?php

namespace App\Services\Badges;

use App\Enums\Badges\EntitlementStatus;
use App\Enums\Badges\ShortageStatus;
use App\Models\Badges\BadgeEntitlement;
use App\Models\Badges\BadgeShortage;

/**
 * مزامنة حالة الاستحقاق والعجز من التوزيعات النشطة الفعلية.
 * سجل العجز/المتبقّي يعكس دائمًا الكمية غير المسلّمة من الاستحقاق:
 * shortage_quantity = required − delivered − redeemed
 * ويوجد سجل لكل استحقاق له متبقٍّ غير مسلّم بغض النظر عن رصيد الموزّع —
 * صفحة النواقص تعرض كل ما لم يصل إلى الطالب بعد (قرار عباس 2026-11-23).
 */
class BadgeShortageService
{
    /** المستهلك من هذا الاستحقاق بالاستبدال بلعبة (خصومات مكتملة فقط) */
    private function redeemedQuantity(BadgeEntitlement $entitlement): int
    {
        return (int) \App\Models\Badges\BadgeRedemptionItem::where('entitlement_id', $entitlement->id)
            ->where('shortage_quantity', '>', 0)
            ->whereHas('redemption', fn ($q) => $q->where('status', 'completed'))
            ->sum('shortage_quantity');
    }

    /**
     * يُستدعى عند إنشاء الاستحقاق: يُفتح سجل متبقٍّ لأي استحقاق غير مكتمل
     * التسليم (واحد فقط لكل استحقاق — قيد فرادة في القاعدة)، سواء أكان لدى
     * الموزّع رصيد يكفي أم لا؛ فالنواقص تتبع ما لم يصل للطالب لا رصيد الموزّع.
     */
    public function openForEntitlement(BadgeEntitlement $entitlement): ?BadgeShortage
    {
        if ($entitlement->status === EntitlementStatus::Cancelled) {
            return null;
        }

        $delivered = $entitlement->deliveredQuantity();
        $redeemed = $this->redeemedQuantity($entitlement);
        $remaining = max(0, $entitlement->quantity - $delivered - $redeemed);
        if ($remaining <= 0) {
            return null;
        }

        return BadgeShortage::firstOrCreate(
            ['entitlement_id' => $entitlement->id],
            [
                'student_id'         => $entitlement->student_id,
                'teacher_id'         => $entitlement->teacher_id,
                'badge_type_id'      => $entitlement->badge_type_id,
                'required_quantity'  => $entitlement->quantity,
                'delivered_quantity' => $delivered,
                'shortage_quantity'  => $remaining,
                'status'             => ($delivered > 0 || $redeemed > 0) ? ShortageStatus::Partial->value : ShortageStatus::Pending->value,
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

        // المتبقي الحقيقي يخصم المستهلك بالاستبدال أيضًا: الاستحقاق
        // المسلّم جزئيًا والمستهلك جزئيًا يبقى عجزه مفتوحًا بالصافي فقط
        $redeemed = $this->redeemedQuantity($entitlement);
        $remaining = max(0, $entitlement->quantity - $delivered - $redeemed);

        $shortage = $entitlement->shortage;
        if (!$shortage && $entitlement->status !== EntitlementStatus::Cancelled && $remaining > 0) {
            // لا سجل بعد (استحقاق سُجّل قبل هذا التعميم أو زادت كميته): يُفتح الآن
            $shortage = $this->openForEntitlement($entitlement);
        }

        if ($shortage && $shortage->status !== ShortageStatus::Cancelled) {
            $shortage->required_quantity = $entitlement->quantity;
            $shortage->delivered_quantity = $delivered;
            $shortage->shortage_quantity = $remaining;
            $shortage->status = match (true) {
                $remaining === 0 && $delivered >= $entitlement->quantity => ShortageStatus::Resolved,
                $remaining === 0 => ShortageStatus::Redeemed,
                $delivered > 0 || $redeemed > 0 => ShortageStatus::Partial,
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
