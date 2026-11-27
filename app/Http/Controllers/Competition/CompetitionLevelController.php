<?php

namespace App\Http\Controllers\Competition;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionLevel;
use App\Models\Level;
use Illuminate\Http\Request;

class CompetitionLevelController extends Controller
{
    /**
     * عرض المستويات المضافة فعلاً للمسابقة (index).
     */
    public function index(Competition $competition)
    {
        $this->authorize('view competitions');

        $competitionLevels = $competition->competitionLevels()
            ->with('level')
            ->withCount(['competitionQuestions', 'competitionParticipants', 'competitionExaminerLevels'])
            ->get();

        return view('.competitions.levels.levels', compact('competition', 'competitionLevels'));
    }

    /**
     * صفحة اختيار/إضافة مستويات جديدة للمسابقة.
     */
    public function edit(Competition $competition)
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);

        $levels = Level::orderBy('name')->get();
        $selectedLevelIds = $competition->levels()->pluck('levels.id')->toArray();

        return view('competitions.levels.levels_select', compact('competition', 'levels', 'selectedLevelIds'));
    }

    public function update(Request $request, Competition $competition)
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);

        $data = $request->validate([
            'level_ids'   => ['nullable', 'array'],
            'level_ids.*' => ['integer', 'exists:levels,id'],
        ]);

        $newLevelIds = $data['level_ids'] ?? [];

        $currentLevelIds = $competition->levels()->pluck('levels.id')->toArray();
        $removedLevelIds = array_diff($currentLevelIds, $newLevelIds);

        if (!empty($removedLevelIds)) {
            $blockedLevels = $competition->competitionLevels()
                ->whereIn('level_id', $removedLevelIds)
                ->withCount(['competitionQuestions', 'competitionParticipants', 'competitionExaminerLevels'])
                ->with('level')
                ->get()
                ->filter(fn ($competitionLevel) =>
                    $competitionLevel->competition_questions_count > 0
                    || $competitionLevel->competition_participants_count > 0
                    || $competitionLevel->competition_examiner_levels_count > 0
                );

            if ($blockedLevels->isNotEmpty()) {
                $names = $blockedLevels->pluck('level.name')->filter()->implode('، ');

                return back()
                    ->withInput()
                    ->with('error', "لا يمكن إزالة المستويات التالية لوجود بيانات مرتبطة بها (أسئلة أو مشاركين أو مختبرين): {$names}");
            }
        }

        $competition->levels()->sync($newLevelIds);

        return redirect()
            ->route('competitions.levels', $competition)
            ->with('success', 'تم تحديث مستويات المسابقة بنجاح.');
    }

    /**
     * حذف مستوى واحد من المسابقة (من صفحة الـ index مباشرة).
     */
    public function destroy(Competition $competition, CompetitionLevel $competitionLevel)
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);

        abort_unless($competitionLevel->competition_id === $competition->id, 404);

        $hasData = $competitionLevel->competitionQuestions()->exists()
            || $competitionLevel->competitionParticipants()->exists()
            || $competitionLevel->competitionExaminerLevels()->exists();

        if ($hasData) {
            return back()->with('error', 'لا يمكن حذف هذا المستوى لوجود بيانات مرتبطة به (أسئلة أو مشاركين أو مختبرين).');
        }

        $competitionLevel->delete();

        return redirect()
            ->route('competitions.levels', $competition)
            ->with('success', 'تم حذف المستوى بنجاح.');
    }
}