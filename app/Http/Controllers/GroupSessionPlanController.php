<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\GroupSessionPlan\StoreGroupSessionPlanRequest;
use App\Http\Requests\GroupSessionPlan\UpdateGroupSessionPlanRequest;
use App\Models\GroupSessionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GroupSessionPlanController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', GroupSessionPlan::class);

        $groupSessionPlans = GroupSessionPlan::with('circle')
            ->when($request->filled('circle_id'), function ($query) use ($request) {
                $query->where('circle_id', $request->circle_id);
            })
            ->orderBy('circle_id')
            ->orderBy('start_time')
            ->paginate($request->integer('per_page', 15));

        // قائمة الحلقات لقائمة التصفية (كانت مفقودة فظهرت القائمة فارغة دائمًا)
        $circles = app(\App\Services\UserAccessService::class)
            ->accessibleCircles($request->user())
            ->get(['id', 'name']);

        return view('group_session_plans.index', compact('groupSessionPlans', 'circles'));
    }

    public function create(): View
    {
        $this->authorize('create', GroupSessionPlan::class);
        // حلقات نطاق وصول المستخدم فقط — لا كل حلقات النظام
        $circles = app(\App\Services\UserAccessService::class)
            ->accessibleCircles(auth()->user())
            ->get(['id', 'name']);
        return view('group_session_plans.create', compact('circles'));
    }

    public function store(StoreGroupSessionPlanRequest $request): RedirectResponse
    {
        $this->authorize('create', GroupSessionPlan::class);

        $validated = $request->validated();
        $this->ensureCircleAccessible((int) $validated['circle_id']);

        $groupSessionPlan = GroupSessionPlan::create($validated);

        return redirect()
            ->route('circles.show', $groupSessionPlan->circle_id)
            ->with('success', 'تم إنشاء جلسة المجموعة بنجاح.');
    }

    public function show(GroupSessionPlan $groupSessionPlan): View
    {
        $this->authorize('view', $groupSessionPlan);
        $groupSessionPlan->load(['circle']);
        return view('group_session_plans.show', compact('groupSessionPlan'));
    }

    public function edit(GroupSessionPlan $groupSessionPlan): View
    {
        $this->authorize('update', $groupSessionPlan);
        $circles = app(\App\Services\UserAccessService::class)
            ->accessibleCircles(auth()->user())
            ->get(['id', 'name']);
        $session = $groupSessionPlan;
        return view('group_session_plans.edit', compact('session', 'circles'));
    }

    public function update(UpdateGroupSessionPlanRequest $request, GroupSessionPlan $groupSessionPlan): RedirectResponse
    {
        $this->authorize('update', $groupSessionPlan);

        $validated = $request->validated();

        // منع نقل الجلسة إلى حلقة خارج نطاق وصول المستخدم
        if (isset($validated['circle_id'])) {
            $this->ensureCircleAccessible((int) $validated['circle_id']);
        }

        $groupSessionPlan->update($validated);

        return redirect()
            ->route('circles.show', $groupSessionPlan->circle_id)
            ->with('success', 'تم تحديث جلسة المجموعة بنجاح.');
    }

    /**
     * الإنشاء/النقل مقصور على حلقات نطاق وصول المستخدم
     * (الأدمن والمدير العام يصلان لكل الحلقات بطبيعة نطاقهما).
     */
    private function ensureCircleAccessible(int $circleId): void
    {
        $user = auth()->user();

        if ($user->hasRole(['admin', 'general_manager'])) {
            return;
        }

        $accessible = app(\App\Services\UserAccessService::class)
            ->accessibleCircles($user)
            ->pluck('id')
            ->contains($circleId);

        abort_unless($accessible, 403);
    }

    public function destroy(GroupSessionPlan $groupSessionPlan): RedirectResponse
    {
        $this->authorize('delete', $groupSessionPlan);

        $circleId = $groupSessionPlan->circle_id;

        $groupSessionPlan->delete();

        return redirect()
            ->route('circles.show', $circleId)
            ->with('success', 'تم حذف جلسة المجموعة بنجاح.');
    }
}
