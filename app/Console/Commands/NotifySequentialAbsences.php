<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Notifications\SequentialAbsenceNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class NotifySequentialAbsences extends Command
{
    protected $signature = 'notify:sequential-absences
                            {--force : Force send even if already notified for this threshold}
                            {--dry-run : Simulate without sending}
                            {--month= : Month to check (Y-m format), defaults to current month}
                            {--min-absences=5 : Absence threshold step to trigger}';

    protected $description = 'Send notifications to guardians for students with 5+ absences in a month, repeating every 5 additional absences';

    /** رسائل مخصصة عند وصول الغياب المتتالي لعتبات معينة (بالأيام الدراسية) */
    private array $milestoneMessages = [
        5  => 'نود إحاطتكم بأن ابنكم %s غاب عن الحلقة لمدة أسبوع حتى الآن. يرجى التواصل مع الإدارة.',
        10 => 'نود إحاطتكم بأن ابنكم %s غاب عن الحلقة لمدة أسبوعين متتاليين حتى الآن. يرجى التواصل مع الإدارة.',
        15 => 'نود إحاطتكم بأن ابنكم %s غاب عن الحلقة لمدة ثلاثة أسابيع متتالية حتى الآن. يرجى التواصل الفوري مع الإدارة.',
        20 => 'نود إحاطتكم بأن ابنكم %s غاب عن الحلقة لمدة أربعة أسابيع متتالية حتى الآن. يرجى التواصل الفوري مع الإدارة لمعرفة سبب الغياب.',
    ];

    public function handle(): int
    {
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');
        $step = (int) $this->option('min-absences');

        $month = $this->option('month') ?? now()->format('Y-m');
        $startOfMonth = Carbon::parse($month . '-01')->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        $sent = 0;
        $skipped = 0;
        $noGuardian = 0;

        $students = Student::with([
            'attendances' => fn($q) => $q
                ->whereBetween('date', [$startOfMonth, $endOfMonth])
                ->orderBy('date'),
            'guardian',
        ])
            ->where('status', '!=', \App\Enums\StudentStatus::Stopped)
            ->whereHas('guardian')
            ->get();
        $this->info("Checking {$students->count()} students for absences in {$month} (step every {$step} absences)...");
        $this->newLine();

        $bar = $this->output->createProgressBar($students->count());
        $bar->start();

        foreach ($students as $student) {
            $bar->advance();


            $absenceDays = $this->consecutiveAbsenceStreak($student);

            if ($absenceDays < $step) {
                continue;
            }

            $guardian = $student->guardian;

            if (!$guardian) {
                $noGuardian++;
                continue;
            }

            // آخر عتبة تم الإشعار عليها فعلياً هذا الشهر
            $lastNotifiedCount = $this->lastNotifiedAbsenceCount($guardian, $student, $month);

            // العتبة الحالية اللي المفروض نبعت عندها (أقرب مضاعف لـ step وصله الطالب)
            // ── فحص غياب الشهر بالكامل (قبل نهاية الشهر بـ 3 أيام) ──
            $totalClassDaysSoFar = $student->attendances->count();
            $isNearMonthEnd = now()->day >= ($endOfMonth->day - 2);
            $isFullMonthAbsent = $totalClassDaysSoFar > 0
                && $absenceDays === $totalClassDaysSoFar
                && $isNearMonthEnd;

            if ($isFullMonthAbsent) {
                if (!$force && $this->alreadyNotifiedFullMonth($guardian, $student, $month)) {
                    $skipped++;
                    continue;
                }

                $fullMonthMessage = 'نأسف لإبلاغكم أن ابنكم ' . $student->name . ' غاب عن الحلقة طوال هذا الشهر بالكامل دون أي حضور. يرجى التواصل العاجل مع الإدارة.';

                if ($dryRun) {
                    $this->newLine();
                    $this->line("  [DRY-RUN] Would notify (FULL MONTH): {$student->name} → {$guardian->name}");
                    $sent++;
                    continue;
                }

                try {
                    $guardian->notify(new SequentialAbsenceNotification($student, $absenceDays, $fullMonthMessage, true));
                    $sent++;
                    $this->newLine();
                    $this->info("  ✓ Sent (FULL MONTH): {$student->name} → {$guardian->name}");
                } catch (\Exception $e) {
                    $this->newLine();
                    $this->error("  ✗ Failed: {$student->name} - {$e->getMessage()}");
                }
                continue;
            }

            // العتبة الحالية اللي المفروض نبعت عندها (أقرب مضاعف لـ step وصله الطالب)
            $currentThreshold = intdiv($absenceDays, $step) * $step;
            $lastThreshold = intdiv($lastNotifiedCount, $step) * $step;

            if (!$force && $currentThreshold <= $lastThreshold) {
                $skipped++;
                continue;
            }

            $message = isset($this->milestoneMessages[$currentThreshold])
                ? sprintf($this->milestoneMessages[$currentThreshold], $student->name)
                : null;

            if ($dryRun) {
                $this->newLine();
                $this->line("  [DRY-RUN] Would notify: {$student->name} → {$guardian->name} ({$absenceDays} absence days, threshold {$currentThreshold})");
                $sent++;
                continue;
            }

            try {
                $guardian->notify(new SequentialAbsenceNotification($student, $absenceDays, $message));
                $sent++;
                $this->newLine();
                $this->info("  ✓ Sent: {$student->name} → {$guardian->name} ({$absenceDays} absence days)");
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("  ✗ Failed: {$student->name} - {$e->getMessage()}");
            }
        }

        $bar->finish();
        $this->newLine(2);

        $label = $dryRun ? 'Would send' : 'Sent';
        $this->info("📊 Summary:");
        $this->line("   {$label}: {$sent}");
        $this->line("   Skipped (already notified for this threshold): {$skipped}");
        $this->line("   No guardian: {$noGuardian}");

        return 0;
    }

    /**
     * أعلى عدد غياب تم إرسال إشعار عنه فعلياً لهذا الطالب هذا الشهر.
     * ترجع 0 لو مفيش أي إشعار اتبعت خالص.
     */
    private function lastNotifiedAbsenceCount($guardian, Student $student, string $month): int
    {
        $notifications = $guardian->notifications()
            ->where('type', SequentialAbsenceNotification::class)
            ->whereJsonContains('data->student_id', $student->id)
            ->whereYear('created_at', Carbon::parse($month . '-01')->year)
            ->whereMonth('created_at', Carbon::parse($month . '-01')->month)
            ->get();

        $max = 0;

        foreach ($notifications as $notification) {
            $data = $notification->data;
            $count = (int) ($data['absence_days'] ?? 0);
            $max = max($max, $count);
        }

        return $max;
    }

    /**
     * هل سبق إرسال إشعار "غياب الشهر بالكامل" لهذا الطالب هذا الشهر؟
     */
    private function alreadyNotifiedFullMonth($guardian, Student $student, string $month): bool
    {
        return $guardian->notifications()
            ->where('type', SequentialAbsenceNotification::class)
            ->whereJsonContains('data->student_id', $student->id)
            ->whereJsonContains('data->is_full_month', true)
            ->whereYear('created_at', Carbon::parse($month . '-01')->year)
            ->whereMonth('created_at', Carbon::parse($month . '-01')->month)
            ->exists();
    }

    /**
     * أطول سلسلة غياب متتالية منتهية بآخر يوم حضور مسجل للطالب.
     * أي يوم "حاضر" بيصفّر العداد. الأيام اللي مفيش لها سجل أصلاً
     * (زي الخميس والجمعة، الإجازة الأسبوعية) مبتأثرش على التسلسل.
     */
    private function consecutiveAbsenceStreak(Student $student): int
    {
        $streak = 0;

        foreach ($student->attendances as $attendance) {
            if ($attendance->status === \App\Enums\AttendanceStatus::Absent) {
                $streak++;
            } else {
                $streak = 0;
            }
        }

        return $streak;
    }
}
