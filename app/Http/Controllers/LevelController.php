<?php

namespace App\Http\Controllers;

use App\Http\Requests\Level\StoreLevelRequest;
use App\Http\Requests\Level\UpdateLevelRequest;
use App\Models\Level;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Class LevelController
 *
 * Handles CRUD operations for competition levels.
 */
class LevelController extends Controller
{

    public function index(Request $request): View
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);

        $sort = $request->get('sort', 'name');
        $dir  = $request->get('dir', 'asc') === 'desc' ? 'desc' : 'asc';

        $allowedSorts = ['name', 'created_at'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'name';
        }

        $levels = Level::query()
            ->withCount('competitionLevels')
            ->when($request->filled('q'), function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->q . '%');
            })
            ->orderBy($sort, $dir)
            ->paginate(15)
            ->withQueryString();

        return view('levels.index', compact('levels'));
    }

    public function create(): View
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);

        return view('levels.create');
    }

    public function store(StoreLevelRequest $request): RedirectResponse
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);

        Level::create($request->validated());

        return redirect()
            ->route('levels.index')
            ->with('success', 'تم إنشاء المستوى بنجاح.');
    }

    public function show(Level $level): View
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);

        $level->loadCount('competitionLevels');

        return view('levels.show', compact('level'));
    }

    public function edit(Level $level): View
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);

        return view('levels.edit', compact('level'));
    }

    public function update(UpdateLevelRequest $request, Level $level): RedirectResponse
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);

        $level->update($request->validated());

        return redirect()
            ->route('levels.index')
            ->with('success', 'تم تحديث المستوى بنجاح.');
    }

    public function destroy(Level $level): RedirectResponse
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);

        if ($level->competitionLevels()->exists()) {
            return redirect()
                ->route('levels.index')
                ->with('error', 'لا يمكن حذف هذا المستوى لأنه مرتبط بمسابقات حالية.');
        }

        $level->delete();

        return redirect()
            ->route('levels.index')
            ->with('success', 'تم حذف المستوى بنجاح.');
    }
}