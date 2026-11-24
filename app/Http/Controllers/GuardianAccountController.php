<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Traits\HasCommonFilters;
use App\Http\Requests\GuardianAccounts\StoreGuardianAccountsRequest;
use App\Http\Requests\GuardianAccounts\UpdateGuardianAccountsRequest;


class GuardianAccountController extends Controller
{
    use HasCommonFilters;
    // ─────────────────────────────────────────
    public function index(Request $request)
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);

        $query = User::role('guardian')
            ->withCount('students')
            ->with('students:id,guardian_id,center_id');

        $this->applySearch($query, $request, ['name', 'email']);
        $this->applyStatus($query, $request);
        $this->applyCenter($query, $request, relation: 'students', param: 'center_id');
        $this->applySort($query, $request, allowed: ['name', 'email','status', 'created_at'], default: 'name');

        $guardians = $query->paginate(20)->withQueryString();

        $centers = \App\Models\Center::orderBy('name')->get(['id', 'name']);

        return view('guardian_accounts.index', compact('guardians', 'centers'));
    }
    // ─────────────────────────────────────────
    public function show(User $guardian)
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);
        abort_unless($guardian->hasRole('guardian'), 404);

        $guardian->load([
            'students:id,name,status,student_code,guardian_id',
            'center:id,name',
        ]);

        return view('guardian_accounts.show', compact('guardian'));
    }

    // ─────────────────────────────────────────
    public function create()
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);
        $centers = \App\Models\Center::orderBy('name')->get(['id', 'name']);
        return view('guardian_accounts.form', [
            'guardian' => new User(),
            'centers'  => $centers,
        ]);
    }

    // ─────────────────────────────────────────
    public function store(StoreGuardianAccountsRequest $request)
    {
        $data = $request->validated();

        $data['password'] = Hash::make($data['password']);
        $data['status']   = 'active';
        $data['email_verified_at'] = now();

        $guardian = User::create($data);
        $guardian->assignRole('guardian');

        return redirect()->route('guardians.index')
            ->with('success', 'تم إنشاء حساب ولي الأمر بنجاح ✓');
    }


    // ─────────────────────────────────────────
    public function edit(User $guardian)
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);
        abort_unless($guardian->hasRole('guardian'), 404);

        $guardian->load('students:id,name,status,student_code,guardian_id');
        $centers = \App\Models\Center::orderBy('name')->get(['id', 'name']);

        return view('guardian_accounts.form', compact('guardian', 'centers'));
    }

    // ─────────────────────────────────────────
    public function update(UpdateGuardianAccountsRequest $request, User $guardian)
    {
        abort_unless($guardian->hasRole('guardian'), 404);

        $data = $request->validated();

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $guardian->update($data);

        return redirect()->route('guardians.index')
            ->with('success', 'تم تحديث بيانات ولي الأمر بنجاح ✓');
    }

    // ─────────────────────────────────────────
    public function toggleStatus(User $guardian)
    {
        // $this->authorize('update', [$guardian, 'guardian']);
        abort_unless($guardian->hasRole('guardian'), 404);

        $guardian->update([
            'status' => $guardian->status === 'active' ? 'inactive' : 'active',
        ]);

        $msg = $guardian->status === 'active' ? 'تم تفعيل الحساب ✓' : 'تم تعطيل الحساب ✓';

        return redirect()->back()->with('success', $msg);
    }

    // ─────────────────────────────────────────
    public function destroy(User $guardian)
    {
        // $this->authorize('delete', [$guardian, 'guardian']);
        abort_unless($guardian->hasRole('guardian'), 404);

        if ($guardian->students()->exists()) {
            return redirect()->back()->with(
                'error',
                'لا يمكن حذف ولي الأمر لوجود طلاب مرتبطين به — قم بإلغاء الربط أولاً'
            );
        }

        $guardian->delete();

        return redirect()->route('guardians.index')->with('success', 'تم حذف الحساب بنجاح');
    }
}
