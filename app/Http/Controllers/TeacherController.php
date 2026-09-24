<?php

namespace App\Http\Controllers;

use App\Http\Requests\Teacher\StoreTeacherRequest;
use App\Http\Requests\Teacher\UpdateTeacherRequest;
use App\Models\Teacher;
use App\Models\User;
use App\Traits\HasAllowedRoles;
use App\Services\UserAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

class TeacherController extends Controller
{
    use HasAllowedRoles;

    public function __construct(protected UserAccessService $access) {}

    // ─────────────────────────────────────────
    public function index(Request $request)
    {
        $this->authorize('viewAny', Teacher::class);

        $user    = Auth::user();

        $query = Teacher::query()
            ->join('users', 'teachers.user_id', '=', 'users.id')
            ->select('teachers.*', 'users.name as user_name', 'users.email as user_email', 'users.status as user_status', 'users.last_seen_at')
            ->with(['user.roles', 'branch.center']);

        if (!$user->hasRole(['admin', 'general_manager'])) {
            $query->where('users.status', 'active');
        }

        // بحث بالاسم أو البريد
        if ($request->filled('q')) {
            $term = $request->q;
            $query->where(function ($qq) use ($term) {
                $qq->where('users.name', 'like', "%{$term}%")
                    ->orWhere('users.email', 'like', "%{$term}%");
            });
        }

        // فلتر الفرع (بالاسم زي ما الفرونت كان بيبعته)

        if ($request->filled('center_id') && $user->hasRole(['admin', 'general_manager'])) {
            $query->whereHas('branch.center', fn($cq) => $cq->where('name', $request->center_id));
        }

        // فلتر الفرع
        if ($request->filled('branch_id')) {
            $query->where('teachers.branch_id', $request->branch_id);
        }

        // فلتر الدور
        if ($request->filled('role') && $user->hasRole(['admin', 'general_manager', 'manager'])) {
            $query->whereHas('user.roles', fn($q) => $q->where('name', $request->role));
        }

        // فلتر الحالة
        if ($request->filled('status')) {
            $query->where('users.status', $request->status);
        }

        $sortBy    = $request->get('sort_by', 'id');
        $sortOrder = $request->get('sort_order', 'asc') === 'desc' ? 'desc' : 'asc';

        switch ($sortBy) {
            case 'status':
                $query->orderBy('users.status', $sortOrder);
                break;

            case 'online':
                $query->orderByRaw('users.last_seen_at IS NULL, users.last_seen_at ' . $sortOrder);
                break;

            case 'role':
                $query->leftJoin('model_has_roles', function ($join) {
                    $join->on('users.id', '=', 'model_has_roles.model_id')
                        ->where('model_has_roles.model_type', '=', \App\Models\User::class);
                })
                    ->leftJoin('roles', 'model_has_roles.role_id', '=', 'roles.id')
                    ->orderBy('roles.display_name', $sortOrder);
                break;

            case 'center':
                $query->leftJoin('branches', 'teachers.branch_id', '=', 'branches.id')
                    ->leftJoin('centers', 'branches.center_id', '=', 'centers.id')
                    ->orderBy('centers.name', $sortOrder);
                break;

            case 'branch':
                $query->leftJoin('branches', 'teachers.branch_id', '=', 'branches.id')
                    ->orderBy('branches.name', $sortOrder);
                break;

            default:
                $query->orderBy('teachers.id', $sortOrder);
                break;
        }

        $teachers = $query->paginate(20)->withQueryString();
        $centers  = $this->access->accessibleCenters($user)->get();
        $branches = $this->access->accessibleBranches($user)->with('center')->get();
        $roles    = Role::orderBy('name')->get();

        return view('teachers.index', compact('teachers', 'centers', 'branches', 'roles'));
    }

    // ─────────────────────────────────────────
    public function create()
    {
        $this->authorize('create', Teacher::class);

        $user     = Auth::user();
        $branches = $this->access->accessibleBranches($user)->get();
        $roles    = $this->getAllowedRolesForCreate($user);

        return view('teachers.create', compact('branches', 'roles'));
    }

    // ─────────────────────────────────────────
    public function store(StoreTeacherRequest $request)
    {
        $this->authorize('create', Teacher::class);
        $this->authorize('create', User::class);

        DB::transaction(function () use ($request) {


            // إضافة الحقول الأمنية لضمان عدم حجب الحساب فور إنشائه
            $user = User::create([
                'name'              => $request->name,
                'email'             => $request->email,
                'password'          => Hash::make($request->password),
            ]);

            $user->syncRoles($request->roles ?? []);

            Teacher::create([
                'user_id'           => $user->id,
                'branch_id'         => $request->branch_id,
            ]);
        });

        return redirect()->route('teachers.index')->with('success', 'تم إضافة المستخدم بنجاح وتفعيله فوراً');
    }

    // ─────────────────────────────────────────
    public function show(string $id)
    {
        $user = Auth::user();
        $record = $this->access->teacher($user);

        // ✅ فلترة أمان قبل الجلب
        $query = Teacher::withoutGlobalScope(\App\Models\Scopes\CenterScope::class)
            ->with([
                'user.roles',
                'branch.center',
                'circles' => fn($q) => $q->withoutGlobalScope(\App\Models\Scopes\CenterScope::class),
            ]);

        // المعلم/المشرف: فقط نفسه
        if ($user->hasRole(['teacher', 'supervisor']) && !$user->hasRole(['manager', 'admin', 'general_manager'])) {
            $query->where('user_id', $user->id);
        }
        // المدير: فقط فرعه
        elseif ($record && $record->branch && $user->hasRole('manager')) {
            $centerId = $record->branch->center_id;
            $query->where(function ($q) use ($centerId) {
                $q->whereHas('branch', fn($bq) => $bq->where('center_id', $centerId))
                    ->orWhereHas('circles.branch', fn($cq) => $cq->where('center_id', $centerId));
            });
        }

        $teacher = $query->findOrFail($id);

        $this->authorize('view', $teacher);

        return view('teachers.show', compact('teacher'));
    }

    // ─────────────────────────────────────────
    public function edit(Teacher $teacher)
    {
        $this->authorize('update', $teacher);

        $user    = Auth::user();
        $branches = $this->access->accessibleBranches($user)->get();

        $teacher->load('user.roles');

        // ✅ تمرير الأدوار المسموحة فقط
        $roles = $this->getAllowedRolesForEdit($user, $teacher);

        $currentRoles = $teacher->user->roles->pluck('name')->toArray();

        return view('teachers.edit', compact('teacher', 'branches', 'roles', 'currentRoles'));
    }

    // ─────────────────────────────────────────
    public function update(UpdateTeacherRequest $request, Teacher $teacher)
    {
        $this->authorize('update', $teacher);

        DB::transaction(function () use ($request, $teacher) {

            // 1. تحديث بيانات المعلم
            $teacher->update([
                'branch_id'         => $request->branch_id,
            ]);

            // 2. تجهيز بيانات المستخدم
            $data = [
                'name'              => $request->name,
                'email'             => $request->email,
            ];

            // ✅ تغيير كلمة المرور
            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            }

            $teacher->user->update($data);
            $teacher->user->syncRoles($request->roles ?? []);
        });

        return redirect()->route('teachers.index')->with('success', 'تم تحديث البيانات بنجاح');
    }

    // ─────────────────────────────────────────
    public function destroy(Teacher $teacher)
    {
        $this->authorize('delete', $teacher);

        // التحقق من عدم وجود حلقات مرتبطة
        if ($teacher->circles()->exists()) {
            return back()->with('error', 'لا يمكن حذف معلم مرتبط بحلقات.');
        }

        // منع حذف نفسه
        if (Auth::id() === $teacher->user_id) {
            return back()->with('error', 'لا يمكنك حذف حسابك الخاص.');
        }

        // منع حذف admin أو general_manager
        if ($teacher->user->hasRole(['admin', 'general_manager'])) {
            return back()->with('error', 'لا يمكن حذف حساب إداري رئيسي.');
        }

        DB::transaction(function () use ($teacher) {
            $teacherId = $teacher->id;
            $userId = $teacher->user_id;

            $teacher->user->delete();
            $teacher->delete();
        });

        return redirect()->route('teachers.index')->with('success', 'تم الحذف بنجاح');
    }

    // ─────────────────────────────────────────
    public function toggle(Teacher $teacher)
    {
        $this->authorize('toggle', $teacher);

        // منع تعطيل نفسه
        if (Auth::id() === $teacher->user_id) {
            return back()->with('error', 'لا يمكنك تعطيل حسابك الخاص.');
        }

        // منع تعطيل admin أو general_manager
        if ($teacher->user->hasRole('admin')) {
            return back()->with('error', 'لا يمكن تعطيل حساب إداري.');
        }

        $newStatus = $teacher->user->status === 'active' ? 'inactive' : 'active';

        $teacher->user->update([
            'status' => $newStatus,
        ]);

        return back()->with('success', 'تم تحديث الحالة بنجاح');
    }
}
