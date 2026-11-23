<?php

namespace App\Services;

use App\Models\Recommendation;
use App\Models\RecommendationTemplate;
use App\Models\StudentWeeklyFollowup;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class RecommendationService
{

    /**
     * Cache لكل قوالب التوصيات — تتحمل مرة واحدة فقط لكل request
     * بدل query منفصل لكل sentenceFor() call.
     */
    private ?Collection $templatesCache = null;

    // ═══════════════════════════════════════════════════════════════
    // Public API - Single follow-up (للاستخدام الفردي / update واحد)
    // ═══════════════════════════════════════════════════════════════

    public function generateAndStore(StudentWeeklyFollowup $followup): Recommendation
    {
        $this->generateAndStoreBatch(collect([$followup]));

        return $followup->recommendation()->first()
            ?? Recommendation::where('weekly_followup_id', $followup->id)->firstOrFail();
    }

    // ═══════════════════════════════════════════════════════════════
    // Public API - Batch (الاستخدام الأساسي من WeeklyFollowupService)
    // ═══════════════════════════════════════════════════════════════

    /**
     * يولّد ويخزّن التوصيات لمجموعة كاملة من الـ followups بأقل عدد queries ممكن.
     */
    public function generateAndStoreBatch(Collection $followups): void
    {
        if ($followups->isEmpty()) {
            return;
        }

        $followups = EloquentCollection::make($followups->all());

        $followups->loadMissing([
            'newMemorizations',
            'revisions',
            'oldMemorizations',
            'discipline',
            'tajweedAssessment',
            'foundationLevel',
            'educationalLessonAssessment',
            'student',
        ]);

        // 2) جلب كل بيانات الحضور لكل الطلاب في استعلام واحد
        $attendanceRates = $this->bulkAttendanceRates($followups);

        // 3) تحميل كل القوالب مرة واحدة في الذاكرة
        $templates = $this->templates();

        // 4) بناء نص التوصية لكل followup في الذاكرة (بدون أي query إضافي)
        $rows = [];
        $now = now();

        foreach ($followups as $followup) {
            $sections = $this->collectSectionLevels($followup, $attendanceRates);

            $sentences = collect($sections)
                ->map(fn($level, $category) => $this->sentenceFromCache($templates, $category, $level))
                ->filter()
                ->values();

            $generatedText = $sentences->isNotEmpty()
                ? $sentences->implode(' ')
                : 'لا توجد بيانات كافية لتوليد توصية لهذا الأسبوع.';

            $rows[] = [
                'weekly_followup_id'        => $followup->id,
                'generated_recommendation'  => $generatedText,
                'created_at'                => $now,
                'updated_at'                => $now,
            ];
        }

        // 5) حفظ كل التوصيات بستعلام upsert واحد
        //    يحافظ على supervisor_recommendation/supervisor_id/signed_at
        //    لأننا بنحدث عمود واحد فقط (generated_recommendation)
        Recommendation::upsert(
            $rows,
            ['weekly_followup_id'],           // unique key
            ['generated_recommendation', 'updated_at']  // الأعمدة المحدّثة فقط
        );
    }

    // ═══════════════════════════════════════════════════════════════
    // Bulk Attendance (بدل query منفصل لكل طالب)
    // ═══════════════════════════════════════════════════════════════

    /**
     * يرجع Collection: key = "{student_id}_{week_start}_{week_end}", value = rate %
     */
    private function bulkAttendanceRates(Collection $followups): Collection
    {
        $studentIds = $followups->pluck('student_id')->filter()->unique()->values();

        if ($studentIds->isEmpty()) {
            return collect();
        }

        // أقل وأكبر تاريخ في كل الـ batch عشان نجيب كل الحضور بستعلام واحد
        $minDate = $followups->min('week_start');
        $maxDate = $followups->max('week_end');

        $attendances = \App\Models\Attendance::whereIn('student_id', $studentIds)
            ->whereBetween('date', [$minDate, $maxDate])
            ->get(['student_id', 'date', 'status']);

        $rates = collect();

        foreach ($followups as $followup) {
            if (!$followup->student_id) {
                continue;
            }

            $key = "{$followup->student_id}_{$followup->week_start}_{$followup->week_end}";

            $studentAttendances = $attendances->filter(
                fn($a) =>
                $a->student_id === $followup->student_id
                    && $a->date->between($followup->week_start, $followup->week_end)
            );

            $total = $studentAttendances->count();

            if ($total === 0) {
                $rates[$key] = null;
                continue;
            }

            $present = $studentAttendances->where('status', \App\Enums\AttendanceStatus::Present)->count();
            $late    = $studentAttendances->where('status', \App\Enums\AttendanceStatus::Late)->count();

            $rates[$key] = round((($present + $late) / $total) * 100);
        }

        return $rates;
    }

    // ═══════════════════════════════════════════════════════════════
    // Templates Cache (بدل query لكل sentenceFor call)
    // ═══════════════════════════════════════════════════════════════

    private function templates(): Collection
    {
        return $this->templatesCache ??= RecommendationTemplate::all();
    }

    /**
     * بحث في الذاكرة بدل استعلام DB. عدّل الشرط حسب بنية RecommendationTemplate الفعلية.
     */
    private function sentenceFromCache(Collection $templates, string $category, ?string $level): ?string
    {
        if (empty($level)) {
            return null;
        }

        $template = $templates->first(fn($t) => $t->category === $category && $t->level === $level);

        return $template?->sentence;
    }

    // ═══════════════════════════════════════════════════════════════
    // Section Levels (بدون queries — كله من العلاقات المحمّلة مسبقًا)
    // ═══════════════════════════════════════════════════════════════

    private function collectSectionLevels(StudentWeeklyFollowup $followup, Collection $attendanceRates): array
    {
        $key = "{$followup->student_id}_{$followup->week_start}_{$followup->week_end}";
        $rate = $attendanceRates->get($key);

        return [
            'new_memorization'   => $followup->newMemorizations?->average_level?->value,
            'revision'           => $followup->revisions?->average_level?->value,
            'old_memorization'   => $followup->oldMemorizations?->average_level?->value,
            'discipline'         => $followup->discipline?->level?->value,
            'tajweed'            => $followup->tajweedAssessment?->level?->value,
            'foundation'         => $followup->foundationLevel?->level?->value,
            'educational_lesson' => $followup->educationalLessonAssessment?->level?->value,
            'attendance'         => $rate !== null ? $this->percentageToLevel($rate) : null,
        ];
    }

    private function percentageToLevel(float $rate): string
    {
        return \App\Enums\AssessmentLevel::forPercentage($rate)->value;
    }
}
