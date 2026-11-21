<?php

namespace App\Http\Controllers\Competition;

use App\Http\Controllers\Controller;
use App\Http\Requests\Competition\StoreCompetitionQuestionRequest;
use App\Models\Competition;
use App\Models\CompetitionQuestion;
use App\Models\Surah;
use Illuminate\Http\Request;

class CompetitionLevelQuestionController extends Controller
{
    public function index(Competition $competition, Request $request)
    {
        $this->authorize('view level questions');

        $levels = $competition->competitionLevels()->with('level')->get();
        $activeLevelId = $request->get('level_id');

        $questions = collect();

        if ($activeLevelId) {
            $questions = CompetitionQuestion::query()
                ->where('competition_level_id', $activeLevelId)
                ->with('competitionExaminers.examiner.user')
                ->latest()
                ->get();
        }

        return view('competitions.level_questions.index_level_questions', [
            'competition'    => $competition,
            'levels'         => $levels,
            'questions'      => $questions,
            'activeLevelId'  => $activeLevelId,
        ]);
    }

    public function create(Competition $competition, Request $request)
    {
        $this->authorize('create level questions');

        return view('competitions.level_questions.create_level_question', [
            'competition'     => $competition,
            'levels'          => $competition->competitionLevels()->with('level')->get(), // حسب علاقتك
            'surahs'          => Surah::orderBy('number')->get(['id', 'number', 'name_arabic']),
            'selectedLevelId' => $request->get('level_id'),
        ]);
    }

    public function store(StoreCompetitionQuestionRequest $request, Competition $competition)
    {
        $this->authorize('create level questions');

        $levelId = $request->get('competition_level_id');

        $belongsToCompetition = $competition->competitionLevels()
            ->where('id', $levelId)
            ->exists();

        abort_unless($belongsToCompetition, 403, 'هذا المستوى غير تابع لهذه المسابقة.');

        $data = $request->validated();
        $data['competition_level_id'] = $levelId;
        $data['competition_examiner_id'] = null;

        CompetitionQuestion::create($data);

        return redirect()
            ->route('competitions.level-questions', [$competition, 'level_id' => $levelId])
            ->with('success', 'تم إضافة السؤال بنجاح.');
    }

    public function show(Competition $competition, CompetitionQuestion $question)
    {
        $this->authorize('view level questions');

        $this->ensureQuestionBelongsToCompetition($competition, $question);

        $question->load([
            'competitionLevel.level',
            'competitionExaminers.examiner.user',
            'memorizationFromSurah',
            'memorizationToSurah',
        ]);
        
        return view('competitions.level_questions.show_level_question', [
            'competition' => $competition,
            'question'    => $question,
        ]);
    }

    public function edit(Competition $competition, CompetitionQuestion $question)
    {
        $this->authorize('edit level questions');

        $this->ensureQuestionBelongsToCompetition($competition, $question);

        return view('competitions.level_questions.edit_level_question', [
            'competition' => $competition,
            'levels'      => $competition->competitionLevels()->with('level')->get(),
            'surahs'      => Surah::orderBy('number')->get(['id', 'number', 'name_arabic']),
            'question'    => $question,
        ]);
    }
    public function update(StoreCompetitionQuestionRequest $request, Competition $competition, CompetitionQuestion $question)
    {
        $this->authorize('edit level questions');

        $this->ensureQuestionBelongsToCompetition($competition, $question);

        $levelId = $request->get('competition_level_id');

        $belongsToCompetition = $competition->competitionLevels()
            ->where('id', $levelId)
            ->exists();

        abort_unless($belongsToCompetition, 403, 'هذا المستوى غير تابع لهذه المسابقة.');

        $data = $request->validated();
        $data['competition_level_id'] = $levelId;

        $question->update($data);

        return redirect()
            ->route('competitions.level-questions', [$competition, 'level_id' => $levelId])
            ->with('success', 'تم تحديث السؤال بنجاح.');
    }

    public function destroy(Competition $competition, CompetitionQuestion $question)
    {
        $this->authorize('delete level questions');

        $this->ensureQuestionBelongsToCompetition($competition, $question);

        $levelId = $question->competition_level_id;
        $question->delete();

        return redirect()
            ->route('competitions.level-questions', [$competition, 'level_id' => $levelId])
            ->with('success', 'تم حذف السؤال بنجاح.');
    }

    /**
     * التأكد إن السؤال تابع فعلاً لهذه المسابقة (منع الوصول لأسئلة مسابقات أخرى).
     */
    private function ensureQuestionBelongsToCompetition(Competition $competition, CompetitionQuestion $question): void
    {
        $belongs = $competition->competitionLevels()
            ->where('id', $question->competition_level_id)
            ->exists();

        abort_unless($belongs, 404);
    }
}
