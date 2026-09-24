<?php

namespace App\Http\Controllers;

use App\Http\Requests\Circle\StoreCircleRequest;
use App\Http\Requests\Circle\UpdateCircleRequest;
use App\Models\Circle;
use App\Models\Teacher;
use App\Models\Branch;
use App\Services\UserAccessService;
use App\Models\Scopes\CenterScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;


class CircleController extends Controller
{
    public function __construct(protected UserAccessService $access) {}

    // ─────────────────────────────────────────
    public function index(Request $request)
    {
        $this->authorize('viewAny', Circle::class);

        $user = Auth::user();

        $query = $this->access->accessibleCircles($user)
            ->with([
                'mainTeachers' => fn($q) => $q->withoutGlobalScope(CenterScope::class),
                'assistantTeachers' => fn($q) => $q->withoutGlobalScope(CenterScope::class),
                'branch.center',
            ])
            ->withCount('students');

        $query->when($request->q, fn($q, $v) => $q->where('name', 'like', "%{$v}%"));
        $query->when($request->branch_id, fn($q, $v) => $q->where('branch_id', $v));
        $query->when($request->center_id, fn($q, $v) => $q->whereHas('branch', fn($bq) => $bq->where('center_id', $v)));
        $query->when($request->type, fn($q, $v) => $q->where('type', $v));
        $query->when($request->level, fn($q, $v) => $q->where('level', $v));

        $allowedSorts = ['id', 'name', 'type', 'level', 'students_count', 'center', 'branch'];
        $sortField    = in_array($request->sort, $allowedSorts) ? $request->sort : 'name';
        $sortDir      = $request->dir === 'desc' ? 'desc' : 'asc';

        switch ($sortField) {
            case 'students_count':
                $query->reorder()->orderBy('students_count', $sortDir);
                break;

            case 'branch':
                $query->reorder()
                    ->leftJoin('branches', 'circles.branch_id', '=', 'branches.id')
                    ->orderBy('branches.name', $sortDir)
                    ->select('circles.*');
                break;

            case 'center':
                $query->reorder()
                    ->leftJoin('branches', 'circles.branch_id', '=', 'branches.id')
                    ->leftJoin('centers', 'branches.center_id', '=', 'centers.id')
                    ->orderBy('centers.name', $sortDir)
                    ->select('circles.*');
                break;

            default:
                $query->reorder()->orderBy('circles.' . $sortField, $sortDir);
                break;
        }

        $circles = $query->paginate(20)->withQueryString();

        $centers   = $this->access->accessibleCenters($user)->get();
        $centerIds = $centers->pluck('id');
        $branches  = Branch::with('center')->whereIn('center_id', $centerIds)->orderBy('name')->get();

        return view('circles.index', compact('circles', 'branches', 'centers'));
    }

    // ─────────────────────────────────────────
    public function create()
    {
        $this->authorize('create', Circle::class);

        $user    = Auth::user();
        $teacher = $this->access->teacher($user);

        $branches = $this->accessibleBranchesFor($user, $teacher);

        // ✅ إذا ما فيش فرع متاح — امنع الإنشاء
        if ($branches->isEmpty()) {
            return redirect()->route('circles.index')
                ->with('error', 'لا يوجد فرع مرتبط بحسابك. لا يمكن إنشاء حلقة.');
        }

        // ✅ تهيئة العلاقات كـ Collection فارغة لتجنب null
        $circle = new Circle();
        $circle->setRelation('mainTeachers', collect());
        $circle->setRelation('assistantTeachers', collect());

        return view('circles.create', [
            'circle'   => $circle,
            'teachers' => $this->access->accessibleTeachers($user)->get(),
            'branches' => $branches,
        ]);
    }

    // ─────────────────────────────────────────
    public function store(StoreCircleRequest $request)
    {
        $this->authorize('create', Circle::class);

        $user    = Auth::user();
        $teacher = $this->access->teacher($user);

        // ✅ الفرع يُختار مباشرة من الفورم (admin/general_manager)
        // أو يُفرض تلقائياً (باقي الأدوار) عبر الحقل المخفي branch_id
        if ($user->hasRole(['admin', 'general_manager'])) {
            $branchId = $request->branch_id;
        } else {
            $branchId = $request->input('branch_id') ?: $teacher?->branch_id;
        }

        // ✅ تحقق من صلاحية الفرع
        if (!$user->hasRole(['admin', 'general_manager'])) {
            $accessibleBranchIds = $this->accessibleBranchesFor($user, $teacher)->pluck('id');
            if (!$branchId || !$accessibleBranchIds->contains($branchId)) {
                abort(403, 'ليس لديك صلاحية إنشاء حلقة في هذا الفرع.');
            }
        }

        if (!$branchId) {
            abort(422, 'يجب اختيار الفرع.');
        }

        $circle = Circle::create([
            'name'      => $request->name,
            'url'       => $request->url,
            'type'      => $request->type,
            'level'     => $request->level,
            'branch_id' => $branchId,
        ]);

        $this->syncCircleStaff($circle, $request);

        return redirect()->route('circles.index')->with('success', 'تم إنشاء الحلقة بنجاح');
    }
    // ─────────────────────────────────────────

    public function show(string $id)
    {
        $user = Auth::user();

        $circleQuery = Circle::with([
            'mainTeachers' => fn($q) => $q->withoutGlobalScope(CenterScope::class),
            'assistantTeachers' => fn($q) => $q->withoutGlobalScope(CenterScope::class),
            'students',
            'studentConstructionDetails' => fn($q) => $q->withoutGlobalScope(CenterScope::class)
                ->with('student')
                ->latest(),
        ]);

        if (!$user->hasRole(['admin', 'general_manager'])) {
            $teacher = $this->access->teacher($user);
            if ($teacher) {
                $circleQuery->where(function ($q) use ($teacher) {
                    $q->whereHas('branch', fn($bq) => $bq->where('center_id', $teacher->center_id))
                        ->orWhereIn('id', function ($sub) use ($teacher) {
                            $sub->select('circle_id')
                                ->from('circle_teacher')
                                ->where('teacher_id', $teacher->id)
                                ->whereIn('role', ['main', 'assistant']);
                        });
                });
            }
        }

        $circle = $circleQuery->findOrFail($id);

        $this->authorize('view', $circle);

        return view('circles.show', compact('circle'));
    }

    // ─────────────────────────────────────────
    public function edit(string $id)
    {
        $user = Auth::user();

        $circleQuery = Circle::with([
            'mainTeachers' => fn($q) => $q->withoutGlobalScope(CenterScope::class),
            'assistantTeachers' => fn($q) => $q->withoutGlobalScope(CenterScope::class),
        ]);

        // ✅ نفس فلترة الأمان
        if (!$user->hasRole(['admin', 'general_manager'])) {
            $teacher = $this->access->teacher($user);
            if ($teacher) {
                $circleQuery->where(function ($q) use ($teacher) {
                    $q->whereHas('branch', fn($bq) => $bq->where('center_id', $teacher->center_id))
                        ->orWhereIn('id', function ($sub) use ($teacher) {
                            $sub->select('circle_id')
                                ->from('circle_teacher')
                                ->where('teacher_id', $teacher->id)
                                ->whereIn('role', ['main', 'assistant']);
                        });
                });
            }
        }

        $circle = $circleQuery->findOrFail($id);
        $this->authorize('update', $circle);

        $teacher = $this->access->teacher($user);

        return view('circles.edit', [
            'circle'   => $circle,
            'teachers' => $this->access->accessibleTeachers($user)->get(),
            'branches' => $this->accessibleBranchesFor($user, $teacher),
        ]);
    }

    // ─────────────────────────────────────────
    public function update(UpdateCircleRequest $request, string $id)
    {
        $user = Auth::user();

        $circleQuery = Circle::query();
        if (!$user->hasRole(['admin', 'general_manager'])) {
            $teacher = $this->access->teacher($user);
            if ($teacher) {
                $circleQuery->where(function ($q) use ($teacher) {
                    $q->whereHas('branch', fn($bq) => $bq->where('center_id', $teacher->center_id))
                        ->orWhereIn('id', function ($sub) use ($teacher) {
                            $sub->select('circle_id')
                                ->from('circle_teacher')
                                ->where('teacher_id', $teacher->id)
                                ->whereIn('role', ['main', 'assistant']);
                        });
                });
            }
        }

        $circle = $circleQuery->findOrFail($id);
        $this->authorize('update', $circle);

        // ✅ admin/general_manager يقدر يغيّر الفرع مباشرة، غيرهم الفرع ثابت
        $branchId = $user->hasRole(['admin', 'general_manager']) && $request->filled('branch_id')
            ? $request->branch_id
            : $circle->branch_id;

        $updateData = [
            'branch_id' => $branchId,
        ];

        if ($request->has('name')) {
            $updateData['name'] = $request->name;
        }
        if ($request->has('url')) {
            $updateData['url'] = $request->url;
        }
        if ($request->has('type')) {
            $updateData['type'] = $request->type;
        }
        if ($request->has('level')) {
            $updateData['level'] = $request->level;
        }

        if (!$this->hasCircleChanges($circle, $updateData, $request)) {
            return redirect()->route('circles.index')->with('info', 'لم يتم إجراء أي تعديل.');
        }

        $circle->update($updateData);
        $this->syncCircleStaff($circle, $request);

        return redirect()->route('circles.index')->with('success', 'تم تحديث الحلقة بنجاح');
    }

    // ─────────────────────────────────────────
    // ✅ مقارنة بيانات الحلقة الأساسية + الطاقم الحالي مقابل المُرسَل
    private function hasCircleChanges(Circle $circle, array $updateData, Request $request): bool
    {
        // 1) مقارنة الأعمدة الأساسية (name / url / type / level / branch_id)
        foreach ($updateData as $key => $value) {
            if ((string) $circle->{$key} !== (string) $value) {
                return true;
            }
        }

        // 2) مقارنة الطاقم الحالي (معلم رئيسي/مساعد) بالمُرسَل
        $current = DB::table('circle_teacher')
            ->where('circle_id', $circle->id)
            ->select('teacher_id', 'role')
            ->get();

        $currentMain      = optional($current->firstWhere('role', 'main'))->teacher_id;
        $currentAssistant = optional($current->firstWhere('role', 'assistant'))->teacher_id;

        $incomingMain      = $request->teacher_id ? (string) $request->teacher_id : null;
        $incomingAssistant = $request->assistant_teacher_id ? (string) $request->assistant_teacher_id : null;

        if ((string) $currentMain !== (string) $incomingMain) {
            return true;
        }
        if ((string) $currentAssistant !== (string) $incomingAssistant) {
            return true;
        }

        return false;
    }

    // ─────────────────────────────────────────
    public function destroy(string $id)
    {
        $user = Auth::user();

        $circleQuery = Circle::query();

        // ✅ نفس فلترة الأمان
        if (!$user->hasRole(['admin', 'general_manager'])) {
            $teacher = $this->access->teacher($user);
            if ($teacher) {
                $circleQuery->where(function ($q) use ($teacher) {
                    $q->whereHas('branch', fn($bq) => $bq->where('center_id', $teacher->center_id))
                        ->orWhereIn('id', function ($sub) use ($teacher) {
                            $sub->select('circle_id')
                                ->from('circle_teacher')
                                ->where('teacher_id', $teacher->id)
                                ->whereIn('role', ['main', 'assistant']);
                        });
                });
            }
        }

        $circle = $circleQuery->findOrFail($id);
        $this->authorize('delete', $circle);

        // ✅ التحقق من عدم وجود طلاب مسجلين قبل الحذف
        if ($circle->students()->count() > 0) {
            return redirect()->back()->with(
                'error',
                'لا يمكن حذف الحلقة لوجود طلاب مسجلين فيها. يرجى نقل الطلاب أولاً.'
            );
        }

        DB::transaction(function () use ($circle) {
            $circle->teachers()->detach();
            $circle->delete();
        });

        return redirect()->route('circles.index')->with('success', 'تم حذف الحلقة بنجاح');
    }

    // ─────────────────────────────────────────

    private function syncCircleStaff(Circle $circle, Request $request): void
    {
        $centerId = $circle->branch?->center_id;
        $user     = Auth::user();

        // ✅ جلب المعلمين المتاحين حسب الدور
        if ($user->hasRole(['admin', 'general_manager'])) {
            $accessibleTeacherIds = Teacher::pluck('id')->toArray();
        } else {
            $accessibleTeacherIds = $this->access->accessibleTeachers($user)
                ->whereHas('user', fn($q) => $q->where('center_id', $centerId))
                ->pluck('id')
                ->toArray();
        }

        // ✅ اسمح بالمعلمين المعيّنين أصلاً على الحلقة يعدّوا من غير فحص صلاحية
        $currentlyAssignedTeacherIds = DB::table('circle_teacher')
            ->where('circle_id', $circle->id)
            ->pluck('teacher_id')
            ->toArray();

        $accessibleTeacherIds = array_unique(array_merge($accessibleTeacherIds, $currentlyAssignedTeacherIds));

        // ✅ التحقق من المعلم الرئيسي
        if ($request->teacher_id) {
            $teacher = Teacher::find($request->teacher_id);
            if (!$teacher) {
                throw new \Exception('المعلم الرئيسي غير موجود.');
            }
            if (!in_array($teacher->id, $accessibleTeacherIds)) {
                throw new \Exception('ليس لديك صلاحية تعيين هذا المعلم.');
            }
        }

        // ✅ التحقق من المعلم المساعد
        if ($request->assistant_teacher_id) {
            $teacher = Teacher::find($request->assistant_teacher_id);
            if (!$teacher) {
                throw new \Exception('المعلم المساعد غير موجود.');
            }
            if (!in_array($teacher->id, $accessibleTeacherIds)) {
                throw new \Exception('ليس لديك صلاحية تعيين هذا المعلم.');
            }
        }

        // 1) المعلم الرئيسي/المساعد — حذف القديم ثم إدراج الجديد
        DB::table('circle_teacher')
            ->where('circle_id', $circle->id)
            ->whereIn('role', ['main', 'assistant'])
            ->delete();

        $rows = [];
        if ($request->teacher_id) {
            $rows[] = [
                'circle_id'  => $circle->id,
                'teacher_id' => (int) $request->teacher_id,
                'role'       => 'main',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        if ($request->assistant_teacher_id) {
            $rows[] = [
                'circle_id'  => $circle->id,
                'teacher_id' => (int) $request->assistant_teacher_id,
                'role'       => 'assistant',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        if ($rows) {
            DB::table('circle_teacher')->insert($rows);
        }
    }

    // ─────────────────────────────────────────
    // الفروع المتاحة للمستخدم: admin/general_manager كل الفروع ضمن مراكزه المتاحة،
    // وباقي الأدوار: فرعه فقط (أو فرع أول حلقة يعمل بها لو مفيش فرع مربوط مباشرة)
    private function accessibleBranchesFor($user, ?Teacher $teacher)
    {
        $centerIds = $this->access->accessibleCenters($user)->pluck('id');
        $branches  = Branch::with('center')->whereIn('center_id', $centerIds)->orderBy('name')->get();

        if ($branches->isNotEmpty() || $user->hasRole(['admin', 'general_manager'])) {
            return $branches;
        }

        // ✅ FIX: مستخدم بدون center_id مباشر (مثلاً مشرف) — استخدم فرع أول حلقة يشرف/يعمل عليها
        if ($teacher) {
            $firstCircle = Circle::whereHas('mainTeachers', fn($q) => $q->where('teachers.id', $teacher->id))
                ->orWhereHas('assistantTeachers', fn($q) => $q->where('teachers.id', $teacher->id))
                ->with('branch.center')
                ->first();

            if ($firstCircle?->branch) {
                return collect([$firstCircle->branch]);
            }
        }

        return collect();
    }

    // ─────────────────────────────────────────
    public function groupPlan(Circle $circle)
    {
        $this->authorize('view', $circle);

        if ($circle->type !== 'group') {
            return response()->json(['found' => false]);
        }

        $latest = $circle->studentConstructionDetails()
            ->withoutGlobalScope(CenterScope::class)
            ->with('currentSurah:id,number,name_arabic')
            ->latest('updated_at')
            ->first();

        if (!$latest) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found'                  => true,
            'current_surah_id'       => $latest->current_surah_id,
            'current_surah_name'     => $latest->currentSurah?->name_arabic,
            'new_memorization_plan'  => $latest->new_memorization_plan,
            'revision_plan'          => $latest->revision_plan,
            'old_memorization_plan'  => $latest->old_memorization_plan,
        ]);
    }
}
