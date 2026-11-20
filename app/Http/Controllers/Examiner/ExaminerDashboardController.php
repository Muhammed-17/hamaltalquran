<?php

namespace App\Http\Controllers\Examiner;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionExaminer;
use App\Models\CompetitionExaminerLevel;
use App\Models\CompetitionParticipant;
use Illuminate\View\View;

/**
 * Class ExaminerDashboardController
 *
 * لوحة تحكم المختبر: ملخص عام بمسابقاته ونتائج تقدمه.
 */
class ExaminerDashboardController extends Controller
{
    /**
     * عرض لوحة التحكم.
     */
    public function index(): View
    {
        $examiner = auth()->user()->examiner;
        abort_if(!$examiner, 403, 'حسابك غير مرتبط بملف مختبر.');

        $competitionExaminerIds = CompetitionExaminer::query()
            ->where('examiner_id', $examiner->id)
            ->pluck('id');

        $levelIds = CompetitionExaminerLevel::query()
            ->whereIn('competition_examiner_id', $competitionExaminerIds)
            ->pluck('competition_level_id')
            ->unique();

        $totalParticipants = CompetitionParticipant::query()
            ->whereIn('competition_level_id', $levelIds)
            ->count();

        $testedParticipants = CompetitionParticipant::query()
            ->whereIn('competition_level_id', $levelIds)
            ->whereHas('competitionAnswers')
            ->count();

        $remainingParticipants = $totalParticipants - $testedParticipants;

        $competitions = Competition::query()
            ->where('status', 'active')
            ->whereHas('competitionExaminers', fn($q) => $q->where('examiner_id', $examiner->id))
            ->with(['competitionExaminers' => function ($q) use ($examiner) {
                $q->where('examiner_id', $examiner->id)
                    ->with('competitionExaminerLevels.competitionLevel.level');
            }])
            ->get();

        return view('examiner_accounts.dashboard', [
            'examiner'              => $examiner,
            'competitionsCount'     => $competitions->count(),
            'levelsCount'           => $levelIds->count(),
            'totalParticipants'     => $totalParticipants,
            'testedParticipants'    => $testedParticipants,
            'remainingParticipants' => $remainingParticipants,
            'competitions'          => $competitions,
        ]);
    }
}
