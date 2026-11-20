<?php

namespace App\Http\Controllers\Examiner;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionExaminer;
use Illuminate\View\View;

/**
 * Class ExaminerCompetitionController
 *
 * يعرض مسابقات المختبر ومستوياته داخل كل مسابقة.
 */
class ExaminerCompetitionController extends Controller
{
    /**
     * قائمة مسابقات المختبر.
     */
    public function index(): View
    {
        $examiner = auth()->user()->examiner;
        abort_if(!$examiner, 403);

        $competitions = Competition::query()
            ->where('status', 'active')
            ->whereHas('competitionExaminers', fn($q) => $q->where('examiner_id', $examiner->id))
            ->with(['competitionExaminers' => function ($q) use ($examiner) {
                $q->where('examiner_id', $examiner->id)
                    ->with('competitionExaminerLevels.competitionLevel.level');
            }])
            ->paginate(12);

        return view('examiner_accounts.competitions.index', compact('competitions'));
    }

    /**
     * عرض مستويات مسابقة معينة المكلف بها المختبر.
     */
    public function levels(Competition $competition): View
    {
        $examiner = auth()->user()->examiner;
        abort_if(!$examiner, 403);

        abort_if($competition->status !== 'active', 403, 'هذه المسابقة غير متاحة حالياً.');

        $competitionExaminer = CompetitionExaminer::query()
            ->where('competition_id', $competition->id)
            ->where('examiner_id', $examiner->id)
            ->firstOrFail();

        $levels = $competitionExaminer->competitionExaminerLevels()
            ->with([
                'competitionLevel.level',
                'competitionLevel.competitionParticipants.competitionResult',
            ])
            ->get()
            ->map(function ($cel) {
                $level = $cel->competitionLevel;
                $participants = $level->competitionParticipants;

                $completed = $participants->filter(fn($p) => $p->competitionResult !== null)->count();

                return (object) [
                    'competitionLevel'   => $level,
                    'participantsCount'  => $participants->count(),
                    'completedCount'     => $completed,
                    'testingCount'       => 0,
                    'registeredCount'    => $participants->count() - $completed,
                ];
            });

        return view('examiner_accounts.competitions.levels', compact('competition', 'levels'));
    }
}
