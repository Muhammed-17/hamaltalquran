<?php

namespace App\Http\Controllers\Examiner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Examiner\StoreCompetitionAnswerRequest;
use App\Models\CompetitionAnswer;
use App\Models\CompetitionExaminer;
use App\Models\CompetitionLevel;
use App\Models\CompetitionParticipant;
use App\Models\CompetitionQuestion;
use App\Models\CompetitionResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Models\Scopes\CenterScope;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Class CompetitionExamController
 *
 * يدير شاشة الاختبار (سؤال واحد في كل مرة) وشاشة المراجعة النهائية.
 *
 * ملحوظة: يدعم وضعين —
 * 1) مختبر (examiner): يرى أسئلته المسندة إليه فقط ضمن competition_examiner_id خاص به.
 * 2) أدمن (لا يملك سجل examiner): يرى كل أسئلة المستوى مجتمعة (كل المختبرين معاً)،
 *    وكل إجابة تُحفظ تحت المختبر الفعلي المسؤول عن السؤال (وليس تحت المستخدم الحالي).
 */
class CompetitionExamController extends Controller
{
    /**
     * عرض شاشة السؤال الحالي للمشارك (سؤال واحد فقط).
     */
    public function show(Request $request, CompetitionParticipant $participant): View|RedirectResponse
    {
        $this->authorize('examine', $participant);

        $examiner = auth()->user()->examiner;
        $isAdminMode = !$examiner;

        if ($isAdminMode) {
            $questions = CompetitionQuestion::query()
                ->where('competition_level_id', $participant->competition_level_id)
                ->orderBy('id')
                ->get();
        } else {
            $competitionExaminer = $this->resolveCompetitionExaminer($participant->competitionLevel, $examiner);

            $questions = CompetitionQuestion::query()
                ->where('competition_level_id', $participant->competition_level_id)
                ->whereHas('competitionExaminers', fn($q) => $q->where('competition_examiners.id', $competitionExaminer->id))
                ->orderBy('id')
                ->get();
        }

        if ($questions->isEmpty()) {
            return redirect()
                ->route($isAdminMode ? 'admin.participants.index' : 'examiner.participants.index', $participant->competition_level_id)
                ->with('error', 'لا توجد أسئلة مسندة إليك لهذا المستوى.');
        }

        $answeredIdsQuery = CompetitionAnswer::query()
            ->where('competition_participant_id', $participant->id);

        if (!$isAdminMode) {
            $answeredIdsQuery->where('competition_examiner_id', $competitionExaminer->id);
        }

        $answeredIds = $answeredIdsQuery->pluck('competition_question_id')->toArray();

        $requestedQuestionId = $request->query('question');

        $currentQuestion = $requestedQuestionId
            ? $questions->firstWhere('id', (int) $requestedQuestionId)
            : $questions->first(fn($q) => !in_array($q->id, $answeredIds));

        if (!$currentQuestion) {
            if ($requestedQuestionId) {
                abort(404);
            }

            return redirect()->route('examiner.exam.review', $participant->id);
        }

        $existingAnswer = CompetitionAnswer::query()
            ->where('competition_participant_id', $participant->id)
            ->where('competition_question_id', $currentQuestion->id)
            ->first();

        $index = $questions->search(fn($q) => $q->id === $currentQuestion->id);

        $previousQuestion = $index > 0 ? $questions[$index - 1] : null;

        return view('examiner_accounts.exam.show', [
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
     * حفظ تقييم السؤال الحالي.
     */
    public function store(StoreCompetitionAnswerRequest $request, CompetitionParticipant $participant): RedirectResponse
    {
        $examiner = auth()->user()->examiner;
        $isAdminMode = !$examiner;

        $question = CompetitionQuestion::findOrFail($request->competition_question_id);

        // السؤال يجب أن ينتمي إلى مستوى هذا المشارك نفسه — لا مستوى آخر
        abort_if(
            (int) $question->competition_level_id !== (int) $participant->competition_level_id,
            422,
            'هذا السؤال لا ينتمي إلى مستوى هذا المشارك.'
        );

        if ($isAdminMode) {
            // احفظ الإجابة تحت المختبر الفعلي المسؤول عن هذا السؤال، وليس تحت الأدمن نفسه
            $competitionExaminer = $question->competitionExaminers()->first();

            abort_if(!$competitionExaminer, 422, 'هذا السؤال غير مسند لأي مختبر بعد.');
        } else {
            $competitionExaminer = $this->resolveCompetitionExaminer($participant->competitionLevel, $examiner);

            // والمختبر لا يقيّم إلا الأسئلة المطالَب بها باسمه في هذا المستوى
            $claimed = $question->competitionExaminers()
                ->where('competition_examiners.id', $competitionExaminer->id)
                ->exists();

            abort_unless($claimed, 403, 'هذا السؤال غير مسند إليك.');
        }

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
                ->route('examiner.exam.review', $participant->id)
                ->with('success', 'تم حفظ التقييم بنجاح.');
        }

        if ($request->action === 'save') {
            return redirect()
                ->route('examiner.exam.show', $participant->id)
                ->with('success', 'تم حفظ التقييم بنجاح.');
        }

        return redirect()
            ->route('examiner.exam.show', $participant->id);
    }

    /**
     * شاشة مراجعة الاختبار قبل اعتماد النتيجة.
     */
    public function review(CompetitionParticipant $participant): View
    {
        $this->authorize('review', $participant);

        $examiner = auth()->user()->examiner;
        $isAdminMode = !$examiner;

        if ($isAdminMode) {
            $totalQuestions = CompetitionQuestion::query()
                ->where('competition_level_id', $participant->competition_level_id)
                ->count();

            $answers = CompetitionAnswer::query()
                ->where('competition_participant_id', $participant->id)
                ->with('competitionQuestion')
                ->get();
        } else {
            $competitionExaminer = $this->resolveCompetitionExaminer($participant->competitionLevel, $examiner);

            $totalQuestions = CompetitionQuestion::query()
                ->where('competition_level_id', $participant->competition_level_id)
                ->whereHas('competitionExaminers', fn($q) => $q->where('competition_examiners.id', $competitionExaminer->id))
                ->count();

            $answers = CompetitionAnswer::query()
                ->where('competition_participant_id', $participant->id)
                ->where('competition_examiner_id', $competitionExaminer->id)
                ->with('competitionQuestion')
                ->get();
        }

        $answeredCount = $answers->where('answered', true)->count();
        $unansweredCount = $answers->where('answered', false)->count();

        return view('examiner_accounts.exam.review', compact(
            'participant',
            'totalQuestions',
            'answeredCount',
            'unansweredCount',
            'answers'
        ));
    }

    /**
     * اعتماد النتيجة النهائية للمشارك من قبل المختبر.
     *
     * ملحوظة مهمة: النتيجة النهائية (CompetitionResult) هي سجل واحد لكل مشارك
     * (وليس لكل مختبر)، لأن المشارك قد يُقيَّم بواسطة أكثر من مختبر (كل مختبر
     * مسؤول عن مجموعة أسئلة مختلفة). لذلك يجب أن يكون total_score هو مجموع
     * درجات كل المختبرين مجتمعين، وليس درجات المختبر الحالي فقط — وإلا فإن
     * كل عملية finalize() ستكتب فوق نتيجة المختبرين الآخرين وتفقد تقييمهم.
     */
    public function finalize(CompetitionParticipant $participant): RedirectResponse
    {
        $this->authorize('examine', $participant);

        $examiner = auth()->user()->examiner;
        $isAdminMode = !$examiner;

        // تأكيد وجود تقييمات قبل السماح بالاعتماد
        $hasAnswersQuery = CompetitionAnswer::query()
            ->where('competition_participant_id', $participant->id);

        if (!$isAdminMode) {
            $competitionExaminer = $this->resolveCompetitionExaminer($participant->competitionLevel, $examiner);
            $hasAnswersQuery->where('competition_examiner_id', $competitionExaminer->id);
        }

        abort_if(!$hasAnswersQuery->exists(), 422, 'لا يمكن اعتماد نتيجة بدون تقييمات.');

        // المجموع الكلي = كل إجابات كل المختبرين لهذا المشارك (وليس مختبر واحد فقط)
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
            ->route($isAdminMode ? 'admin.participants.index' : 'examiner.participants.index', $participant->competition_level_id)
            ->with('success', 'تم اعتماد النتيجة بنجاح.');
    }

    /**
     * التحقق من صلاحية المختبر على مستوى معيّن.
     */
    protected function authorizeLevel(CompetitionLevel $competitionLevel): void
    {
        $examiner = auth()->user()->examiner;
        abort_if(!$examiner, 403);

        $exists = $competitionLevel->competitionExaminerLevels()
            ->whereHas('competitionExaminer', fn($q) => $q->where('examiner_id', $examiner->id))
            ->exists();

        abort_unless($exists, 403, 'غير مصرح لك بالوصول لهذا المستوى.');
    }

    /**
     * جلب سجل CompetitionExaminer المرتبط بالمستوى والمختبر الحالي.
     */
    protected function resolveCompetitionExaminer(CompetitionLevel $competitionLevel, $examiner): CompetitionExaminer
    {
        return CompetitionExaminer::query()
            ->where('competition_id', $competitionLevel->competition_id)
            ->where('examiner_id', $examiner->id)
            ->firstOrFail();
    }

    /**
     * حساب درجة السؤال بعد خصم أخطاء الحفظ والتشكيل.
     *
     * يُخصم 1 درجة لكل خطأ حفظ، و0.5 درجة لكل خطأ تشكيل،
     * بحد أدنى صفر.
     */
    protected function calculateScore(float $questionScore, int $memorizationMistakes, int $tashkeelMistakes): float
    {
        $deduction = ($memorizationMistakes * 1) + ($tashkeelMistakes * 0.5);

        return max(0, round($questionScore - $deduction, 2));
    }

    /**
     * قائمة المشاركين ضمن مستوى معيّن.
     */
    public function participants(Request $request, CompetitionLevel $competitionLevel): View
    {
        $this->authorizeLevel($competitionLevel);

        $examiner = auth()->user()->examiner;
        $competitionExaminer = $this->resolveCompetitionExaminer($competitionLevel, $examiner);

        $totalQuestions = CompetitionQuestion::query()
            ->where('competition_level_id', $competitionLevel->id)
            ->whereHas('competitionExaminers', fn($q) => $q->where('competition_examiners.id', $competitionExaminer->id))
            ->count();

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

        $participants->getCollection()->transform(function ($participant) use ($competitionExaminer, $totalQuestions) {
            $answeredCount = $participant->competitionAnswers()
                ->where('competition_examiner_id', $competitionExaminer->id)
                ->count();

            if ($participant->competitionResult) {
                $participant->exam_status = 'completed';
            } elseif ($answeredCount === 0) {
                $participant->exam_status = 'registered';
            } else {
                $participant->exam_status = 'testing';
            }

            return $participant;
        });

        return view('examiner_accounts.participants.index', compact('competitionLevel', 'participants'));
    }
}
