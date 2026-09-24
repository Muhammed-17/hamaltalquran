<?php

namespace App\Http\Controllers;

use App\Http\Requests\CollectionRound\StoreCollectionRoundRequest;
use App\Http\Requests\CollectionRound\UpdateCollectionRoundRequest;
use App\Http\Requests\CollectionRound\ConfirmCollectionRoundRequest;
use App\Services\CollectionRoundService;
use App\Services\UserAccessService;
use App\Models\CollectionRound;
use App\Models\Center;
use App\Models\Circle;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CollectionRoundController extends Controller
{
    public function __construct(protected UserAccessService $access) {}

    public function create(Request $request)
    {
        $this->authorize('create', CollectionRound::class);

        $circles = $this->access->accessibleCircles(Auth::user())->get();
        $selectedCircleId = $request->get('circle_id');
        $selectedMonth = $request->get('period_month', now()->format('Y-m'));

        // استبعاد الحلقات التي لديها بالفعل التحصيل معلّق لنفس الشهر
        $circles = app(CollectionRoundService::class)->filterCirclesWithoutPendingRound($circles, $selectedMonth);

        $breakdown = collect();
        $previousRounds = collect();
        $nextRoundNumber = 1;

        if ($selectedCircleId) {
            if (! $circles->contains('id', $selectedCircleId)) {
                abort(403, 'ليس لديك صلاحية للوصول لهذه الحلقة.');
            }

            $service = app(CollectionRoundService::class);

            $breakdown = $service->getUncollectedBreakdown($selectedCircleId, $selectedMonth);
            $previousRounds = $service->getPreviousRoundsSummary($selectedCircleId, $selectedMonth);
            $nextRoundNumber = $service->getNextRoundNumber($selectedCircleId, $selectedMonth);
        }

        // قائمة المنشئين المسموح بهم
        $creators = collect();
        if (Auth::user()->hasAnyRole(['admin', 'general_manager'])) {
            $creators = User::whereHas('roles', function ($q) {
                $q->whereIn('name', ['supervisor', 'manager', 'general_manager']);
            })->orderBy('name')->get(['id', 'name']);
        }

        return view('collection_rounds.create', compact(
            'circles',
            'selectedCircleId',
            'selectedMonth',
            'breakdown',
            'previousRounds',
            'nextRoundNumber',
            'creators'
        ));
    }

    public function store(StoreCollectionRoundRequest $request)
    {
        $this->authorize('create', CollectionRound::class);

        $validated = $request->validated();

        if (! $this->access->canAccessCircle(Auth::user(), $validated['circle_id'])) {
            abort(403, 'ليس لديك صلاحية للوصول لهذه الحلقة.');
        }
        try {
            $round = app(CollectionRoundService::class)->storeRound([
                'circle_id'                  => $validated['circle_id'],
                'period_month'               => $validated['period_month'],
                'selected_subscription_ids'  => $validated['selected_subscription_ids'],
                'total_amount'               => $validated['total_amount'],
                'supervisor_note'            => $validated['supervisor_note'] ?? null,
                'created_by'                 => $validated['created_by'] ?? Auth::id(),
            ], Auth::id());
        } catch (\InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors'  => ['selected_subscription_ids' => [$e->getMessage()]],
                ], 422);
            }

            return back()->withInput()->withErrors(['selected_subscription_ids' => $e->getMessage()]);
        }

        $message = 'تم تسجيل التحصيل التحصيل بنجاح (التحصيل رقم ' . $round->round_number . ')';

        if ($request->wantsJson()) {
            return response()->json([
                'redirect_url' => route('collection-rounds.index'),
                'message'      => $message,
                'round'        => $round->only(['id', 'round_number', 'status']),
            ]);
        }

        return redirect()->route('collection-rounds.index')
            ->with('success', $message);
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', CollectionRound::class);

        $user = Auth::user();

        $query = CollectionRound::with(['circle', 'center', 'createdBy', 'confirmedBy', 'logs.createdBy']);

        if (!$user->hasRole(['admin', 'general_manager'])) {
            $query->whereIn('circle_id', $this->access->accessibleCircles($user)->pluck('id'));
        }

        if ($request->filled('branch_id')) {
            $query->whereHas('circle', fn($cq) => $cq->where('branch_id', $request->branch_id));
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('circle', fn($cq) => $cq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('createdBy', fn($uq) => $uq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('confirmedBy', fn($uq) => $uq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('center_id')) {
            $query->where('center_id', $request->center_id);
        }

        if ($request->filled('circle_id')) {
            $query->where('circle_id', $request->circle_id);
        }

        if ($request->filled('period_month')) {
            try {
                $period = Carbon::createFromFormat('Y-m', $request->period_month)->startOfMonth();
                $query->where('period_month', $period);
            } catch (\Exception $e) {
            }
        }

        if ($request->filled('status')) {
            $query->where('collection_rounds.status', $request->status);
        }

        if ($request->filled('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        $stats = (clone $query)->selectRaw("
    COUNT(*) as total_rounds,
    SUM(CASE WHEN collection_rounds.status = 'pending' THEN 1 ELSE 0 END) as total_pending,
    SUM(CASE WHEN collection_rounds.status = 'confirmed' THEN collection_rounds.total_amount ELSE 0 END) as total_confirmed_amount,
    SUM(CASE WHEN collection_rounds.status = 'pending' THEN collection_rounds.total_amount ELSE 0 END) as total_pending_amount
    ")->first();

        $sort = $request->get('sort', 'created_at');
        $direction = $request->get('direction', 'desc') === 'asc' ? 'asc' : 'desc';

        switch ($sort) {
            case 'circle':
                $query->join('circles', 'circles.id', '=', 'collection_rounds.circle_id')
                    ->orderBy('circles.name', $direction)
                    ->select('collection_rounds.*');
                break;
            case 'center':
                $query->join('centers', 'centers.id', '=', 'collection_rounds.center_id')
                    ->orderBy('centers.name', $direction)
                    ->select('collection_rounds.*');
                break;
            case 'round_number':
                $query->orderBy('round_number', $direction);
                break;
            case 'period_month':
                $query->orderBy('period_month', $direction);
                break;
            case 'total_amount':
                $query->orderBy('total_amount', $direction);
                break;
            case 'created_by':
                $query->join('users', 'users.id', '=', 'collection_rounds.created_by')
                    ->orderBy('users.name', $direction)
                    ->select('collection_rounds.*');
                break;
            default:
                $query->orderBy('created_at', $direction);
        }

        $rounds = $query->paginate(20)->withQueryString();

        $filters = $request->only(['circle_id', 'period_month', 'status', 'center_id', 'branch_id', 'created_by', 'search']);

        // ─── Build filter lists with center_id/branch_id info for dynamic filtering ───
        $centers = collect();
        $branches = collect();
        $allCircles = collect();
        $allCreators = collect();
        $circles = collect();
        $creators = collect();

        if ($user->hasRole(['admin', 'general_manager'])) {
            $centers  = Center::orderBy('name')->get(['id', 'name']);
            $branches = \App\Models\Branch::orderBy('name')->get(['id', 'name', 'center_id']);

            $allCircles = Circle::with('branch')->orderBy('name')->get(['id', 'name', 'branch_id'])
                ->map(fn($c) => (object) ['id' => $c->id, 'name' => $c->name, 'branch_id' => $c->branch_id, 'center_id' => $c->branch?->center_id]);
            $circles = $allCircles;

            // ✅ لازم نجيب المستخدمين الأول
            $allCreators = User::whereHas('collectionRounds')
                ->orderBy('name')
                ->get(['id', 'name']);

            $creatorCircleMap = DB::table('collection_rounds')
                ->whereIn('created_by', $allCreators->pluck('id'))
                ->select('created_by', 'circle_id')
                ->distinct()
                ->get()
                ->groupBy('created_by');

            $allCreators = $allCreators->map(function ($u) use ($creatorCircleMap) {
                $u->circle_ids = $creatorCircleMap->get($u->id, collect())->pluck('circle_id')->toArray();
                return $u;
            });
            $creators = $allCreators;
        } else {
            $accessibleCircleIds = $this->access->accessibleCircles($user)->pluck('id');
            $circles = Circle::with('branch')->whereIn('id', $accessibleCircleIds)->orderBy('name')->get(['id', 'name', 'branch_id'])
                ->map(fn($c) => (object) ['id' => $c->id, 'name' => $c->name, 'branch_id' => $c->branch_id, 'center_id' => $c->branch?->center_id]);
            $allCircles = $circles;

            $centerIds = $circles->pluck('center_id')->unique();
            $centers = Center::whereIn('id', $centerIds)->orderBy('name')->get(['id', 'name']);

            $branchIds = $circles->pluck('branch_id')->unique()->filter();
            $branches  = \App\Models\Branch::whereIn('id', $branchIds)->orderBy('name')->get(['id', 'name', 'center_id']);

            $creators = User::whereHas('collectionRounds', fn($q) => $q->whereIn('circle_id', $accessibleCircleIds))
                ->orderBy('name')
                ->get(['id', 'name']);

            $creatorCircleMap = DB::table('collection_rounds')
                ->whereIn('created_by', $creators->pluck('id'))
                ->whereIn('circle_id', $accessibleCircleIds)
                ->select('created_by', 'circle_id')
                ->distinct()
                ->get()
                ->groupBy('created_by');

            $creators = $creators->map(function ($u) use ($creatorCircleMap) {
                $u->circle_ids = $creatorCircleMap->get($u->id, collect())->pluck('circle_id')->toArray();
                return $u;
            });
            $allCreators = $creators;
        }

        $selectedCenterId = $request->get('center_id');
        $selectedBranchId = $request->get('branch_id');
        $selectedCircleId = $request->get('circle_id');
        $selectedCreatorId = $request->get('created_by');
        $search = $request->get('search', '');

        $hasActiveFilters = $request->anyFilled(['center_id', 'branch_id', 'circle_id', 'period_month', 'status', 'created_by', 'search']);

        return view('collection_rounds.index', compact(
            'rounds',
            'stats',
            'filters',
            'centers',
            'branches',
            'circles',
            'creators',
            'allCircles',
            'allCreators',
            'selectedCenterId',
            'selectedBranchId',
            'selectedCircleId',
            'selectedCreatorId',
            'search',
            'hasActiveFilters',
            'sort',
            'direction'
        ));
    }

    /**
     * عرض صفحة تعديل التحصيل معلّق
     */
    public function editRound(CollectionRound $collectionRound)
    {
        $this->authorize('update', $collectionRound);

        $breakdown = app(CollectionRoundService::class)->getEditableBreakdown($collectionRound);

        $selectedSubscriptionIds = $collectionRound->items()
            ->pluck('subscription_id')
            ->values()
            ->toArray();

        // قائمة المنشئين المسموح بهم
        $creators = collect();
        if (Auth::user()->hasAnyRole(['admin', 'general_manager'])) {
            $creators = User::whereHas('roles', function ($q) {
                $q->whereIn('name', ['supervisor', 'manager', 'general_manager']);
            })->orderBy('name')->get(['id', 'name']);
        }

        return view('collection_rounds.edit', compact(
            'collectionRound',
            'breakdown',
            'selectedSubscriptionIds',
            'creators'
        ));
    }

    /**
     * تحديث التحصيل معلّق
     */
    public function updateRound(UpdateCollectionRoundRequest $request, CollectionRound $collectionRound)
    {
        $this->authorize('update', $collectionRound);

        $validated = $request->validated();

        try {
            $updatedRound = app(CollectionRoundService::class)->updateRound(
                $collectionRound,
                $validated,
                Auth::user()   // ← تمرير الـ Model نفسه بدل الـ ID
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['selected_subscription_ids' => $e->getMessage()]);
        }

        return redirect()
            ->route('collection-rounds.index')
            ->with('success', 'تم تحديث التحصيل التحصيل رقم ' . $updatedRound->round_number . ' بنجاح.');
    }

    public function showConfirm(CollectionRound $collectionRound)
    {
        $this->authorize('confirm', $collectionRound);

        $collectionRound->load([
            'circle',
            'center',
            'createdBy',
            'items.subscription.student',
            'items.collectedBySnapshot',
        ]);

        // قائمة المديرين المسموح لهم بالتأكيد — تظهر فقط لحساب admin
        $confirmers = collect();
        if (Auth::user()->hasAnyRole(['admin', 'general_manager'])) {
            $confirmers = User::whereHas('roles', function ($q) {
                $q->whereIn('name', ['manager', 'general_manager']);
            })->orderBy('name')->get(['id', 'name']);
        }

        $view = view('collection_rounds.confirm', compact('collectionRound', 'confirmers'));

        return response($view)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function confirmRound(ConfirmCollectionRoundRequest $request, CollectionRound $collectionRound)
    {
        $this->authorize('confirm', $collectionRound);

        if ($collectionRound->status !== 'pending') {
            return back()->withErrors(['status' => 'هذه الالتحصيل ليست في حالة تسمح بالتأكيد.']);
        }

        $validated = $request->validated();

        $confirmedBy = $validated['confirmed_by'] ?? Auth::id();

        $collectionRound->update([
            'status'       => 'confirmed',
            'confirmed_by' => $confirmedBy,
            'confirmed_at' => now(),
        ]);

        return redirect()
            ->route('collection-rounds.index')
            ->with('success', 'تم تأكيد التحصيل التحصيل رقم ' . $collectionRound->round_number . ' بنجاح.');
    }

    public function addManagerNote(Request $request, CollectionRound $collectionRound)
    {
        $this->authorize('confirm', $collectionRound);

        $request->validate([
            'manager_note' => 'required|string|max:1000',
        ]);

        if ($collectionRound->status !== 'pending') {
            return back()->withErrors(['status' => 'لا يمكن إضافة ملاحظة على التحصيل ليست معلّق.']);
        }

        $collectionRound->update([
            'manager_note'           => $request->manager_note,
            'manager_note_addressed'   => false,
        ]);

        return redirect()
            ->route('collection-rounds.index')
            ->with('warning', 'تم إضافة ملاحظة مراجعة على الالتحصيل رقم ' . $collectionRound->round_number . '.');
    }

    public function getFilterOptions(Request $request)
    {
        $this->authorize('viewAny', CollectionRound::class);

        $user = Auth::user();
        $centerId = $request->get('center_id');
        $circleId = $request->get('circle_id');

        $accessibleCircleIds = $this->access->accessibleCircles($user)->pluck('id');

        $circlesQuery = Circle::with('branch')->whereIn('id', $accessibleCircleIds)->orderBy('name');
        if ($centerId) {
            $circlesQuery->whereHas('branch', fn($bq) => $bq->where('center_id', $centerId));
        }

        $centersQuery = Center::orderBy('name');
        if ($circleId) {
            $circle = Circle::find($circleId);
            if ($circle) {
                $centersQuery->where('id', $circle->center_id); // عبر accessor — property access آمن
            }
        }

        return response()->json([
            'centers' => $centersQuery->get(['id', 'name']),
            'circles' => $circlesQuery->get(['id', 'name', 'branch_id'])
                ->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'center_id' => $c->branch?->center_id]),
        ]);
    }

    public function getBreakdown(Request $request)
    {
        $this->authorize('create', CollectionRound::class);

        $validated = $request->validate([
            'circle_id'     => 'required|exists:circles,id',
            'period_month'  => 'required|date_format:Y-m',
        ]);

        if (! $this->access->canAccessCircle(Auth::user(), $validated['circle_id'])) {
            abort(403, 'ليس لديك صلاحية للوصول لهذه الحلقة.');
        }

        $service = app(CollectionRoundService::class);

        return response()->json([
            'breakdown'         => $service->getUncollectedBreakdown($validated['circle_id'], $validated['period_month']),
            'previous_rounds'   => $service->getPreviousRoundsSummary($validated['circle_id'], $validated['period_month']),
            'next_round_number' => $service->getNextRoundNumber($validated['circle_id'], $validated['period_month']),
        ]);
    }

    /**
     * حذف التحصيل بالكامل (حصريًا للإدارة)
     */
    public function destroyRound(CollectionRound $collectionRound)
    {
        $this->authorize('delete', $collectionRound);

        app(CollectionRoundService::class)->destroyRound($collectionRound);

        if (request()->wantsJson()) {
            return response()->json([
                'redirect_url' => route('collection-rounds.index'),
                'message'      => 'تم حذف التحصيل بنجاح، وتم تحرير الاشتراكات المرتبطة بها لتصبح متاحة لتحصيلات جديدة.',
            ]);
        }

        return redirect()
            ->route('collection-rounds.index')
            ->with('success', 'تم حذف التحصيل بنجاح، وتم تحرير الاشتراكات المرتبطة بها لتصبح متاحة لتحصيلات جديدة.');
    }

    /**
     * جلب قائمة الحلقات المتاحة (بدون التحصيل معلّق) لشهر معيّن — يُستخدم عند تغيير الشهر ديناميكيًا
     */
    public function getAvailableCircles(Request $request)
    {
        $this->authorize('create', CollectionRound::class);

        $validated = $request->validate([
            'period_month' => 'required|date_format:Y-m',
        ]);

        $circles = $this->access->accessibleCircles(Auth::user())->get();
        $circles = app(CollectionRoundService::class)->filterCirclesWithoutPendingRound($circles, $validated['period_month']);

        return response()->json([
            'circles' => $circles->map(fn($c) => ['value' => $c->id, 'label' => $c->name])->values(),
        ]);
    }
    /**
     * جلب الحلقات المتاحة لمستخدم معين (للـ dropdown الديناميكي)
     */
    public function getAvailableCirclesForUser(Request $request)
    {
        $this->authorize('create', CollectionRound::class);

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'period_month' => 'required|date_format:Y-m',
        ]);

        $user = User::findOrFail($validated['user_id']);

        $circles = $this->access->accessibleCircles($user)->get();
        $circles = app(CollectionRoundService::class)->filterCirclesWithoutPendingRound($circles, $validated['period_month']);

        return response()->json([
            'circles' => $circles->map(fn($c) => ['value' => $c->id, 'label' => $c->name])->values(),
        ]);
    }
}
