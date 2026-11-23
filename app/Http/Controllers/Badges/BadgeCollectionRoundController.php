<?php

namespace App\Http\Controllers\Badges;

use App\Http\Controllers\Controller;
use App\Http\Requests\Badges\CancelBadgeOperationRequest;
use App\Http\Requests\Badges\StoreBadgeCollectionItemRequest;
use App\Http\Requests\Badges\UpdateBadgeCollectionRoundRequest;
use App\Models\Badges\BadgeCollectionItem;
use App\Models\Badges\BadgeCollectionRound;
use App\Models\Circle;
use App\Services\Badges\BadgeAccessService;
use App\Services\Badges\BadgeCollectionService;
use App\Services\UserAccessService;
use Illuminate\Http\Request;

class BadgeCollectionRoundController extends Controller
{
    public function index(Request $request, UserAccessService $access)
    {
        $this->authorize('viewAny', BadgeCollectionRound::class);

        // التحصيل إداري: كل الجولات في كل الفروع والمراكز مرئية لمن يديره
        $rounds = BadgeCollectionRound::with(['circle', 'teacher'])
            ->withCount('items')
            ->when($request->filled('circle_id'), fn ($q) => $q->where('circle_id', $request->circle_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('month') && preg_match('/^\d{4}-\d{2}$/', (string) $request->month), function ($q) use ($request) {
                $start = \Carbon\Carbon::createFromFormat('Y-m', $request->month)->startOfMonth();
                $q->whereBetween('collection_date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()]);
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('badges.collections.index', [
            'rounds' => $rounds,
            'circles' => Circle::orderBy('name')->get(),
        ]);
    }

    public function create(Request $request, UserAccessService $access, BadgeAccessService $badgeAccess, BadgeCollectionService $service)
    {
        $this->authorize('create', BadgeCollectionRound::class);

        $user = auth()->user();
        // من يدير التحصيل يرى كل الحلقات في كل الفروع والمراكز
        $circles = Circle::orderBy('name')->get();
        $circle = $circles->firstWhere('id', (int) $request->circle_id) ?? $circles->first();
        // مسؤول التحصيل: كل المشرفين والمديرين والمديرين العامين عدا مدير النظام
        $teachers = $badgeAccess->selectableCollectors();
        $teacherId = (int) $request->teacher_id
            ?: ($teachers->contains('id', $user->id) ? $user->id : null);
        $date = $request->filled('collection_date') ? $request->collection_date : now()->toDateString();

        // جلسة التحصيل: جولة مسودة واحدة للسياق (حلقة + مسؤول + تاريخ)
        $session = ($circle && $teacherId) ? $service->sessionRound($circle, $teacherId, $date) : null;
        $settled = $session ? $service->settledStudents($session) : collect();
        $breakdown = $circle ? $service->breakdownForCircle($circle) : collect();

        $studentId = (int) $request->student_id ?: null;
        $selectedGroup = $studentId ? $breakdown->first(fn ($g) => $g['student']->id === $studentId) : null;

        return view('badges.collections.create', [
            'circles' => $circles,
            'circle' => $circle,
            'teachers' => $teachers,
            'teacherId' => $teacherId,
            'collectionDate' => $date,
            'breakdown' => $breakdown,
            'session' => $session,
            'settled' => $settled,
            'selectedStudentId' => $studentId,
            'selectedGroup' => $selectedGroup,
            'selectedSettled' => $studentId ? ($settled[$studentId] ?? null) : null,
        ]);
    }

    /** حفظ تحصيل طالب واحد داخل جلسة التحصيل (نتيجته نهائية من لحظة الحفظ) */
    public function settleStudent(\App\Http\Requests\Badges\SettleBadgeCollectionStudentRequest $request, BadgeCollectionService $service)
    {
        $circle = Circle::findOrFail($request->circle_id);

        $round = $service->findOrCreateSessionRound($circle, (int) $request->teacher_id, $request->collection_date, $request->user());
        $service->settleStudent($round, (int) $request->student_id, (array) $request->input('amounts', []), $request->user());

        $studentName = \Illuminate\Support\Facades\DB::table('students')->where('id', (int) $request->student_id)->value('name');

        return redirect()->route('badges.collections.create', [
            'circle_id' => $circle->id,
            'teacher_id' => (int) $request->teacher_id,
            'collection_date' => $request->collection_date,
        ])->with('success', "تم حفظ تحصيل الطالب {$studentName} في الجلسة.");
    }

    public function show(BadgeCollectionRound $collection)
    {
        $this->authorize('view', $collection);

        // خصومات المستحق (استبدال بلعبة) المنفذة ضمن هذه الجولة — صف لكل طالب ونوع
        // (أسماء الطلاب تُقرأ بلا نطاق مركز: التحصيل إداري عابر للمراكز)
        $owedDeductions = \App\Models\Badges\BadgeRedemption::where('collection_round_id', $collection->id)
            ->with(['student' => fn ($q) => $q->withoutGlobalScope(\App\Models\Scopes\CenterScope::class), 'items.badgeType'])
            ->get()
            ->flatMap(fn ($r) => $r->items->where('shortage_quantity', '>', 0)
                ->groupBy('badge_type_id')
                ->map(fn ($items, $typeId) => [
                    'student'   => $r->student?->name,
                    'type'      => $items->first()->badgeType?->name,
                    'qty'       => (int) $items->sum('shortage_quantity'),
                    'cancelled' => !$r->isCompleted(),
                ]))
            ->values();

        $collection->load(['circle', 'teacher', 'creator', 'items.student' => fn ($q) => $q->withoutGlobalScope(\App\Models\Scopes\CenterScope::class), 'items.badgeType']);

        // عرض مجمّع: صف واحد لكل (طالب × نوع) بدل صف لكل توزيع
        $studentGroups = $collection->items
            ->groupBy('student_id')
            ->map(fn ($items) => [
                'student' => $items->first()->student,
                // بنود صفرية بلا نوع (طالب بلا توزيعات) لا تُبنى لها صفوف أنواع
                'types' => $items->filter(fn ($i) => $i->badge_type_id !== null)->groupBy('badge_type_id')->map(function ($typeItems) {
                    $distributed = (int) $typeItems->sum('distributed_quantity');
                    $collectedTotal = (int) $typeItems->sum('collected_quantity');
                    $damagedQty = (int) $typeItems->where('status', \App\Enums\Badges\CollectionItemStatus::Damaged)->sum('collected_quantity');
                    $missingQty = (int) $typeItems->where('status', \App\Enums\Badges\CollectionItemStatus::Missing)
                        ->sum(fn ($i) => $i->distributed_quantity - $i->collected_quantity);

                    return [
                        'type' => $typeItems->first()->badgeType,
                        'distributed' => $distributed,
                        'good' => $collectedTotal - $damagedQty,
                        // التالف والمفقود فئة واحدة في العرض: لم يرجع صالحًا
                        'lost' => $damagedQty + $missingQty,
                        'pending' => $distributed - $collectedTotal - $missingQty,
                        'notes' => $typeItems->pluck('notes')->filter()->first(),
                    ];
                })->sortBy(fn ($t) => $t['type']?->name)->values(),
            ])
            ->sortBy(fn ($g) => $g['student']?->name)
            ->values();

        return view('badges.collections.show', ['round' => $collection, 'owedDeductions' => $owedDeductions, 'studentGroups' => $studentGroups]);
    }

    public function edit(BadgeCollectionRound $collection)
    {
        $this->authorize('update', $collection);

        return view('badges.collections.edit', ['round' => $collection]);
    }

    public function update(UpdateBadgeCollectionRoundRequest $request, BadgeCollectionRound $collection)
    {
        if (!$collection->isDraft()) {
            return back()->with('error', 'لا يمكن تعديل جولة غير مسودة.');
        }

        $collection->update($request->only(['collection_date', 'notes']));

        return redirect()->route('badges.collections.show', $collection)->with('success', 'تم تحديث الجولة بنجاح.');
    }

    public function updateItem(StoreBadgeCollectionItemRequest $request, BadgeCollectionItem $item, BadgeCollectionService $service)
    {
        $service->updateItem($item, (int) $request->collected_quantity, $request->status, $request->notes, $request->user());

        return back()->with('success', 'تم تحديث بند التحصيل.');
    }

    public function complete(CancelBadgeOperationRequest $request, BadgeCollectionRound $collection, BadgeCollectionService $service)
    {
        $this->authorize('complete', $collection);

        $service->complete($collection, $request->user());

        return redirect()->route('badges.collections.show', $collection)->with('success', 'تم اعتماد الجولة بنجاح.');
    }

    public function cancel(CancelBadgeOperationRequest $request, BadgeCollectionRound $collection, BadgeCollectionService $service)
    {
        $this->authorize('cancel', $collection);

        $service->cancel($collection, $request->user(), $request->reason);

        return redirect()->route('badges.collections.index')->with('success', 'تم إلغاء الجولة بنجاح.');
    }
}
