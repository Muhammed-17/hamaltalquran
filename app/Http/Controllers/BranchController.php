<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Center;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    /**
     * عرض جميع الفروع
     */
    public function index(Request $request)
    {
        $sortable = ['name', 'established_at', 'created_at'];
        $sort = in_array($request->get('sort'), $sortable) ? $request->get('sort') : 'created_at';
        $dir = $request->get('dir') === 'asc' ? 'asc' : 'desc';

        $branches = Branch::with(['center', 'supervisors.user'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $query->where('name', 'like', "%{$request->q}%");
            })
            ->when($request->filled('center_id'), function ($query) use ($request) {
                $query->where('center_id', $request->center_id);
            })
            ->orderBy($sort, $dir)
            ->paginate(10)
            ->withQueryString();

        $centers = Center::orderBy('name')->get();

        return view('branches.index', compact('branches', 'centers'));
    }

    /**
     * نموذج إنشاء فرع جديد
     */
    public function create()
    {
        $centers = Center::orderBy('name')->get();
        $supervisors = $this->eligibleSupervisors();

        return view('branches.create', compact('centers', 'supervisors'));
    }

    /**
     * حفظ فرع جديد
     */
    public function store(Request $request)
    {
        $validated = $this->validateBranch($request);
        $supervisorIds = $validated['supervisor_ids'] ?? [];
        unset($validated['supervisor_ids']);

        $branch = Branch::create($validated);
        $branch->supervisors()->sync($supervisorIds);

        return redirect()
            ->route('branches.index')
            ->with('success', 'تم إضافة الفرع بنجاح.');
    }

    /**
     * نموذج تعديل فرع
     */
    public function edit(Branch $branch)
    {
        $centers = Center::orderBy('name')->get();
        $supervisors = $this->eligibleSupervisors();
        $branch->load('supervisors');

        return view('branches.edit', compact('branch', 'centers', 'supervisors'));
    }

    /**
     * تحديث بيانات الفرع
     */
    public function update(Request $request, Branch $branch)
    {
        $validated = $this->validateBranch($request, $branch->id);
        $supervisorIds = $validated['supervisor_ids'] ?? [];
        unset($validated['supervisor_ids']);

        $branch->update($validated);
        $branch->supervisors()->sync($supervisorIds);

        return redirect()
            ->route('branches.index')
            ->with('success', 'تم تحديث الفرع بنجاح.');
    }

    /**
     * حذف الفرع
     */
    public function destroy(Branch $branch)
    {
        $branch->delete();

        return redirect()
            ->route('branches.index')
            ->with('success', 'تم حذف الفرع بنجاح.');
    }

    /**
     * قواعد التحقق المشتركة بين store و update
     */
    private function validateBranch(Request $request, $branchId = null): array
    {
        return $request->validate([
            'center_id' => ['required', 'exists:centers,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('branches', 'name')
                    ->where(fn($query) => $query->where('center_id', $request->center_id))
                    ->ignore($branchId),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'established_at' => ['required', 'date'],
            'supervisor_ids' => ['required', 'array', 'min:1'],
            'supervisor_ids.*' => ['exists:teachers,id'],
        ], [
            'center_id.required' => 'يجب اختيار المركز.',
            'center_id.exists' => 'المركز المحدد غير موجود.',
            'name.required' => 'اسم الفرع مطلوب.',
            'name.unique' => 'هذا الفرع موجود بالفعل في نفس المركز.',
            'established_at.required' => 'تاريخ الإنشاء مطلوب.',
            'established_at.date' => 'صيغة تاريخ الإنشاء غير صحيحة.',
            'supervisor_ids.required' => 'يجب اختيار مشرف واحد على الأقل.',
            'supervisor_ids.min' => 'يجب اختيار مشرف واحد على الأقل.',
        ]);
    }
    /**
     * المعلمون المسموح تعيينهم كمشرفين على فرع
     * (دورهم: مشرف / مدير فرع / مدير عام فقط)
     */
    private function eligibleSupervisors()
    {
        return Teacher::whereHas('user.roles', function ($query) {
            $query->whereIn('name', ['supervisor', 'manager', 'general_manager']);
        })
            ->with(['user.roles', 'branch'])
            ->join('users', 'teachers.user_id', '=', 'users.id')
            ->orderBy('users.name')
            ->select('teachers.*')
            ->get();
    }

    public function forCenter(Request $request)
    {
        $user   = auth()->user();
        $access = app(\App\Services\UserAccessService::class);

        $branches = $access->accessibleBranches($user)
            ->when($request->filled('center_id'), fn($q) => $q->where('center_id', $request->center_id))
            ->get(['id', 'name']);

        return response()->json(['branches' => $branches]);
    }
}
