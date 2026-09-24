<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\Student;
use App\Models\User;
use App\Models\Center;
use App\Models\Circle;
use App\Models\CircleAssignmentHistory;
use App\Jobs\CalculateUnpaidMonths;
use App\Http\Requests\Subscription\StoreSubscriptionRequest;
use App\Http\Requests\Subscription\UpdateSubscriptionRequest;
use App\Services\UserAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Notifications\UnpaidSubscriptionNotification;
use Illuminate\Support\Facades\RateLimiter;

class SubscriptionController extends Controller
{
    public function __construct(protected UserAccessService $access) {}

    // ─────────────────────────────────────────
    public function index(Request $request)
    {
        $this->authorize('viewAny', Subscription::class);

        $user           = Auth::user();
        $selectedMonth  = $request->get('month');
        $monthStart     = $selectedMonth
            ? Carbon::createFromFormat('Y-m', $selectedMonth)->startOfMonth()->format('Y-m-d')
            : now()->startOfMonth()->format('Y-m-d');
        $selectedStatus = $request->get('status');
        $search         = $request->get('search');
        $statsMonth     = $selectedMonth ?? now()->format('Y-m');

        $circles   = $this->access->accessibleCircles($user)->get();
        $circleIds = $circles->pluck('id');

        $selectedCircleId = $request->get('circle_id');
        if ($selectedCircleId && !$circleIds->contains($selectedCircleId)) {
            abort(403, 'ليس لديك صلاحية لعرض هذه الحلقة.');
        }

        if (!$selectedCircleId && $circles->count() === 1) {
            $selectedCircleId = $circles->first()->id;
        }

        $selectedCenterId    = $request->get('center_id');
        $selectedBranchId    = $request->get('branch_id');
        $selectedTeacherId   = $request->get('teacher_id');
        $selectedCollectedById = $request->get('collected_by_id');

        // ─── قائمة المعلمين لفلتر "المعلم" ───────────────────────────
        $teachers = collect();
        if ($user->can('view subscriptions')) {
            $teachers = User::whereHas('roles', function ($q) {
                $q->whereIn('name', ['supervisor', 'manager', 'general_manager', 'teacher']);
            })->orderBy('name')->get(['id', 'name']);
        }

        // ─── قائمة المحصِّلين حسب الدور ✅ ───────────────────────────
        $collectedByUsers = $this->buildCollectedByUsers($user);

        $statsBaseQuery = Subscription::query();

        // ✅ فلتر المعلم (مين سجّل)
        if ($selectedTeacherId) {
            $statsBaseQuery->where('teacher_id', $selectedTeacherId);
        }

        // ✅ فلتر المحصِّل (مين قبض) — مستقل تماماً
        if ($selectedCollectedById) {
            $statsBaseQuery->where('collected_by', $selectedCollectedById);
        }

        $isGuardian = $user->can('view own subscriptions') && !$user->can('view subscriptions') && $user->hasRole('guardian');

        if ($isGuardian) {
            $statsBaseQuery->whereIn('student_id', $user->students()->pluck('id'));
        } else {
            $this->access->applyScopeByCircleIds($statsBaseQuery, 'subscriptions', $circleIds);
        }

        if ($selectedCircleId) {
            $statsBaseQuery->where('circle_id', $selectedCircleId);
        }

        if ($selectedCenterId) {
            $statsBaseQuery->whereHas('circle.branch', function ($q) use ($selectedCenterId) {
                $q->where('center_id', $selectedCenterId);
            });
        }

        if ($selectedBranchId) {
            $statsBaseQuery->whereHas('circle', function ($q) use ($selectedBranchId) {
                $q->where('branch_id', $selectedBranchId);
            });
        }

        $monthlyRevenue          = collect();
        $monthlyPaymentStats     = collect();
        $monthlyCollected        = 0;
        $paymentRate             = 0;
        $paidAndExemptRate       = 0;
        $unpaidAmount            = 0;
        $dueMonthRevenue         = 0;
        $paidMonthCount          = 0;
        $collectedForOtherMonths = 0;
        $paidOrExemptCount       = 0;
        $totalActiveStudents     = 0;
        $paidOnlyCount           = 0;
        $exemptOnlyCount         = 0;

        $isScopedByCircles = !$isGuardian && !$user->hasRole(['admin', 'general_manager']);

        if ($user->can('view subscriptions')) {

            $studentsQuery = Student::withoutGlobalScopes()
                ->where('status', 'مقيد');

            if ($isGuardian) {
                $studentsQuery->where('guardian_id', $user->id);
            } else {
                $monthEndForCheck = Carbon::parse($monthStart)->endOfMonth();

                $relevantStudentIds = \App\Models\CircleAssignmentHistory::studentIdsInCirclesAt(
                    $circleIds,
                    $monthEndForCheck
                );
                $studentsQuery->whereIn('id', $relevantStudentIds);

                if ($isScopedByCircles) {
                    $statsBaseQuery->whereIn('student_id', $relevantStudentIds);
                }
            }

            if ($selectedCircleId) {
                $studentsQuery->where('circle_id', $selectedCircleId);
            }

            if ($selectedCenterId) {
                $studentsQuery->whereHas('circle.branch', function ($q) use ($selectedCenterId) {
                    $q->where('center_id', $selectedCenterId);
                });
            }

            if ($selectedBranchId) {
                $studentsQuery->whereHas('circle', function ($q) use ($selectedBranchId) {
                    $q->where('branch_id', $selectedBranchId);
                });
            }

            if ($selectedTeacherId) {
                $studentsQuery->whereHas('circle.teachers', function ($q) use ($selectedTeacherId) {
                    $q->whereHas('user', function ($uq) use ($selectedTeacherId) {
                        $uq->where('id', $selectedTeacherId);
                    });
                });
            }

            $activeStudentIds    = (clone $studentsQuery)->pluck('id');
            $totalActiveStudents = $activeStudentIds->count();

            $aggregatedStats = (clone $statsBaseQuery)
                ->selectRaw("
        SUM(CASE WHEN month = ? AND status = 'مدفوع' THEN amount ELSE 0 END) as due_month_revenue,
        SUM(CASE WHEN month != ? AND status = 'مدفوع' AND DATE_FORMAT(paid_at, '%Y-%m') = ? THEN amount ELSE 0 END) as collected_for_other_months,
        SUM(CASE WHEN status = 'مدفوع' AND paid_at IS NOT NULL AND DATE_FORMAT(paid_at, '%Y-%m') = ? THEN 1 ELSE 0 END) as paid_month_count
    ", [$monthStart, $monthStart, $statsMonth, $statsMonth])
                ->first();

            $dueMonthRevenue         = (float) ($aggregatedStats->due_month_revenue ?? 0);
            $collectedForOtherMonths = (float) ($aggregatedStats->collected_for_other_months ?? 0);
            $paidMonthCount          = (int) ($aggregatedStats->paid_month_count ?? 0);
            $monthlyCollected        = $dueMonthRevenue + $collectedForOtherMonths;

            // ✅ ده لسه query منفصل، لكن استخدام whereIn() آمن بدل ما تحقن IDs في raw string
            $paidExemptStats = (clone $statsBaseQuery)
                ->where('month', $monthStart)
                ->whereIn('student_id', $activeStudentIds)
                ->selectRaw("
        SUM(CASE WHEN status = 'مدفوع' THEN 1 ELSE 0 END) as paid_only_count,
        SUM(CASE WHEN status = 'معفي' THEN 1 ELSE 0 END) as exempt_only_count
    ")
                ->first();

            $paidOnlyCount     = (int) ($paidExemptStats->paid_only_count ?? 0);
            $exemptOnlyCount   = (int) ($paidExemptStats->exempt_only_count ?? 0);
            $paidOrExemptCount = $paidOnlyCount + $exemptOnlyCount;

            $paymentRate = $totalActiveStudents > 0
                ? round(($paidOnlyCount / $totalActiveStudents) * 100, 1)
                : 0;

            $paidAndExemptRate = $totalActiveStudents > 0
                ? round(($paidOrExemptCount / $totalActiveStudents) * 100, 1)
                : 0;

            $prices   = \App\Models\SubscriptionPrice::all();
            $priceMap = [];
            foreach ($prices as $p) {
                $priceMap[$p->circle_level . '|' . $p->education_stage] = (float) $p->amount;
            }

            $paidSubscriptionsMap = (clone $statsBaseQuery)
                ->where('month', $monthStart)
                ->where('status', 'مدفوع')
                ->pluck('amount', 'student_id');

            (clone $studentsQuery)
                ->with('circle:id,level')
                ->select('id', 'circle_id', 'educational_stage')
                ->chunk(500, function ($chunk) use (&$unpaidAmount, $priceMap, $paidSubscriptionsMap) {
                    foreach ($chunk as $student) {
                        $key      = ($student->circle?->level) . '|' . $student->educational_stage;
                        $expected = $priceMap[$key] ?? 60.00;
                        $paid     = $paidSubscriptionsMap[$student->id] ?? 0;
                        $unpaidAmount += max(0, $expected - $paid);
                    }
                });

            if ($user->can('view subscriptions chart')) {

                $monthlyRevenue = (clone $statsBaseQuery)
                    ->selectRaw("DATE_FORMAT(paid_at, '%Y-%m') as month_label, SUM(amount) as total")
                    ->where('status', 'مدفوع')
                    ->whereNotNull('paid_at')
                    ->groupBy('month_label')
                    ->orderBy('month_label', 'desc')
                    ->take(6)
                    ->get()
                    ->sortBy('month_label')
                    ->values();

                $monthlyPaymentStats = collect();
                $last6MonthsStarts   = collect(range(5, 0))->map(
                    fn($i) => Carbon::parse($monthStart)->subMonths($i)->startOfMonth()
                );

                $paidCountsByMonth = (clone $statsBaseQuery)
                    ->whereIn('month', $last6MonthsStarts->map(fn($d) => $d->format('Y-m-d')))
                    ->where('status', 'مدفوع')
                    ->selectRaw("DATE_FORMAT(month, '%Y-%m') as month_label, COUNT(*) as cnt")
                    ->groupBy('month_label')
                    ->pluck('cnt', 'month_label');

                $exemptCountsByMonth = (clone $statsBaseQuery)
                    ->whereIn('month', $last6MonthsStarts->map(fn($d) => $d->format('Y-m-d')))
                    ->where('status', 'معفي')
                    ->selectRaw("DATE_FORMAT(month, '%Y-%m') as month_label, COUNT(*) as cnt")
                    ->groupBy('month_label')
                    ->pluck('cnt', 'month_label');

                $studentsForStatsQuery = Student::withoutGlobalScopes()
                    ->where('status', 'مقيد')
                    ->select('id', 'join_date', 'created_at');

                if ($isGuardian) {
                    $studentsForStatsQuery->where('guardian_id', $user->id);
                } elseif (!$isScopedByCircles) {
                    $studentsForStatsQuery->whereIn('circle_id', $circleIds);
                }

                if ($selectedCircleId) {
                    $studentsForStatsQuery->where('circle_id', $selectedCircleId);
                }

                if ($selectedCenterId) {
                    $studentsForStatsQuery->whereHas('circle.branch', function ($q) use ($selectedCenterId) {
                        $q->where('center_id', $selectedCenterId);
                    });
                }

                if ($selectedBranchId) {
                    $studentsForStatsQuery->whereHas('circle', function ($q) use ($selectedBranchId) {
                        $q->where('branch_id', $selectedBranchId);
                    });
                }

                if ($selectedTeacherId) {
                    $studentsForStatsQuery->whereHas('circle.teachers', function ($q) use ($selectedTeacherId) {
                        $q->whereHas('user', function ($uq) use ($selectedTeacherId) {
                            $uq->where('id', $selectedTeacherId);
                        });
                    });
                }

                $studentsForStats = $studentsForStatsQuery->get();

                $historicalStudentIdsByMonth = $isScopedByCircles
                    ? \App\Models\CircleAssignmentHistory::studentIdsInCirclesAtMultipleDates($circleIds, $last6MonthsStarts)
                    : [];

                // ✅ تحويل كل قائمة شهرية لـ lookup set بحث O(1) بدل in_array() اللي كانت O(k)
                $historicalLookupByMonth = [];
                foreach ($historicalStudentIdsByMonth as $monthLabel => $ids) {
                    $historicalLookupByMonth[$monthLabel] = array_flip($ids);
                }

                // ✅ حساب join_date مرة واحدة لكل طالب بدل تكراره 6 مرات جوه الفلتر
                $studentJoinDates = $studentsForStats->map(function ($student) {
                    return [
                        'id'        => $student->id,
                        'join_date' => $student->join_date ?? $student->created_at,
                    ];
                });

                foreach ($last6MonthsStarts as $monthDate) {
                    $monthLabel  = $monthDate->format('Y-m');
                    $monthEndCut = $monthDate->copy()->endOfMonth();
                    $monthLookup = $historicalLookupByMonth[$monthLabel] ?? [];

                    $totalCount = $studentJoinDates->filter(function ($item) use ($monthEndCut, $monthLookup, $isScopedByCircles) {
                        $joinedBeforeCutoff = $item['join_date'] <= $monthEndCut;

                        if ($isScopedByCircles) {
                            return $joinedBeforeCutoff && isset($monthLookup[$item['id']]);
                        }

                        return $joinedBeforeCutoff;
                    })->count();

                    $paidCount   = $paidCountsByMonth[$monthLabel] ?? 0;
                    $exemptCount = $exemptCountsByMonth[$monthLabel] ?? 0;

                    $monthlyPaymentStats->push([
                        'month_label'  => $monthLabel,
                        'paid_count'   => $paidCount,
                        'exempt_count' => $exemptCount,
                        'unpaid_count' => max(0, $totalCount - $paidCount - $exemptCount),
                        'total_count'  => $totalCount,
                        'rate'         => $totalCount > 0 ? round(($paidCount / $totalCount) * 100, 1) : 0,
                    ]);
                }
            }
        }

        $recentSubscriptionsQuery = (clone $statsBaseQuery)
            ->with([
                'student' => function ($query) {
                    $query->withoutGlobalScopes();
                },
                'circle.branch.center',
                'collectedBy.roles',
                'teacher',
                'collectionRoundItem.collectionRound',
            ]);

        if ($request->filled('month')) {
            $recentSubscriptionsQuery->where('month', $monthStart);
        }

        if ($search && $user->can('view subscriptions')) {
            $recentSubscriptionsQuery->whereHas('student', function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%');
            });
        }

        if ($selectedStatus) {
            $recentSubscriptionsQuery->where('status', $selectedStatus);
        }

        $sort      = $request->get('sort', 'paid_at');
        $direction = $request->get('direction', 'desc') === 'asc' ? 'asc' : 'desc';

        switch ($sort) {
            case 'student':
                $recentSubscriptionsQuery
                    ->join('students', 'students.id', '=', 'subscriptions.student_id')
                    ->orderBy('students.name', $direction)
                    ->select('subscriptions.*');
                break;
            case 'circle':
                $recentSubscriptionsQuery
                    ->join('circles', 'circles.id', '=', 'subscriptions.circle_id')
                    ->orderBy('circles.name', $direction)
                    ->select('subscriptions.*');
                break;
            case 'center':
                $recentSubscriptionsQuery
                    ->join('circles', 'circles.id', '=', 'subscriptions.circle_id')
                    ->join('branches', 'branches.id', '=', 'circles.branch_id')
                    ->join('centers', 'centers.id', '=', 'branches.center_id')
                    ->orderBy('centers.name', $direction)
                    ->select('subscriptions.*');
                break;
            case 'branch':
                $recentSubscriptionsQuery
                    ->join('circles', 'circles.id', '=', 'subscriptions.circle_id')
                    ->join('branches', 'branches.id', '=', 'circles.branch_id')
                    ->orderBy('branches.name', $direction)
                    ->select('subscriptions.*');
                break;
            case 'month':
                $recentSubscriptionsQuery->orderBy('month', $direction);
                break;
            case 'amount':
                $recentSubscriptionsQuery->orderBy('amount', $direction);
                break;
            case 'status':
                $recentSubscriptionsQuery->orderBy('status', $direction);
                break;
            case 'payment_method':
                $recentSubscriptionsQuery->orderBy('payment_method', $direction);
                break;
            case 'paid_at':
                $recentSubscriptionsQuery->orderBy('paid_at', $direction);
                break;
            default:
                $recentSubscriptionsQuery->orderByRaw('COALESCE(paid_at, created_at) ' . $direction);
        }

        $recentSubscriptions = $recentSubscriptionsQuery
            ->paginate(20)
            ->withQueryString();

        $centers = collect();
        if ($user->can('view subscriptions')) {
            $centers = Center::orderBy('name')->get(['id', 'name']);
        } else {
            $centerIds = $circles->pluck('center_id')->unique()->filter();
            $centers   = Center::whereIn('id', $centerIds)->orderBy('name')->get(['id', 'name']);
        }

        // ✅ قائمة المقرات (الفروع) لفلتر "المقر"
        $branches = collect();
        if ($user->hasRole(['admin', 'general_manager'])) {
            $branchesQuery = \App\Models\Branch::orderBy('name');
            if ($selectedCenterId) {
                $branchesQuery->where('center_id', $selectedCenterId);
            }
            $branches = $branchesQuery->get(['id', 'name', 'center_id']);
        } elseif ($user->hasRole('manager')) {
            $managerTeacher   = $this->access->teacher($user);
            $managerCenterId  = $this->access->teacherCenterId($managerTeacher);
            if ($managerCenterId) {
                $branches = \App\Models\Branch::where('center_id', $managerCenterId)
                    ->orderBy('name')
                    ->get(['id', 'name', 'center_id']);
            }
        }

        // ✅ إضافة collected_by_id و branch_id لـ hasActiveFilters
        $hasActiveFilters = $request->anyFilled(['center_id', 'branch_id', 'circle_id', 'status', 'teacher_id', 'collected_by_id', 'search'])
            || ($request->filled('month') && $request->get('month') !== now()->format('Y-m'));

        return view('subscriptions.index', compact(
            'monthlyRevenue',
            'monthlyPaymentStats',
            'monthlyCollected',
            'recentSubscriptions',
            'circles',
            'selectedCircleId',
            'selectedMonth',
            'paymentRate',
            'paidAndExemptRate',
            'unpaidAmount',
            'search',
            'selectedStatus',
            'centers',
            'selectedCenterId',
            'branches',
            'selectedBranchId',
            'teachers',
            'selectedTeacherId',
            'selectedCollectedById',
            'collectedByUsers',
            'dueMonthRevenue',
            'paidMonthCount',
            'collectedForOtherMonths',
            'hasActiveFilters',
            'paidOrExemptCount',
            'totalActiveStudents',
            'paidOnlyCount',
            'exemptOnlyCount',
            'statsMonth',
            'sort',
            'direction',
        ));
    }
    public function create()
    {
        $this->authorize('create subscriptions');

        $user      = Auth::user();
        $circles   = $this->access->accessibleCircles($user)->get();
        $circleIds = $circles->pluck('id');

        $students = Student::withoutGlobalScopes()
            ->with(['circle', 'subscriptions'])
            ->where('status', 'مقيد')
            ->whereIn('circle_id', $circleIds)
            ->get();

        $prices = \App\Models\SubscriptionPrice::all();

        $teachers = collect();
        if ($user->hasRole(['admin', 'general_manager'])) {
            $teachers = User::whereHas('roles', function ($q) {
                $q->whereIn('name', ['supervisor', 'manager', 'general_manager', 'teacher']);
            })->with('roles')->get(['id', 'name']);
        }

        $collectedByUsers = $this->buildCollectedByUsers($user);

        // ✅ الطلاب المعفيون هذا الشهر (لتمييزهم بصريًا في الفورم)
        $currentMonthStart = now()->startOfMonth()->format('Y-m-d');
        $exemptedStudentIds = Subscription::where('month', $currentMonthStart)
            ->where('status', 'معفي')
            ->whereIn('student_id', $students->pluck('id'))
            ->pluck('student_id')
            ->toArray();

        return view('subscriptions.create', compact(
            'circles',
            'students',
            'prices',
            'teachers',
            'collectedByUsers',
            'exemptedStudentIds' // ✅ جديد
        ));
    }

    public function store(StoreSubscriptionRequest $request)
    {
        $this->authorize('create subscriptions');

        $validated = $request->validated();
        $user      = Auth::user();
        $month = Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth()->format('Y-m-d');
        $isExempt  = $validated['status'] === 'معفي';
        $isUnpaid  = $validated['status'] === 'غير مدفوع';

        $circleIds = $this->access->accessibleCircles($user)->pluck('id');
        if (!$circleIds->contains($validated['circle_id'])) {
            abort(403, 'ليس لديك صلاحية لإضافة اشتراك لهذه الحلقة.');
        }

        $teacherId = $user->hasRole(['admin', 'general_manager'])
            ? ($validated['teacher_id'] ?? $user->id)
            : $user->id;

        $data = [
            'student_id'     => $validated['student_id'],
            'circle_id'      => $validated['circle_id'],
            'teacher_id'     => $teacherId,
            'month'          => $month,
            'status'         => $validated['status'],
            'amount'         => ($isExempt || $isUnpaid) ? 0 : $validated['amount'],
            'payment_method' => ($isExempt || $isUnpaid) ? null : ($validated['payment_method'] ?? null),
            'paid_at'      => $validated['status'] === 'مدفوع' ? now() : null,
            'collected_by' => $validated['status'] === 'مدفوع' ? ($validated['collected_by'] ?? $teacherId) : null,
            'notes'          => $validated['notes'] ?? null,
        ];

        // ✅ تحقق: هل يوجد بالفعل سجل اشتراك لنفس الطالب ونفس الشهر؟
        // (حالة شائعة: طالب معفي دفع استثنائيًا هذا الشهر — يجب تحديث سجله لا إنشاء سجل مكرر)
        $existingSubscription = Subscription::where('student_id', $validated['student_id'])
            ->where('month', $month)
            ->first();

        if ($existingSubscription) {
            $collectionRoundItem = $existingSubscription->collectionRoundItem;
            $isLockedByConfirmedRound = $collectionRoundItem && $collectionRoundItem->collectionRound?->status === 'confirmed';

            if ($isLockedByConfirmedRound) {
                return back()->with(
                    'error',
                    'يوجد سجل اشتراك سابق لهذا الطالب في نفس الشهر وهو مرتبط بجولة تحصيل مؤكَّدة، ولا يمكن تعديله.'
                );
            }

            $existingSubscription->update($data);
            CalculateUnpaidMonths::dispatch($validated['student_id']);

            return redirect()->route('subscriptions.index')
                ->with('success', 'تم العثور على سجل اشتراك سابق لهذا الطالب في نفس الشهر، وتم تحديثه بدلاً من إنشاء سجل مكرر.');
        }

        Subscription::create($data);
        CalculateUnpaidMonths::dispatch($data['student_id']);
        return redirect()->route('subscriptions.index')
            ->with('success', 'تم تسجيل الاشتراك بنجاح');
    }

    // ─── 2. lateAndUnpaid() - الدالة كاملة ──────────────────────────────
    public function lateAndUnpaid(Request $request)
    {
        $query = Student::query()
            ->with(['circle.branch.center'])
            ->whereIn('status', ['مقيد', 'متوقف'])
            ->whereHas('unpaidMonths', fn($q) => $q->where('unpaid_months_count', '>', 0))
            ->join('student_unpaid_months', 'students.id', '=', 'student_unpaid_months.student_id')
            ->select('students.*', 'student_unpaid_months.unpaid_months_count');

        if ($request->search) {
            $query->where('students.name', 'like', "%{$request->search}%");
        }
        if ($request->status) {
            $query->where('students.status', $request->status);
        }
        if ($request->circle_id) {
            $query->where('students.circle_id', $request->circle_id);
        } else {
            if ($request->center_id) {
                $query->whereHas('circle.branch', fn($q) => $q->where('center_id', $request->center_id));
            }
            if ($request->branch_id) {
                $query->whereHas('circle', fn($q) => $q->where('branch_id', $request->branch_id));
            }
        }
        if ($request->teacher_id) {
            $query->whereHas('circle.teachers', fn($q) => $q->where('teachers.id', $request->teacher_id));
        }

        // ✅ حساب total/sum قبل إضافة أي JOIN إضافي خاص بالـ sorting، لتفادي تضاعف الصفوف بسبب join جديد
        $totalStudents     = (clone $query)->count();
        $totalUnpaidMonths = (clone $query)->sum('student_unpaid_months.unpaid_months_count');

        // ─── Sorting في DB مباشرة (JOIN بدل correlated subquery) ─────────────────
        $sort      = $request->input('sort', 'unpaid_months');
        $direction = $request->input('direction', 'desc');

        match ($sort) {
            'name'   => $query->orderBy('students.name', $direction),
            'status' => $query->orderBy('students.status', $direction),
            'circle' => $query
                ->join('circles', 'circles.id', '=', 'students.circle_id')
                ->orderBy('circles.name', $direction)
                ->select('students.*', 'student_unpaid_months.unpaid_months_count'),
            'center' => $query
                ->join('circles', 'circles.id', '=', 'students.circle_id')
                ->join('branches', 'branches.id', '=', 'circles.branch_id')
                ->join('centers', 'centers.id', '=', 'branches.center_id')
                ->orderBy('centers.name', $direction)
                ->select('students.*', 'student_unpaid_months.unpaid_months_count'),
            'branch' => $query
                ->join('circles', 'circles.id', '=', 'students.circle_id')
                ->join('branches', 'branches.id', '=', 'circles.branch_id')
                ->orderBy('branches.name', $direction)
                ->select('students.*', 'student_unpaid_months.unpaid_months_count'),
            default  => $query->orderBy('student_unpaid_months.unpaid_months_count', $direction),
        };

        $students = $query->paginate(20)->withQueryString();

        $search            = $request->input('search');
        $selectedStatus    = $request->input('status');
        $selectedCenterId  = $request->input('center_id');
        $selectedBranchId  = $request->input('branch_id');
        $selectedCircleId  = $request->input('circle_id');
        $selectedTeacherId = $request->input('teacher_id');

        $centers  = Center::orderBy('name')->get();

         // ✅ قائمة المقرات — Cascading حسب المركز المختار (لو فيه)
        $branches = \App\Models\Branch::when($selectedCenterId, fn($q) => $q->where('center_id', $selectedCenterId))
            ->orderBy('name')->get(['id', 'name', 'center_id']);

        $circles  = Circle::when($selectedCenterId, fn($q) => $q->whereHas('branch', fn($bq) => $bq->where('center_id', $selectedCenterId)))
            ->when($selectedBranchId, fn($q) => $q->where('branch_id', $selectedBranchId))
            ->orderBy('name')->get();

        $teachers = \App\Models\Teacher::with('user:id,name')
            ->get()
            ->sortBy('user.name')
            ->values();

        $hasActiveFilters = $request->anyFilled(['search', 'status', 'center_id', 'branch_id', 'circle_id', 'teacher_id']);

        return view('subscriptions.late_and_unpaid', compact(
            'students',
            'totalStudents',
            'totalUnpaidMonths',
            'centers',
            'branches',
            'circles',
            'teachers',
            'search',
            'selectedStatus',
            'selectedCenterId',
            'selectedBranchId',
            'selectedCircleId',
            'selectedTeacherId',
            'hasActiveFilters',
        ));
    }

    public function DetailsUnpaid(Student $student)
    {
        $user = Auth::user();

        if (!$user->can('view subscriptions')) {
            abort(403);
        }

        if (!$user->hasRole(['admin', 'general_manager'])) {
            $accessibleCircleIds = $this->access->accessibleCircles($user)->pluck('id');
            if (!$accessibleCircleIds->contains($student->circle_id)) {
                abort(403);
            }
        }

        $enrolledMonths = $this->buildEnrolledMonths($student)->sort()->values();

        $subscriptions = $student->subscriptions()
            ->select('month', 'status', 'paid_at')
            ->get();

        $paidSet   = array_flip(
            $subscriptions->where('status', 'مدفوع')
                ->map(fn($s) => Carbon::parse($s->month)->format('Y-m'))
                ->all()
        );

        $exemptSet = array_flip(
            $subscriptions->where('status', 'معفي')
                ->map(fn($s) => Carbon::parse($s->month)->format('Y-m'))
                ->all()
        );

        $timeline = $enrolledMonths->map(function ($monthStr) use ($paidSet, $exemptSet) {
            $date     = Carbon::createFromFormat('Y-m', $monthStr);
            $isPaid   = isset($paidSet[$monthStr]);
            $isExempt = isset($exemptSet[$monthStr]);
            $isUnpaid = !$isPaid && !$isExempt;

            return [
                'month_str'    => $monthStr,
                'month_label'  => $date->locale('ar')->isoFormat('MMMM YYYY'),
                'is_paid'      => $isPaid,
                'is_exempt'    => $isExempt,
                'is_unpaid'    => $isUnpaid,
                'days_overdue' => $isUnpaid ? now()->diffInDays($date->copy()->endOfMonth()) : 0,
            ];
        })->sortByDesc('month_str')->values()->toArray();

        $paidCount   = collect($timeline)->where('is_paid', true)->count();
        $exemptCount = collect($timeline)->where('is_exempt', true)->count();
        $unpaidCount = collect($timeline)->where('is_unpaid', true)->count();
        $totalMonths = count($timeline);

        $chartData = [
            'labels' => ['مدفوع', 'معفي', 'غير مدفوع'],
            'data'   => [$paidCount, $exemptCount, $unpaidCount],
            'colors' => ['#10b981', '#3b82f6', '#ef4444'],
        ];

        return view('subscriptions.details_unpaid', compact(
            'student',
            'timeline',
            'chartData',
            'totalMonths',
            'paidCount',
            'exemptCount',
            'unpaidCount'
        ));
    }

    // ─────────────────────────────────────────
    // Notify guardian about unpaid subscription
    // ─────────────────────────────────────────
    public function notifyUnpaid(Student $student, Request $request)
    {
        $this->authorize('notifyUnpaid', $student);

        $validated = $request->validate([
            'message' => 'nullable|string|max:1000',
        ]);

        $key = 'notify-unpaid:' . Auth::id();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json(['message' => "يرجى الانتظار {$seconds} ثانية قبل إرسال تنبيه آخر."], 429);
        }
        RateLimiter::hit($key, 60);

        $guardian = $student->guardian;
        if (!$guardian) {
            return response()->json(['message' => 'لا يوجد ولي أمر مرتبط بهذا الطالب.'], 422);
        }

        $alreadyNotified = $guardian->notifications()
            ->where('type', UnpaidSubscriptionNotification::class)
            ->whereDate('created_at', today())
            ->where('data', 'like', '%"student_id":' . $student->id . '%')
            ->exists();

        if ($alreadyNotified) {
            return response()->json(['message' => 'تم إرسال تنبيه بالفعل اليوم لهذا الطالب.'], 409);
        }

        $unpaidMonthsCount = $student->unpaidMonths?->unpaid_months_count ?? 0;

        $message = $validated['message'] ? strip_tags($validated['message']) : null;

        $guardian->notify(new UnpaidSubscriptionNotification($student, $unpaidMonthsCount, $message));

        return response()->json(['message' => 'تم إرسال التنبيه بنجاح.']);
    }

    public function lateDetail(Student $student)
    {
        if (!Auth::user()->can('view subscriptions')) {
            abort(403);
        }

        $enrolledMonths = $this->buildEnrolledMonths($student);

        $paidSet = array_flip(
            $student->subscriptions()
                ->whereIn('status', ['مدفوع', 'معفي'])
                ->pluck('month')
                ->map(fn($d) => Carbon::parse($d)->format('Y-m'))
                ->all()
        );

        $unpaid = $enrolledMonths
            ->filter(fn($monthStr) => !isset($paidSet[$monthStr]))
            ->map(function ($monthStr) {
                $date = Carbon::createFromFormat('Y-m', $monthStr);
                return $date->locale('ar')->isoFormat('MMMM') . ' ' . $date->format('Y');
            })
            ->values()
            ->all();

        return response()->json([
            'student' => $student->name,
            'months'  => $unpaid,
            'count'   => count($unpaid),
        ]);
    }

    public function edit(Subscription $subscription)
    {
        $this->authorize('update', $subscription);

        $user     = Auth::user();
        $circles  = $this->access->accessibleCircles($user)->get();

        $students = Student::withoutGlobalScopes()
            ->with(['circle', 'subscriptions'])
            ->where(function ($q) use ($subscription, $circles) {
                $q->where('status', 'مقيد')
                    ->whereIn('circle_id', $circles->pluck('id'));
            })
            ->orWhere('id', $subscription->student_id)
            ->get();
        $prices = \App\Models\SubscriptionPrice::all();

        $teachers = collect();
        if ($user->hasRole(['admin', 'general_manager'])) {
            $teachers = User::whereHas('roles', function ($q) {
                $q->whereIn('name', ['supervisor', 'manager', 'general_manager', 'teacher']);
            })->with('roles')->get(['id', 'name']);
        }

        $collectedByUsers = $this->buildCollectedByUsers($user);

        // ✅ جديد: فحص إذا كان الاشتراك محميًا بالتحصيل مؤكَّد
        $collectionRoundItem = $subscription->collectionRoundItem;
        $isLockedByConfirmedRound = $collectionRoundItem && $collectionRoundItem->collectionRound?->status === 'confirmed';

        // ✅ الطلاب المعفون في شهر هذا الاشتراك تحديدًا (لتمييزهم بصريًا في الفورم)
        $exemptedStudentIds = Subscription::where('month', $subscription->month)
            ->where('status', 'معفي')
            ->whereIn('student_id', $students->pluck('id'))
            ->pluck('student_id')
            ->toArray();

        return view('subscriptions.edit', compact(
            'subscription',
            'circles',
            'students',
            'prices',
            'teachers',
            'collectedByUsers',
            'isLockedByConfirmedRound',
            'exemptedStudentIds'
        ));
    }


    public function update(UpdateSubscriptionRequest $request, Subscription $subscription)
    {
        $this->authorize('update', $subscription);

        $user      = Auth::user();
        $circleIds = $this->access->accessibleCircles($user)->pluck('id');
        $validated = $request->validated();

        if (!$circleIds->contains($validated['circle_id'])) {
            abort(403, 'ليس لديك صلاحية لنقل الاشتراك لهذه الحلقة.');
        }
        $month = Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth()->format('Y-m-d');
        $isExempt = $validated['status'] === 'معفي';
        $isUnpaid = $validated['status'] === 'غير مدفوع';

        $teacherId = $user->hasRole(['admin', 'general_manager'])
            ? ($validated['teacher_id'] ?? $subscription->teacher_id)
            : $subscription->teacher_id;

        $data = [
            'student_id'     => $validated['student_id'],
            'circle_id'      => $validated['circle_id'],
            'teacher_id'     => $teacherId,
            'month'          => $month,
            'status'         => $validated['status'],
            'amount'         => ($isExempt || $isUnpaid) ? 0 : ($validated['amount'] ?? $subscription->amount),
            'payment_method' => ($isExempt || $isUnpaid) ? null : ($validated['payment_method'] ?? null),
            'paid_at'        => $validated['status'] === 'مدفوع' ? ($subscription->paid_at ?? now()) : null,
            'collected_by'   => $validated['status'] === 'مدفوع' ? ($validated['collected_by'] ?? $subscription->collected_by) : null,
            'notes'          => $validated['notes'] ?? null,
        ];

        // ✅ FIX: طبقة حماية إضافية (server-side) — لو مفيش أي تغيير فعلي
        // على بيانات الاشتراك، تجاهل التحديث بدل عمل UPDATE + Job (CalculateUnpaidMonths) غير ضروريين.
        // ده احتياطي فقط؛ المنع الأساسي المفروض يكون بالـ JS في subscriptions/edit.blade.php
        if (!$this->hasSubscriptionChanges($subscription, $data)) {
            return redirect()->route('subscriptions.index')->with('info', 'لم يتم إجراء أي تعديل.');
        }

        $collectionRoundItem = $subscription->collectionRoundItem;
        $isLockedByConfirmedRound = $collectionRoundItem && $collectionRoundItem->collectionRound?->status === 'confirmed';

        if ($isLockedByConfirmedRound) {
            $round = $collectionRoundItem->collectionRound;

            return back()->with(
                'error',
                'هذا الاشتراك جزء من جولة تحصيل رقم ' . ($round?->round_number ?? '—') .
                    ' مؤكَّدة، ولا يمكن تعديله. لتعديله، يجب أولاً إزالته من الجولة عبر ' .
                    '<a href="' . route('collection-rounds.edit', $round?->id ?? 0) . '" class="underline font-bold hover:text-amber-800">صفحة التعديل</a>.'
            );
        }

        $subscription->update($data);
        CalculateUnpaidMonths::dispatch($validated['student_id']);

        return redirect()->route('subscriptions.index')->with('success', 'تم تحديث الاشتراك بنجاح');
    }

    // ✅ FIX: مقارنة بيانات الاشتراك الحالية مقابل المُرسَلة لتحديد وجود تغيير فعلي
    private function hasSubscriptionChanges(Subscription $subscription, array $data): bool
    {
        $comparableFields = [
            'student_id',
            'circle_id',
            'teacher_id',
            'status',
            'amount',
            'payment_method',
            'collected_by',
            'notes',
        ];

        foreach ($comparableFields as $field) {
            $current  = $subscription->{$field};
            $incoming = $data[$field] ?? null;

            if ((string) ($current ?? '') !== (string) ($incoming ?? '')) {
                return true;
            }
        }

        // ✅ month مخزّن كـ date — قارن بصيغة موحدة
        $currentMonth  = $subscription->month instanceof \Illuminate\Support\Carbon
            ? $subscription->month->format('Y-m-d')
            : (string) $subscription->month;
        $incomingMonth = (string) ($data['month'] ?? '');

        if ($currentMonth !== $incomingMonth) {
            return true;
        }

        return false;
    }

    public function destroy(Subscription $subscription)
    {
        $this->authorize('delete', $subscription);

        $collectionRoundItem = $subscription->collectionRoundItem;

        // ─── المنطق الجديد: منع الحذف إذا كان الاشتراك مرتبطًا بأي جولة تحصيل ───
        if ($collectionRoundItem) {
            $round = $collectionRoundItem->collectionRound;

            return back()->with(
                'error',
                'هذا الاشتراك جزء من جولة تحصيل رقم ' . ($round?->round_number ?? '—') .
                    '. لحذفه، يجب أولاً إزالته من الجولة عبر ' .
                    '<a href="' . route('collection-rounds.edit', $round?->id ?? 0) . '" class="underline font-bold hover:text-amber-800">صفحة التعديل</a>.'
            );
        }

        $subscription->delete();
        CalculateUnpaidMonths::dispatch($subscription->student_id);

        return redirect()->route('subscriptions.index')
            ->with('success', 'تم حذف الاشتراك بنجاح');
    }

    public function getFilterOptions(Request $request)
    {
        if (!Auth::user()->can('view subscriptions')) {
            abort(403);
        }

        $user      = Auth::user();
        $centerId  = $request->get('center_id');
        $branchId  = $request->get('branch_id');
        $circleId  = $request->get('circle_id');
        $teacherId = $request->get('teacher_id');

        $circles   = $this->access->accessibleCircles($user)->get();
        $circleIds = $circles->pluck('id');

        // ─── فلتر الحلقات ───────────────────────────────────────
        if ($user->hasRole(['admin', 'general_manager'])) {
            $circlesQuery = Circle::orderBy('name');
        } else {
            $circlesQuery = Circle::whereIn('id', $circleIds)->orderBy('name');
        }

        if ($centerId) {
            $circlesQuery->whereHas('branch', fn($bq) => $bq->where('center_id', $centerId));
        }

        if ($branchId) {
            $circlesQuery->where('branch_id', $branchId);
        }

        if ($teacherId) {
            $teacher = \App\Models\Teacher::where('user_id', $teacherId)->first();
            if ($teacher) {
                $teacherCircleIds = DB::table('circle_teacher')
                    ->where('teacher_id', $teacher->id)
                    ->pluck('circle_id');
                $circlesQuery->whereIn('id', $teacherCircleIds);
            }
        }

        // ─── فلتر المعلمين ──────────────────────────────────────
        $teachersQuery = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['supervisor', 'manager', 'general_manager', 'teacher']);
        })->orderBy('name');

        if ($centerId) {
            $teachersQuery->whereHas('teacher.branch', fn($q) => $q->where('center_id', $centerId));
        }

        if ($branchId) {
            $teachersQuery->whereHas('teacher', fn($q) => $q->where('branch_id', $branchId));
        }

        if ($circleId) {
            $teachersQuery->whereHas('teacher', function ($q) use ($circleId) {
                $q->whereHas('circles', fn($cq) => $cq->where('circles.id', $circleId));
            });
        }
        // ─── فلتر الفروع ────────────────────────────────────────
        $centersQuery = Center::orderBy('name');

        if ($circleId) {
            $circle = Circle::with('branch')->find($circleId);
            if ($circle?->branch) {
                $centersQuery->where('id', $circle->branch->center_id);
            }
        }

        if ($teacherId) {
            $teacher = $teacher ?? \App\Models\Teacher::with('branch')->where('user_id', $teacherId)->first();
            if ($teacher?->branch) {
                $centersQuery->where('id', $teacher->branch->center_id);
            }
        }


        // ─── فلتر المقرات (الفروع) ──────────────────────────────
        if ($user->hasRole(['admin', 'general_manager'])) {
            $branchesQuery = \App\Models\Branch::orderBy('name');
        } elseif ($user->hasRole('manager')) {
            $managerTeacher  = $this->access->teacher($user);
            $managerCenterId = $this->access->teacherCenterId($managerTeacher);
            $branchesQuery   = \App\Models\Branch::where('center_id', $managerCenterId ?? 0)->orderBy('name');
        } else {
            $branchesQuery = \App\Models\Branch::whereRaw('1 = 0');
        }

        if ($centerId) {
            $branchesQuery->where('center_id', $centerId);
        }

        $collectedByUsers = $this->buildCollectedByUsers($user, $centerId, $circleId, $branchId);

        return response()->json([
            'circles'          => $circlesQuery->get(['id', 'name']),
            'teachers'         => $teachersQuery->get(['id', 'name']),
            'centers'          => $centersQuery->get(['id', 'name']),
            'branches'         => $branchesQuery->get(['id', 'name']),
            'collected_by'     => $collectedByUsers, // ✅ جديد
        ]);
    }
    /**
     * بناء قائمة المحصِّلين المتاحين حسب دور المستخدم.
     * admin/general_manager → الكل | manager → فرعه | supervisor → حلقاته | غيرهم → فارغة
     */
    private function buildCollectedByUsers(User $user, ?int $centerId = null, ?int $circleId = null, ?int $branchId = null): \Illuminate\Support\Collection
    {
        $query = null;

        // ✅ الأدوار المسموح ظهورها كـ "محصِّل" — يستبعد صراحةً guardian و admin
        // بدل الاعتماد على استبعاد دور admin فقط (وده كان بيسيب أولياء الأمور داخلين بالخطأ)
        $collectorRoles = ['teacher', 'supervisor', 'manager', 'general_manager'];

        if ($user->hasRole(['admin', 'general_manager'])) {
            $query = User::whereHas('roles', fn($q) => $q->whereIn('name', $collectorRoles));
        } elseif ($user->hasRole('manager')) {
            $managerCenterId = \App\Models\Teacher::where('user_id', $user->id)
                ->whereHas('branch')
                ->with('branch')
                ->first()?->branch?->center_id;

            if ($managerCenterId) {
                $query = User::whereHas('teacher.branch', fn($q) => $q->where('center_id', $managerCenterId));
            }
        } elseif ($user->hasRole('supervisor')) {
            $teacher = $this->access->teacher($user);

            if ($teacher && $teacher->branch_id) {
                $branchId = $teacher->branch_id;

                $query = User::whereHas('roles', fn($q) => $q->whereIn('name', $collectorRoles))
                    ->whereHas('teacher', function ($q) use ($branchId) {
                        $q->where('branch_id', $branchId)
                            ->orWhereHas('circles', function ($cq) use ($branchId) {
                                $cq->withoutGlobalScope(\App\Models\Scopes\CenterScope::class)
                                    ->where('branch_id', $branchId);
                            });
                    });
            }
        }
        if (!$query) {
            return collect();
        }

        if ($centerId) {
            $query->whereHas('teacher.branch', fn($q) => $q->where('center_id', $centerId));
        }

        if ($branchId) {
            $query->whereHas('teacher', fn($q) => $q->where('branch_id', $branchId));
        }

        if ($circleId) {
            $query->whereHas('teacher', function ($q) use ($circleId) {
                $q->whereHas('circles', fn($cq) => $cq->where('circles.id', $circleId));
            });
        }

        return $query->orderBy('name')->get(['id', 'name']);
    }

    /**
     * حساب كل الشهور اللي كان فيها الطالب مسجَّل فعليًا في أي حلقة،
     * بناءً على سجل CircleAssignmentHistory، حتى الشهر الحالي كحد أقصى.
     */
    private function buildEnrolledMonths(Student $student): \Illuminate\Support\Collection
    {
        $assignments = CircleAssignmentHistory::where('student_id', $student->id)
            ->orderBy('from_date')
            ->get(['from_date', 'to_date']);

        $currentMonth = now()->startOfMonth();

        $enrolledMonths = collect();
        foreach ($assignments as $assignment) {
            $start       = $assignment->from_date->copy()->startOfMonth();
            $end         = ($assignment->to_date ?? now())->copy()->startOfMonth();
            if ($end->gt($currentMonth)) $end = $currentMonth->copy();
            $totalMonths = $start->diffInMonths($end) + 1;

            for ($i = 0; $i < $totalMonths; $i++) {
                $enrolledMonths->push($start->copy()->addMonths($i)->format('Y-m'));
            }
        }

        return $enrolledMonths->unique()->values();
    }

    /**
     * صفحة "اشتراكاتي" لولي الأمر
     */
    public function mySubscription(Request $request)
    {
        $user = Auth::user();
        $month = $request->get('month', now()->format('Y-m'));
        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->format('Y-m-d');

        // ⬅️ أبناء ولي الأمر (استبعاد المتوقفين)
        $students = Student::withoutGlobalScopes()
            ->where('guardian_id', $user->id)
            ->where('status', '!=', 'متوقف')
            ->with(['circle:id,name', 'subscriptions' => function ($q) use ($monthStart) {
                $q->where('month', $monthStart);
            }])
            ->get(['id', 'name', 'circle_id', 'status']);

        // ⬅️ آخر 6 أشهر للـ summary
        $last6Months = collect(range(5, 0))->map(
            fn($i) => now()->subMonths($i)->startOfMonth()->format('Y-m-d')
        );

        $summary = [];

        foreach ($students as $student) {
            // الاشتراك للشهر المحدد
            $currentSubscription = $student->subscriptions->first();

            // سجل آخر 6 أشهر
            $history = Subscription::where('student_id', $student->id)
                ->whereIn('month', $last6Months)
                ->with(['collectedBy:id,name', 'teacher:id,name'])
                ->orderBy('month', 'desc')
                ->get();

            // حساب المدفوع وغير المدفوع خلال 6 أشهر
            $paidCount = $history->where('status', 'مدفوع')->count();
            $unpaidCount = $history->where('status', '!=', 'مدفوع')->count();
            $lastPaidAt = $history->where('status', 'مدفوع')->sortByDesc('paid_at')->first()?->paid_at;

            $summary[] = [
                'student' => $student,
                'circle' => $student->circle,
                'current_subscription' => $currentSubscription,
                'history' => $history,
                'paid_count_6m' => $paidCount,
                'unpaid_count_6m' => $unpaidCount,
                'last_paid_at' => $lastPaidAt,
            ];
        }

        return view('guardians.my_subscription', compact('summary', 'month'));
    }
}
