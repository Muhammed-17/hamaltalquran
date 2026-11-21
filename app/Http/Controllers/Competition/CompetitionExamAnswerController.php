<?php

namespace App\Http\Controllers\Competition;

use App\Http\Controllers\Controller;
use App\Http\Requests\Examiner\StoreCompetitionAnswerRequest;
use App\Models\CompetitionAnswer;
use App\Models\CompetitionParticipant;
use App\Models\CompetitionQuestion;
use App\Models\CompetitionResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Class CompetitionExamAdminController
 *
 * نسخة أدمن مستقلة تمامًا عن CompetitionExamController الخاص بالمختبر.
 * الأدمن هنا يرى كل أسئلة المستوى مجتمعة (كل المختبرين معاً)، وكل إجابة
 * تُحفظ تحت المختبر الفعلي المسؤول عن السؤال (وليس تحت الأدمن نفسه)،
 * حتى تبقى الإحصائيات ونتيجة كل مختبر صحيحة.
 */
class CompetitionExamAnswerController extends Controller
{
    /**
     * عرض شاشة السؤال الحالي للمشارك (سؤال واحد فقط) — كل أسئلة المستوى.
     */
    public function show(Request $request, CompetitionParticipant $participant): View|RedirectResponse
    {
        $this->authorize('examine competition participants');

        $questions = CompetitionQuestion::query()
            ->where('competition_level_id', $participant->competition_level_id)
            ->orderBy('id')
            ->get();

        if ($questions->isEmpty()) {
            return redirect()
                ->route('competitions.level-participants.index', $participant->competition_level_id)
                ->with('error', 'لا توجد أسئلة لهذا المستوى.');
        }

        $answeredIds = CompetitionAnswer::query()
            ->where('competition_participant_id', $participant->id)
            ->pluck('competition_question_id')
            ->toArray();

        $requestedQuestionId = $request->query('question');

        $currentQuestion = $requestedQuestionId
            ? $questions->firstWhere('id', (int) $requestedQuestionId)
            : $questions->first(fn($q) => !in_array($q->id, $answeredIds));

        if (!$currentQuestion) {
            if ($requestedQuestionId) {
                abort(404);
            }

            return redirect()->route('competitions.exam.review', $participant->id);
        }

        $existingAnswer = CompetitionAnswer::query()
            ->where('competition_participant_id', $participant->id)
            ->where('competition_question_id', $currentQuestion->id)
            ->first();

        $index = $questions->search(fn($q) => $q->id === $currentQuestion->id);

        $previousQuestion = $index > 0 ? $questions[$index - 1] : null;

        return view('competitions.results.show', [
            'participant'      => $participant,
            'question'         => $currentQuestion,
            'existingAnswer'   => $existingAnswer,
            'questionIndex'    => $index + 1,
            'totalQuestions'   => $questions->count(),
            'answeredCount'    => count($answeredIds),
            'isLast'           => $index === $questions->count() - 1,
            'previousQuestion' => $previousQuestion,
        ]);
    }

    /**
     * حفظ تقييم السؤال الحالي — يُحفظ تحت المختبر الفعلي المسؤول عن السؤال.
     */
    public function store(StoreCompetitionAnswerRequest $request, CompetitionParticipant $participant): RedirectResponse
    {
        $this->authorize('examine competition participants');
        $question = CompetitionQuestion::findOrFail($request->competition_question_id);

        // السؤال يجب أن ينتمي إلى مستوى هذا المشارك نفسه — لا مستوى آخر
        abort_if(
            (int) $question->competition_level_id !== (int) $participant->competition_level_id,
            422,
            'هذا السؤال لا ينتمي إلى مستوى هذا المشارك.'
        );

        $competitionExaminer = $question->competitionExaminers()->first();

        abort_if(!$competitionExaminer, 422, 'هذا السؤال غير مسند لأي مختبر بعد.');

        $answered = (bool) $request->answered;

        DB::transaction(function () use ($request, $participant, $competitionExaminer, $answered, $question) {
            $memorizationMistakes = $answered ? (int) $request->memorization_mistakes : 0;
            $tashkeelMistakes     = $answered ? (int) $request->tashkeel_mistakes : 0;

            CompetitionAnswer::updateOrCreate(
                [
                    'competition_participant_id' => $participant->id,
                    'competition_question_id'    => $request->competition_question_id,
                ],
                [
                    'competition_examiner_id' => $competitionExaminer->id,
                    'user_id'                 => null,
                    'answered'                => $answered,
                    'score'                   => $answered
                        ? $this->calculateScore($question->score, $memorizationMistakes, $tashkeelMistakes)
                        : 0,
                    'memorization_mistakes'   => $answered ? $memorizationMistakes : null,
                    'tashkeel_mistakes'       => $answered ? $tashkeelMistakes : null,
                    'notes'                   => $request->notes,
                ]
            );
        });

        if ($request->action === 'finish') {
            return redirect()
                ->route('competitions.exam.review', $participant->id)
                ->with('success', 'تم حفظ التقييم بنجاح.');
        }

        if ($request->action === 'save') {
            return redirect()
                ->route('competitions.exam.show', $participant->id)
                ->with('success', 'تم حفظ التقييم بنجاح.');
        }

        return redirect()->route('competitions.exam.show', $participant->id);
    }

    /**
     * شاشة مراجعة الاختبار قبل اعتماد النتيجة — كل إجابات كل المختبرين.
     */
    public function review(CompetitionParticipant $participant): View
    {
        $this->authorize('examine competition participants');

        $totalQuestions = CompetitionQuestion::query()
            ->where('competition_level_id', $participant->competition_level_id)
            ->count();

        $answers = CompetitionAnswer::query()
            ->where('competition_participant_id', $participant->id)
            ->with('competitionQuestion')
            ->get();

        $answeredCount = $answers->where('answered', true)->count();
        $unansweredCount = $answers->where('answered', false)->count();

        return view('competitions.results.review', compact(
            'participant',
            'totalQuestions',
            'answeredCount',
            'unansweredCount',
            'answers'
        ));
    }

    /**
     * اعتماد النتيجة النهائية للمشارك.
     *
     * total_score = مجموع درجات كل المختبرين مجتمعين (وليس مختبر واحد فقط)،
     * لأن النتيجة سجل واحد لكل مشارك بغض النظر عن عدد المختبرين.
     */
    public function finalize(CompetitionParticipant $participant): RedirectResponse
    {
        $this->authorize('examine competition participants');

        $hasAnswers = CompetitionAnswer::query()
            ->where('competition_participant_id', $participant->id)
            ->exists();

        abort_if(!$hasAnswers, 422, 'لا يمكن اعتماد نتيجة بدون تقييمات.');

        $totalScore = CompetitionAnswer::query()
            ->where('competition_participant_id', $participant->id)
            ->sum('score');

        DB::transaction(function () use ($participant, $totalScore) {
            CompetitionResult::updateOrCreate(
                ['competition_participant_id' => $participant->id],
                ['total_score' => $totalScore, 'rank' => null]
            );
        });

        return redirect()
            ->route('competitions.level-participants.index', $participant->competition_level_id)
            ->with('success', 'تم اعتماد النتيجة بنجاح.');
    }

    /**
     * حساب درجة السؤال بعد خصم أخطاء الحفظ والتشكيل.
     *
     * يُخصم 1 درجة لكل خطأ حفظ، و0.5 درجة لكل خطأ تشكيل، بحد أدنى صفر.
     */
    protected function calculateScore(float $questionScore, int $memorizationMistakes, int $tashkeelMistakes): float
    {
        $deduction = ($memorizationMistakes * 1) + ($tashkeelMistakes * 0.5);

        return max(0, round($questionScore - $deduction, 2));
    }
}
