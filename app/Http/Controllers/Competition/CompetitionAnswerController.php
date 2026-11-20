<?php

namespace App\Http\Controllers\Competition;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionAnswer;
use App\Models\CompetitionLevel;
use App\Models\CompetitionParticipant;
use App\Models\CompetitionQuestion;
use App\Models\CompetitionResult;
use App\Models\Scopes\CenterScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompetitionAnswerController extends Controller
{
    /**
     * قائمة كل المسابقات (بدون فلترة على مختبر معيّن).
     */
    public function competitions(): View
    {
        $this->authorize('examine competition participants');
        $competitions = Competition::query()
            ->withCount(['competitionLevels'])
            ->latest()
            ->paginate(12);

        return view('competitions.results.index', compact('competitions'));
    }

    /**
     * قائمة كل مستويات مسابقة معيّنة، مع إحصائيات المشاركين والمكتمل.
     */
    public function levels(Competition $competition): View
    {
        $this->authorize('examine competition participants');
        $levels = $competition->competitionLevels()
            ->with('level')
            ->withCount(['competitionParticipants as participantsCount'])
            ->get()
            ->map(function (CompetitionLevel $competitionLevel) {
                $competitionLevel->completedCount = $competitionLevel->competitionParticipants()
                    ->whereHas('competitionResult')
                    ->count();

                return $competitionLevel;
            });

        return view('competitions.results.level', compact('competition', 'levels'));
    }

    /**
     * قائمة المشاركين ضمن مستوى معيّن — عرض فقط، بدون إجراءات اختبار.
     */
    public function participants(Request $request, CompetitionLevel $competitionLevel): View
    {
        $this->authorize('examine competition participants');
        $participants = $competitionLevel->competitionParticipants()
            ->with([
                'student' => fn($query) => $query->withoutGlobalScope(CenterScope::class),
                'externalParticipant',
                'competitionResult',
            ])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->q;
                $query->where(function ($inner) use ($search) {
                    $inner->whereHas('student', fn($q) => $q->withoutGlobalScope(CenterScope::class)->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('externalParticipant', fn($q) => $q->where('name', 'like', "%{$search}%"));
                });
            })
            ->paginate(15)
            ->withQueryString();

        $participants->getCollection()->transform(function ($participant) {
            $answeredCount = $participant->competitionAnswers()->count();

            if ($participant->competitionResult) {
                $participant->exam_status = 'completed';
            } elseif ($answeredCount === 0) {
                $participant->exam_status = 'registered';
            } else {
                $participant->exam_status = 'testing';
            }
            $participant->is_manual_result = $participant->competitionResult && $answeredCount === 0;

            return $participant;
        });

        return view('competitions.results.participant', compact('competitionLevel', 'participants'));
    }

    /**
     * عرض تفاصيل نتيجة مشارك — إجاباته وتقييمات كل الأسئلة، بدون شرط اكتمال النتيجة.
     */
    public function result(CompetitionParticipant $participant): View
    {
        $this->authorize('examine competition participants');
        $totalQuestions = CompetitionQuestion::query()
            ->where('competition_level_id', $participant->competition_level_id)
            ->count();

        $answers = CompetitionAnswer::query()
            ->where('competition_participant_id', $participant->id)
            ->with(['competitionQuestion', 'competitionExaminer.examiner'])
            ->get();

        $answeredCount = $answers->where('answered', true)->count();
        $unansweredCount = $answers->where('answered', false)->count();

        $isManualResult = $participant->competitionResult && $answers->isEmpty();

        return view('competitions.results.result', compact(
            'participant',
            'totalQuestions',
            'answeredCount',
            'unansweredCount',
            'answers',
            'isManualResult'
        ));
    }

    /**
     * عرض فورم إدخال نتيجة نهائية يدويًا بدون المرور على أسئلة الاختبار.
     */
    public function manualResultForm(CompetitionParticipant $participant): View
    {
        $this->authorize('manage competitions');
        $this->authorize('examine', $participant);

        return view('competitions.results.manual', compact('participant'));
    }

    /**
     * حفظ نتيجة يدوية مباشرة في competition_results.
     *
     * تحذير: لو بدأ أي مختبر أو أدمن الاختبار بعد ذلك وقيّم أسئلة هذا
     * المشارك ثم اعتمد النتيجة عبر finalize()، فسيتم استبدال هذه القيمة
     * اليدوية تلقائيًا بمجموع درجات الأسئلة، لأن finalize() تعتمد فقط
     * على مجموع competition_answers ولا "تتذكر" وجود قيمة يدوية سابقة.
     */
    public function storeManualResult(Request $request, CompetitionParticipant $participant): RedirectResponse
    {
        $this->authorize('examine', $participant);

        $data = $request->validate([
            'total_score' => ['required', 'numeric', 'min:0'],
        ]);

        CompetitionResult::updateOrCreate(
            ['competition_participant_id' => $participant->id],
            ['total_score' => $data['total_score'], 'rank' => null]
        );

        return redirect()
            ->route('competitions.level-participants.index', $participant->competition_level_id)
            ->with('success', 'تم حفظ النتيجة يدويًا. تنبيه: إذا تم اختبار المشارك لاحقًا عبر الأسئلة واعتماد النتيجة، ستُستبدل هذه القيمة تلقائيًا.');
    }
}
