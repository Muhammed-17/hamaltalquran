<?php

namespace App\Http\Controllers\Badges;

use App\Http\Controllers\Controller;
use App\Http\Requests\Badges\ResolveBadgeShortageRequest;
use App\Models\Badges\BadgeShortage;
use App\Models\Badges\BadgeType;
use App\Services\Badges\BadgeBalanceService;
use App\Services\Badges\BadgeDistributionService;
use App\Services\UserAccessService;
use Illuminate\Http\Request;

class BadgeShortageController extends Controller
{
    public function index(Request $request, UserAccessService $access, \App\Services\Badges\BadgeAccessService $badgeAccess, BadgeBalanceService $balances)
    {
        $this->authorize('viewAny', BadgeShortage::class);

        $user = $request->user();

        $base = BadgeShortage::query()
            ->when(!$user->hasRole(['admin', 'general_manager']), function ($q) use ($badgeAccess, $user) {
                // كل شخص يرى عجزه وعجز من تحته في سلسلة العهدة فقط
                // (من يستطيع التسليم لهم = رتبة أقل وداخل نطاقه) + عجزه هو
                $visibleIds = $badgeAccess->selectableRecipients($user)->pluck('id')->push($user->id);
                $q->whereIn('teacher_id', $visibleIds);
            })
            ->open() // تُعرض السجلات المفتوحة فقط (لا فلتر حالة/نوع في الصفحة)
            ->when($request->filled('teacher_id'), fn ($q) => $q->where('teacher_id', $request->teacher_id))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('student', fn ($s) => $s->withoutGlobalScopes()->where('name', 'like', "%{$request->q}%")));

        // التجميع حسب الموزّع: بطاقة لكل موزّع بإجمالي عجزه، والطلاب
        // المستحقون داخل البطاقة يظهرون بزر «عرض الطلاب»
        $teacherKeys = (clone $base)->selectRaw('teacher_id, MAX(id) as max_id')->groupBy('teacher_id')->get()
            ->map(fn ($r) => ['key' => (int) $r->teacher_id, 'max_id' => (int) $r->max_id])
            ->sortByDesc('max_id')->values();

        $perPage = 20;
        $page = \Illuminate\Pagination\Paginator::resolveCurrentPage();
        $pageKeys = $teacherKeys->slice(($page - 1) * $perPage, $perPage)->values();
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $pageKeys, $teacherKeys->count(), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $items = (clone $base)->with(['student', 'teacher', 'badgeType', 'entitlement.circle'])
            ->whereIn('teacher_id', $pageKeys->pluck('key'))
            ->latest('id')->get();
        $canCreateHandover = $user->can('badges.handovers.create');
        $groups = $pageKeys->map(function ($k) use ($items, $user, $badgeAccess, $canCreateHandover, $balances) {
            $groupItems = $items->where('teacher_id', $k['key'])->values();
            $teacher = $groupItems->first()?->teacher;

            // بيانات زر «تسليم الطالب»: أرصدة الموزّع الحالية، ولكل طالب هل
            // يستطيع المستخدم الحالي استكمال سجلاته وهل يوجد رصيد قابل للتسليم
            $typeBalances = $groupItems->pluck('badge_type_id')->unique()
                ->mapWithKeys(fn ($typeId) => [$typeId => $teacher ? $balances->teacherBalance($teacher->id, $typeId) : 0]);
            $studentMeta = $groupItems->groupBy('student_id')->map(fn ($rows) => [
                'resolvable' => $rows->every(fn ($s) => $user->can('resolve', $s)),
                'deliverable' => $rows->contains(fn ($s) => min((int) $s->shortage_quantity, (int) ($typeBalances[$s->badge_type_id] ?? 0)) > 0),
            ]);
            // زر «تسليم»: يظهر فقط لمن يستطيع فعلًا التسليم لهذا الموزّع
            // (صلاحية + رتبة/نطاق سلسلة العهدة)، ويعبّئ نموذج التسليم بكميات عجزه
            $canHandover = $canCreateHandover && $teacher && $badgeAccess->canHandoverTo($user, $teacher);

            return [
                'teacher' => $teacher,
                'items' => $groupItems,
                'studentMeta' => $studentMeta,
                'canHandover' => $canHandover,
                'handoverUrl' => $canHandover ? route('badges.handovers.create', [
                    'teacher_id' => $teacher->id,
                    'quantities' => $groupItems->groupBy('badge_type_id')
                        ->map(fn ($rows) => (int) $rows->sum('shortage_quantity'))->all(),
                ]) : null,
            ];
        })->filter(fn ($g) => $g['items']->isNotEmpty())->values();

        return view('badges.shortages.index', [
            'groups' => $groups,
            'paginator' => $paginator,
            'totalRecords' => (clone $base)->count(),
            // أعمدة جدول الطلاب: الأنواع الموجودة في مجموعات هذه الصفحة
            'typesInScope' => BadgeType::whereIn('id', $items->pluck('badge_type_id')->unique())->orderBy('name')->get(),
        ]);
    }

    /**
     * زر «تسليم الطالب» في قائمة النواقص: يوزّع كل أنواع عجز الطالب المفتوحة
     * عند هذا الموزّع دفعة واحدة — لكل نوع: الأقل من (الناقص، رصيد الموزّع
     * الحالي) — بتاريخ اليوم وبلا نموذج، في معاملة واحدة.
     */
    public function distributeStudent(Request $request, BadgeDistributionService $service, BadgeBalanceService $balances)
    {
        $request->validate([
            'teacher_id' => ['required', 'integer'],
            'student_id' => ['required', 'integer'],
        ]);

        $shortages = BadgeShortage::open()
            ->where('teacher_id', (int) $request->teacher_id)
            ->where('student_id', (int) $request->student_id)
            ->with('entitlement')
            ->get();

        // نفس بوابة الاستكمال لكل سجل قبل أي توزيع
        foreach ($shortages as $shortage) {
            $this->authorize('resolve', $shortage);
        }

        $distributed = 0;
        \Illuminate\Support\Facades\DB::transaction(function () use ($shortages, $service, $balances, $request, &$distributed) {
            foreach ($shortages as $shortage) {
                $qty = min((int) $shortage->shortage_quantity, $balances->teacherBalance($shortage->teacher_id, $shortage->badge_type_id));
                if ($qty > 0) {
                    $service->distribute($shortage->entitlement, $qty, now()->toDateString(), $request->user());
                    $distributed += $qty;
                }
            }
        });

        return back()->with('success', $distributed > 0
            ? "تم تسليم {$distributed} وسامًا للطالب بنجاح."
            : 'لا يوجد رصيد متاح عند الموزّع للتسليم.');
    }

    public function show(BadgeShortage $shortage, BadgeBalanceService $balances)
    {
        $this->authorize('view', $shortage);

        $shortage->load(['student', 'teacher', 'badgeType', 'entitlement.circle',
            'entitlement.distributions' => fn ($q) => $q->latest('id')]);

        return view('badges.shortages.show', [
            'shortage' => $shortage,
            'teacherBalance' => $balances->teacherBalance($shortage->teacher_id, $shortage->badge_type_id),
        ]);
    }

    /** استكمال التسليم: توزيع جديد على الاستحقاق المرتبط بالعجز */
    public function resolve(ResolveBadgeShortageRequest $request, BadgeShortage $shortage, BadgeDistributionService $service)
    {
        $service->distribute(
            $shortage->entitlement,
            (int) $request->quantity,
            $request->distribution_date,
            $request->user(),
            $request->notes,
        );

        return redirect()->route('badges.shortages.show', $shortage)
            ->with('success', 'تم تسجيل التسليم بنجاح.');
    }
}
