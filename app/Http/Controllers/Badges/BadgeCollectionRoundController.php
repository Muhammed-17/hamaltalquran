<?php

namespace App\Http\Controllers\Badges;

use App\Http\Controllers\Controller;
use App\Http\Requests\Badges\CancelBadgeOperationRequest;
use App\Http\Requests\Badges\StoreBadgeCollectionItemRequest;
use App\Http\Requests\Badges\StoreBadgeCollectionRoundRequest;
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

        $user = $request->user();
        $circleIds = $access->accessibleCircles($user)->pluck('id');

        $rounds = BadgeCollectionRound::with(['circle', 'teacher'])
            ->withCount('items')
            ->when(!$user->hasRole(['admin', 'general_manager']), function ($q) use ($circleIds, $user) {
                $q->where(fn ($w) => $w->whereIn('circle_id', $circleIds)->orWhere('teacher_id', $user->id));
            })
            ->when($request->filled('circle_id'), fn ($q) => $q->where('circle_id', $request->circle_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('badges.collections.index', [
            'rounds' => $rounds,
            'circles' => $access->accessibleCircles($user)->get(),
        ]);
    }

    public function create(UserAccessService $access, BadgeAccessService $badgeAccess)
    {
        $this->authorize('create', BadgeCollectionRound::class);

        return view('badges.collections.create', [
            'circles' => $access->accessibleCircles(auth()->user())->get(),
            'teachers' => $badgeAccess->selectableTeachers(auth()->user()),
        ]);
    }

    public function store(StoreBadgeCollectionRoundRequest $request, BadgeCollectionService $service)
    {
        $circle = Circle::findOrFail($request->circle_id);

        $round = $service->createRound($circle, (int) $request->teacher_id, $request->collection_date, $request->user(), $request->notes);

        return redirect()->route('badges.collections.show', $round)
            ->with('success', 'تم إنشاء جولة التحصيل بنجاح.');
    }

    public function show(BadgeCollectionRound $collection)
    {
        $this->authorize('view', $collection);

        $collection->load(['circle', 'teacher', 'creator', 'items.student', 'items.badgeType']);

        return view('badges.collections.show', ['round' => $collection]);
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
