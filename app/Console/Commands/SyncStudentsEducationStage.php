<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Services\EducationStageExclusionService;
use Illuminate\Console\Command;

class SyncStudentsEducationStage extends Command
{
    protected $signature = 'students:sync-education-stage
        {--dry-run : عرض التغييرات المتوقعة بدون حفظها فعليًا}';

    protected $description = 'تحديث المرحلة الدراسية والصف ونوع التعليم لكل الطلاب تلقائيًا بناءً على سنهم';

    public function handle(EducationStageExclusionService $exclusionService): int
    {
        $thresholds = collect(config('education_stages.thresholds'))->sortKeysDesc();
        $dryRun     = (bool) $this->option('dry-run');

        $updated = 0;
        $skipped = 0;
        $touched = 0;

        Student::query()
            ->whereNotNull('date_of_birth')
            ->select(['id', 'name', 'date_of_birth', 'educational_stage', 'school_grade', 'education_type'])
            ->chunkById(200, function ($students) use ($thresholds, $exclusionService, $dryRun, &$updated, &$skipped, &$touched) {
                foreach ($students as $student) {
                    $touched++;

                    $eval = $exclusionService->evaluate($student);
                    if ($eval['skip']) {
                        $skipped++;
                        continue;
                    }

                    $age   = $student->date_of_birth->age;
                    $match = $thresholds->first(fn($v, $minAge) => $age >= $minAge, null);
                    if (!$match) {
                        $skipped++;
                        continue;
                    }

                    $changes = [];
                    if ($student->educational_stage?->value !== $match['educational_stage']) {
                        $changes['educational_stage'] = $match['educational_stage'];
                    }
                    if ($student->school_grade?->value !== $match['school_grade']) {
                        $changes['school_grade'] = $match['school_grade'];
                    }
                    if ($match['education_type'] !== null && $student->education_type !== $match['education_type']) {
                        $changes['education_type'] = $match['education_type'];
                    }

                    if (empty($changes)) {
                        continue;
                    }

                    if ($dryRun) {
                        $this->line("[DRY-RUN] #{$student->id} {$student->name}: " . json_encode($changes, JSON_UNESCAPED_UNICODE));
                    } else {
                        $student->forceFill($changes)->saveQuietly();
                    }

                    $updated++;
                }
            });

        $this->info("تم فحص {$touched} طالب — تحديث {$updated} — تجاهل {$skipped}" . ($dryRun ? ' (Dry Run)' : ''));

        return self::SUCCESS;
    }
}
