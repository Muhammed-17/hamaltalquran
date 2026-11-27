<?php

namespace App\Http\Controllers;

use App\Http\Requests\Examiner\StoreExaminerRequest;
use App\Http\Requests\Examiner\UpdateExaminerRequest;
use App\Models\Examiner;
use App\Models\User;
use App\Models\Center;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Class ExaminerController
 *
 * Handles CRUD operations for competition examiners.
 */
class ExaminerController extends Controller
{
    // public function __construct()
    // {
    //     $this->authorizeResource(Examiner::class, 'examiner');
    // }

    public function index(Request $request): View
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);
        $sort = $request->get('sort', 'created_at');
        $dir  = $request->get('dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['created_at', 'phone'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        $examiners = Examiner::query()
            ->with('user')
            ->withCount('competitionExaminers')
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->q;
                $query->where(function ($inner) use ($search) {
                    $inner->whereHas('user', fn($q) => $q->where('name', 'like', "%{$search}%"))
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy($sort, $dir)
            ->paginate(15)
            ->withQueryString();

        return view('examiners.index', compact('examiners'));
    }

    public function create(): View
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);
        $centers = Center::orderBy('name')->get();

        return view('examiners.create', compact('centers'));
    }

    public function store(StoreExaminerRequest $request): RedirectResponse
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);
        $data = $request->validated();
        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => Hash::make($data['password']),
            'status'    => $data['status'],
            'center_id' => $data['center_id'] ?? null,
        ]);

        $user->assignRole('examiner'); // عدّل اسم الـ role حسب نظامك

        Examiner::create([
            'user_id'          => $user->id,
            'phone'            => $data['phone'] ?? null,
            'secondary_phone'  => $data['secondary_phone'] ?? null,
            'address'          => $data['address'] ?? null,
            'notes'            => $data['notes'] ?? null,
        ]);

        return redirect()
            ->route('examiners.index')
            ->with('success', 'تم إضافة المختبر بنجاح.');
    }

    public function show(Examiner $examiner): View
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);
        $examiner->load('user');
        $examiner->loadCount('competitionExaminers');

        return view('examiners.show', compact('examiner'));
    }

    public function edit(Examiner $examiner): View
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);
        $examiner->load('user');
        $centers = Center::orderBy('name')->get();

        return view('examiners.edit', compact('examiner', 'centers'));
    }
    
    public function update(UpdateExaminerRequest $request, Examiner $examiner): RedirectResponse
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);
        $data = $request->validated();

        $userData = [
            'name'      => $data['name'],
            'email'     => $data['email'],
            'status'    => $data['status'],
            'center_id' => $data['center_id'] ?? null,
        ];

        if (!empty($data['password'])) {
            $userData['password'] = Hash::make($data['password']);
        }

        $examiner->user->update($userData);

        $examiner->update([
            'phone'            => $data['phone'] ?? null,
            'secondary_phone'  => $data['secondary_phone'] ?? null,
            'address'          => $data['address'] ?? null,
            'notes'            => $data['notes'] ?? null,
        ]);

        return redirect()
            ->route('examiners.index')
            ->with('success', 'تم تحديث بيانات المختبر بنجاح.');
    }

    public function destroy(Examiner $examiner): RedirectResponse
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);
        if ($examiner->competitionExaminers()->exists()) {
            return redirect()
                ->route('examiners.index')
                ->with('error', 'لا يمكن حذف هذا المختبر لأنه مرتبط بمسابقات حالية.');
        }

        $examiner->delete();

        return redirect()
            ->route('examiners.index')
            ->with('success', 'تم حذف المختبر بنجاح.');
    }
}
