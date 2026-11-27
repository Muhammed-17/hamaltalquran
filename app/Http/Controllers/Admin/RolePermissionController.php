<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RolePermissionController extends Controller
{
    /**
     * الأدوار الأساسية المحمية — لا يمكن حذفها أو تعديل اسمها
     */
    private const PROTECTED_ROLES = [
        'admin',
        'general_manager',
        'manager',
        'supervisor',
        'teacher',
        'examiner',
        'guardian',
    ];

    /**
     * الحد الأقصى لعدد الأدوار في النظام
     */
    private const MAX_ROLES = 25;

    /**
     * أدوار لا تُدار من هذه الصفحة إطلاقًا — وصولها مربوط بالدور نفسه لا
     * بصلاحيات تُمنح من هنا، فلا تظهر هنا ولا تُعدَّل صلاحياتها:
     * مدير النظام (صلاحياته كاملة بحكم الدور، وتعديلها من هنا قد يقفل
     * النظام)، والمختبر (لا يملك أي صلاحية — بوابته بالدور والإسناد)،
     * وولي الأمر (صفحاته وبيانات أبنائه محكومة بالدور والملكية).
     */
    private const UNMANAGED_ROLES = [
        'admin',
        'examiner',
        'guardian',
    ];

    // ─────────────────────────────────────────────
    public function index(): View
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);

        $roles = Role::with('permissions')->whereNotIn('name', self::UNMANAGED_ROLES)->get();

        $permissions = Permission::orderBy('group')
            ->orderBy('display_name')
            ->get()
            ->groupBy('group');

        return view('roles.index', compact('roles', 'permissions'));
    }

    // ─────────────────────────────────────────────
    public function storeRole(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);

        // ① التحقق من الحد الأقصى للأدوار
        if (Role::count() >= self::MAX_ROLES) {
            return back()->with('error', 'تم الوصول للحد الأقصى من الأدوار (' . self::MAX_ROLES . ')');
        }

        // ② Validation قوي
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z][a-z_]*[a-z]$/',   // حروف صغيرة وunderscore فقط
                'unique:roles,name',
                Rule::notIn(self::PROTECTED_ROLES), // لا يُنشئ role باسم محمي
            ],
            'display_name'  => 'nullable|string|max:100',
            'permissions'   => 'nullable|array',
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ], [
            'name.regex'    => 'اسم الدور يجب أن يحتوي على حروف إنجليزية صغيرة وشرطة سفلية فقط',
            'name.not_in'   => 'هذا الاسم محجوز ولا يمكن استخدامه',
            'name.unique'   => 'اسم الدور موجود مسبقاً',
        ]);

        $permissionIds = $validated['permissions'] ?? [];

        // ③ منع Privilege Escalation — لا يمنح صلاحيات لا يملكها
        $this->ensureCanGrantPermissions($permissionIds);

        DB::transaction(function () use ($validated, $permissionIds) {
            $role = Role::create([
                'name'         => $validated['name'],
                'display_name' => $validated['display_name'] ?? null,
                'guard_name'   => 'web',
            ]);

            if (!empty($permissionIds)) {
                $role->syncPermissions(
                    Permission::whereIn('id', $permissionIds)->get()
                );
            }
        });

        $this->clearPermissionCache();

        return redirect()->route('admin.roles.index')
            ->with('success', 'تم إنشاء الدور بنجاح ✓');
    }

    // ─────────────────────────────────────────────
    public function updateRolePermissions(Request $request, Role $role): RedirectResponse
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);

        // الأدوار غير المُدارة من هنا لا تُعدَّل ولو بطلب مباشر
        if (in_array($role->name, self::UNMANAGED_ROLES)) {
            return redirect()->route('admin.roles.index')
                ->with('error', 'هذا الدور لا تُدار صلاحياته من هذه الصفحة');
        }

        // ① Validation
        $request->validate([
            'permissions'   => 'nullable|array',
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $permissionIds = $request->input('permissions', []);

        // ③ منع Privilege Escalation
        $this->ensureCanGrantPermissions($permissionIds);

        $oldPermissions = $role->permissions->pluck('id')->toArray();

        DB::transaction(function () use ($role, $permissionIds, $oldPermissions) {
            $role->syncPermissions(
                Permission::whereIn('id', $permissionIds)->get()
            );
        });

        // ⑤ إلغاء sessions المستخدمين المتأثرين بشكل موثوق
        $this->invalidateRoleUserSessions($role);

        $this->clearPermissionCache();

        return redirect()->route('admin.roles.index')
            ->with('success', 'تم تحديث الصلاحيات بنجاح ✓');
    }

    // ─────────────────────────────────────────────
    public function destroyRole(Role $role): RedirectResponse
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);

        // ① حماية الأدوار الأساسية
        if (in_array($role->name, self::PROTECTED_ROLES)) {
            return redirect()->route('admin.roles.index')
                ->with('error', 'لا يمكن حذف الأدوار الأساسية للنظام');
        }

        // ② التحقق من عدم وجود مستخدمين على هذا الدور
        $usersCount = User::role($role->name)->count();
        if ($usersCount > 0) {
            return redirect()->route('admin.roles.index')
                ->with('error', "لا يمكن حذف الدور، يوجد {$usersCount} مستخدم مرتبط به");
        }

        $roleName = $role->name;

        DB::transaction(function () use ($role, $roleName) {
            $role->syncPermissions([]);
            $role->delete();
        });

        $this->clearPermissionCache();

        return redirect()->route('admin.roles.index')
            ->with('success', 'تم حذف الدور بنجاح ✓');
    }

    // ─────────────────────────────────────────────
    // Private Helpers
    // ─────────────────────────────────────────────

    /**
     * يمنع Privilege Escalation — المستخدم لا يمنح صلاحيات لا يملكها هو نفسه
     */
    private function ensureCanGrantPermissions(array $permissionIds): void
    {
        // الـ admin يملك كل الصلاحيات — استثناء
        if (auth()->user()->hasRole('admin')) {
            return;
        }

        $requestedPermissions = Permission::whereIn('id', $permissionIds)->pluck('name');

        foreach ($requestedPermissions as $permName) {
            if (!auth()->user()->can($permName)) {
                abort(403, "غير مسموح: لا يمكنك منح صلاحية ({$permName}) لأنك لا تملكها");
            }
        }
    }

    /**
     * إلغاء sessions المستخدمين المرتبطين بالدور
     * يعمل مع database session driver — للـ drivers الأخرى يستخدم permissions_updated_at
     */
    private function invalidateRoleUserSessions(Role $role): void
    {
        $userIds = User::role($role->name)->pluck('id');

        if ($userIds->isEmpty()) {
            return;
        }

        // يحذف الـ sessions من قاعدة البيانات مباشرة
        // لو session driver = file أو redis، الـ clearPermissionCache() كافي مع Spatie
        if (config('session.driver') === 'database') {
            DB::table('sessions')->whereIn('user_id', $userIds)->delete();
        }
    }

    /**
     * مسح cache الصلاحيات
     */
    private function clearPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
