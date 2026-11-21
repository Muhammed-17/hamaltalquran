<?php

namespace App\Services\Badges;

use App\Enums\Badges\CollectionItemStatus;
use App\Enums\Badges\CollectionRoundStatus;
use App\Models\Badges\BadgeCollectionItem;
use App\Models\Badges\BadgeCollectionRound;
use App\Models\Badges\BadgeDistribution;
use App\Models\Circle;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BadgeCollectionService
{
    public function __construct(private BadgeLogService $logger) {}

    /**
     * إنشاء جولة تحصيل (مسودة) لحلقة:
     * تُدرج كل التوزيعات النشطة غير المحصلة بالكامل لطلاب الحلقة،
     * وصفًا صفريًا لكل طالب لا أوسمة لديه حتى تكون الجولة شاملة الجميع.
     */
    public function createRound(Circle $circle, int $teacherUserId, string $date, User $actor, ?string $notes = null): BadgeCollectionRound
    {
        return DB::transaction(function () use ($circle, $teacherUserId, $date, $actor, $notes) {
            $round = BadgeCollectionRound::create([
                'circle_id'       => $circle->id,
                'teacher_id'      => $teacherUserId,
                'collection_date' => $date,
                'status'          => CollectionRoundStatus::Draft->value,
                'notes'           => $notes,
                'created_by'      => $actor->id,
            ]);

            $students = $circle->students()->orderBy('name')->get();

            // الكميات المحجوزة في جولات أخرى (معتمدة + مسودات قائمة) لكل توزيع
            $reservedByDistribution = BadgeCollectionItem::whereNotNull('distribution_id')
                ->whereHas('round', fn ($q) => $q->whereIn('status', ['completed', 'draft']))
                ->selectRaw('distribution_id, SUM(collected_quantity) as total')
                ->groupBy('distribution_id')
                ->pluck('total', 'distribution_id');

            $distributions = BadgeDistribution::active()
                ->whereIn('student_id', $students->pluck('id'))
                ->whereHas('entitlement', fn ($q) => $q->where('circle_id', $circle->id))
                ->orderBy('student_id')
                ->get();

            $studentsWithItems = [];
            foreach ($distributions as $distribution) {
                $alreadyCollected = (int) ($reservedByDistribution[$distribution->id] ?? 0);
                $remaining = $distribution->quantity - $alreadyCollected;
                if ($remaining <= 0) {
                    continue;
                }

                BadgeCollectionItem::create([
                    'round_id'             => $round->id,
                    'student_id'           => $distribution->student_id,
                    'distribution_id'      => $distribution->id,
                    'badge_type_id'        => $distribution->badge_type_id,
                    'distributed_quantity' => $remaining,
                    'collected_quantity'   => 0,
                    'status'               => CollectionItemStatus::NotCollected->value,
                ]);
                $studentsWithItems[$distribution->student_id] = true;
            }

            // طلاب بلا أوسمة للتحصيل: صف صفري لشمول سجل الجولة
            foreach ($students as $student) {
                if (!isset($studentsWithItems[$student->id])) {
                    BadgeCollectionItem::create([
                        'round_id'             => $round->id,
                        'student_id'           => $student->id,
                        'distribution_id'      => null,
                        'badge_type_id'        => null,
                        'distributed_quantity' => 0,
                        'collected_quantity'   => 0,
                        'status'               => CollectionItemStatus::NotCollected->value,
                    ]);
                }
            }

            $this->logger->log('collection.created', $round, newValues: $round->only(['circle_id', 'teacher_id', 'collection_date']), user: $actor);

            return $round;
        });
    }

    /** تحديث بند تحصيل داخل مسودة: لا تحصيل فوق الموزع، والفقدان بكمية محصلة صفر */
    public function updateItem(BadgeCollectionItem $item, int $collected, ?string $status, ?string $notes, User $actor): BadgeCollectionItem
    {
        return DB::transaction(function () use ($item, $collected, $status, $notes, $actor) {
            $item = BadgeCollectionItem::lockForUpdate()->findOrFail($item->id);
            $round = BadgeCollectionRound::lockForUpdate()->findOrFail($item->round_id);

            if (!$round->isDraft()) {
                throw ValidationException::withMessages(['round' => 'لا يمكن تعديل بنود جولة غير مسودة.']);
            }
            if ($collected < 0 || $collected > $item->distributed_quantity) {
                throw ValidationException::withMessages(['collected_quantity' => 'الكمية المحصلة لا يمكن أن تتجاوز الكمية الموزعة للطالب.']);
            }

            $resolvedStatus = $status === CollectionItemStatus::Missing->value
                ? CollectionItemStatus::Missing
                : CollectionItemStatus::fromQuantities($item->distributed_quantity, $collected);

            if ($resolvedStatus === CollectionItemStatus::Missing && $collected > 0) {
                throw ValidationException::withMessages(['collected_quantity' => 'الوسام المفقود لا يمكن أن تكون له كمية محصلة.']);
            }

            $item->update([
                'collected_quantity' => $collected,
                'status'             => $resolvedStatus->value,
                'notes'              => $notes,
            ]);

            return $item;
        });
    }

    /** اعتماد الجولة: من هنا فقط تدخل الكميات المحصلة في الأرصدة */
    public function complete(BadgeCollectionRound $round, User $actor): BadgeCollectionRound
    {
        return DB::transaction(function () use ($round, $actor) {
            $round = BadgeCollectionRound::lockForUpdate()->findOrFail($round->id);

            if (!$round->isDraft()) {
                throw ValidationException::withMessages(['round' => 'يمكن اعتماد الجولات المسودة فقط.']);
            }

            $round->update(['status' => CollectionRoundStatus::Completed->value]);

            $this->logger->log('collection.completed', $round, user: $actor);

            return $round;
        });
    }

    public function cancel(BadgeCollectionRound $round, User $actor, ?string $reason = null): BadgeCollectionRound
    {
        return DB::transaction(function () use ($round, $actor, $reason) {
            $round = BadgeCollectionRound::lockForUpdate()->findOrFail($round->id);

            if (!$round->isDraft()) {
                throw ValidationException::withMessages(['round' => 'يمكن إلغاء الجولات المسودة فقط؛ الجولات المعتمدة نهائية.']);
            }

            $round->update(['status' => CollectionRoundStatus::Cancelled->value]);

            $this->logger->log('collection.cancelled', $round, reason: $reason, user: $actor);

            return $round;
        });
    }
}
