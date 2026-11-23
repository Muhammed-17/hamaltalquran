<?php

namespace App\Services\Badges;

use App\Enums\Badges\EntitlementStatus;
use App\Enums\Badges\RedemptionStatus;
use App\Models\Badges\BadgeCollectionRound;
use App\Models\Badges\BadgeEntitlement;
use App\Models\Badges\BadgeRedemption;
use App\Models\Badges\BadgeRedemptionItem;
use App\Models\Badges\BadgeStockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * محرك خصم «المستحق» بالاستبدال بلعبة — يُستدعى من داخل جولات التحصيل
 * فقط (لا واجهة مستقلة له): عند إنشاء جولة تحصيل يستطيع المُحصّل أن
 * يخصم من حساب الطالب جزءًا من أوسمته المستحقة غير المسلّمة (قيمتها
 * استُبدلت بلعبة في الواقع)، فيُقفل عجزها كمستهلك بالاستبدال بدل أن
 * يبقى دينًا على المركز. الخصم مرتبط بالجولة ويُعكَس عند إلغائها.
 * القطع المسلّمة لا تمر من هنا — تحصيلها العادي بجولة التحصيل نفسها.
 */
class BadgeRedemptionService
{
    public function __construct(
        private BadgeLogService $logger,
        private BadgeShortageService $shortages,
    ) {}

    /** المستحق المستهلك من كل استحقاق في خصومات مكتملة */
    private function redeemedShortageByEntitlement(): \Illuminate\Support\Collection
    {
        return BadgeRedemptionItem::where('shortage_quantity', '>', 0)
            ->whereHas('redemption', fn ($q) => $q->where('status', RedemptionStatus::Completed->value))
            ->selectRaw('entitlement_id, SUM(shortage_quantity) as total')
            ->groupBy('entitlement_id')
            ->pluck('total', 'entitlement_id');
    }

    /** صافي المستحق غير المسلّم لاستحقاق (بعد خصم المستهلك سابقًا) */
    public function netOwed(BadgeEntitlement $entitlement, ?int $redeemedShortage = null): int
    {
        $redeemedShortage ??= (int) BadgeRedemptionItem::where('entitlement_id', $entitlement->id)
            ->where('shortage_quantity', '>', 0)
            ->whereHas('redemption', fn ($q) => $q->where('status', RedemptionStatus::Completed->value))
            ->sum('shortage_quantity');

        return max(0, (int) $entitlement->quantity - $entitlement->deliveredQuantity() - $redeemedShortage);
    }

    /**
     * خصم مستحق طالب داخل حلقة ضمن جولة تحصيل:
     * $owedByType[typeId] = الكمية المطلوب خصمها من المستحق غير المسلّم.
     * تُوزَّع على استحقاقات الحلقة من الأقدم، وتُزامَن عجوزاتها بالصافي.
     * يعيد سجل الخصم أو null إن كان الإجمالي صفرًا.
     */
    public function consumeOwedForRound(BadgeCollectionRound $round, int $studentId, array $owedByType, User $actor): ?BadgeRedemption
    {
        $owedByType = array_filter(array_map('intval', $owedByType), fn ($q) => $q > 0);
        if ($owedByType === []) {
            return null;
        }

        return DB::transaction(function () use ($round, $studentId, $owedByType, $actor) {
            $entitlements = BadgeEntitlement::where('student_id', $studentId)
                ->where('circle_id', $round->circle_id)
                ->where('status', '!=', EntitlementStatus::Cancelled->value)
                ->whereIn('badge_type_id', array_keys($owedByType))
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            $redeemedShortage = $this->redeemedShortageByEntitlement();

            foreach ($owedByType as $typeId => $want) {
                $typeEntitlements = $entitlements->where('badge_type_id', $typeId);
                $available = (int) $typeEntitlements->sum(
                    fn ($e) => $this->netOwed($e, (int) ($redeemedShortage[$e->id] ?? 0))
                );
                if ($want > $available) {
                    $typeName = $typeEntitlements->first()?->badgeType?->name ?? '';
                    throw ValidationException::withMessages([
                        'owed_amounts' => "كمية خصم المستحق لنوع «{$typeName}» تتجاوز المتاح ({$available}).",
                    ]);
                }
            }

            $redemption = BadgeRedemption::create([
                'student_id'          => $studentId,
                'period_month'        => \Carbon\Carbon::parse($round->collection_date)->format('Y-m'),
                'redemption_date'     => $round->collection_date,
                'status'              => RedemptionStatus::Completed->value,
                'notes'               => 'خصم مستحق بالاستبدال ضمن جولة تحصيل #' . $round->id,
                'created_by'          => $actor->id,
                'collection_round_id' => $round->id,
            ]);

            $affected = [];
            foreach ($owedByType as $typeId => $want) {
                foreach ($entitlements->where('badge_type_id', $typeId) as $e) {
                    if ($want <= 0) {
                        break;
                    }
                    $take = min($want, $this->netOwed($e, (int) ($redeemedShortage[$e->id] ?? 0)));
                    if ($take <= 0) {
                        continue;
                    }
                    BadgeRedemptionItem::create([
                        'redemption_id'     => $redemption->id,
                        'entitlement_id'    => $e->id,
                        'badge_type_id'     => $typeId,
                        'shortage_quantity' => $take,
                    ]);
                    $want -= $take;
                    $affected[$e->id] = $e;
                }
            }

            foreach ($affected as $e) {
                $this->shortages->syncEntitlementState($e);
            }

            $this->logger->log('redemption.created', $redemption, newValues: $redemption->only(['student_id', 'period_month', 'collection_round_id']), user: $actor);

            return $redemption;
        });
    }

    /** عكس كل خصومات جولة تحصيل (عند إلغائها وهي مسودة) */
    public function cancelForRound(BadgeCollectionRound $round, User $actor): void
    {
        BadgeRedemption::where('collection_round_id', $round->id)
            ->where('status', RedemptionStatus::Completed->value)
            ->get()
            ->each(fn ($r) => $this->cancel($r, $actor, 'أُلغيت جولة التحصيل المرتبطة'));
    }

    /** عكس خصومات طالب واحد داخل جولة (عند إعادة تسوية بياناته في نفس الجولة) */
    public function cancelForStudent(BadgeCollectionRound $round, int $studentId, User $actor): void
    {
        BadgeRedemption::where('collection_round_id', $round->id)
            ->where('student_id', $studentId)
            ->where('status', RedemptionStatus::Completed->value)
            ->get()
            ->each(fn ($r) => $this->cancel($r, $actor, 'أُعيدت تسوية الطالب داخل الجولة'));
    }

    /** إلغاء خصم مكتمل: إعادة مزامنة العجز بعد خروج بنوده من الحساب */
    public function cancel(BadgeRedemption $redemption, User $actor, ?string $reason = null): BadgeRedemption
    {
        return DB::transaction(function () use ($redemption, $actor, $reason) {
            $redemption = BadgeRedemption::lockForUpdate()->findOrFail($redemption->id);

            if (!$redemption->isCompleted()) {
                throw ValidationException::withMessages(['redemption' => 'يمكن إلغاء الخصومات المكتملة فقط.']);
            }

            // خصومات التحصيل لا حركات مخزون لها (لا قطع مادية) — الحذف وقائي
            BadgeStockMovement::where('source_type', 'redemption')
                ->where('reference_id', $redemption->id)
                ->delete();

            $redemption->update(['status' => RedemptionStatus::Cancelled->value]);

            $entitlementIds = $redemption->items()
                ->where('shortage_quantity', '>', 0)
                ->pluck('entitlement_id');

            foreach (BadgeEntitlement::whereIn('id', $entitlementIds)->get() as $e) {
                $this->shortages->syncEntitlementState($e);
            }

            $this->logger->log('redemption.cancelled', $redemption, reason: $reason, user: $actor);

            return $redemption;
        });
    }
}
