<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\CircleAssignmentHistory;
use App\Models\Subscription;
use App\Notifications\UnpaidSubscriptionNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class NotifyUnpaidSubscriptions extends Command
{
    protected $signature = 'notify:unpaid-subscriptions
                            {--force : Force send even if already notified today}
                            {--dry-run : Simulate without sending}';

    protected $description = 'Send notifications to guardians for students with unpaid subscription months, on the 7th, 15th, 20th, 25th, and last day of each month';

    public function handle(): int
    {
        $today = now()->day;
        $lastDayOfMonth = now()->endOfMonth()->day;
        $notifyDays = [7, 15, 20, 25, $lastDayOfMonth];

        if (!in_array($today, $notifyDays) && !$this->option('force')) {
            $this->info("اليوم ({$today}) مش يوم إرسال. أيام الإرسال: " . implode(', ', $notifyDays));
            return 0;
        }

        $force  = $this->option('force');
        $dryRun = $this->option('dry-run');

        $sent = 0;
        $skipped = 0;
        $noGuardian = 0;

        $students = Student::with('guardian')
            ->whereIn('status', [\App\Enums\StudentStatus::Enrolled, \App\Enums\StudentStatus::Stopped])
            ->whereHas('unpaidMonths', fn($q) => $q->where('unpaid_months_count', '>', 0))
            ->whereHas('guardian')
            ->get();

        $this->info("Checking {$students->count()} students with unpaid months...");
        $bar = $this->output->createProgressBar($students->count());
        $bar->start();

        foreach ($students as $student) {
            $bar->advance();

            $guardian = $student->guardian;
            if (!$guardian) {
                $noGuardian++;
                continue;
            }

            // منع الإرسال المكرر لنفس الطالب في نفس اليوم
            if (!$force) {
                $alreadyNotified = $guardian->notifications()
                    ->where('type', UnpaidSubscriptionNotification::class)
                    ->whereDate('created_at', today())
                    ->where('data->student_id', $student->id)
                    ->exists();

                if ($alreadyNotified) {
                    $skipped++;
                    continue;
                }
            }

            $unpaidMonths = $this->unpaidMonthNames($student);
            $unpaidMonthsCount = count($unpaidMonths);

            if ($unpaidMonthsCount === 0) {
                continue;
            }

            if ($dryRun) {
                $this->newLine();
                $this->line("  [DRY-RUN] Would notify: {$student->name} → {$guardian->name} ({$unpaidMonthsCount} months: " . implode('، ', $unpaidMonths) . ")");
                $sent++;
                continue;
            }

            try {
                $guardian->notify(new UnpaidSubscriptionNotification($student, $unpaidMonthsCount, null, $unpaidMonths));
                $sent++;
                $this->newLine();
                $this->info("  ✓ Sent: {$student->name} → {$guardian->name} ({$unpaidMonthsCount} months)");
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
        $this->line("   Skipped (already notified today): {$skipped}");
        $this->line("   No guardian: {$noGuardian}");

        return 0;
    }

    /**
     * أسماء الأشهر غير المدفوعة للطالب (مدفوع أو معفي يُستثنيان).
     */
    private function unpaidMonthNames(Student $student): array
    {
        $assignments = CircleAssignmentHistory::where('student_id', $student->id)
            ->orderBy('from_date')
            ->get(['from_date', 'to_date']);

        $currentMonth = now()->startOfMonth();

        $enrolledMonths = collect();
        foreach ($assignments as $assignment) {
            $start = $assignment->from_date->copy()->startOfMonth();
            $end   = ($assignment->to_date ?? now())->copy()->startOfMonth();
            if ($end->gt($currentMonth)) $end = $currentMonth->copy();
            $totalMonths = $start->diffInMonths($end) + 1;

            for ($i = 0; $i < $totalMonths; $i++) {
                $enrolledMonths->push($start->copy()->addMonths($i)->format('Y-m'));
            }
        }
        $enrolledMonths = $enrolledMonths->unique();

        $paidOrExemptSet = array_flip(
            $student->subscriptions()
                ->whereIn('status', \App\Enums\SubscriptionStatus::settled())
                ->pluck('month')
                ->map(fn($d) => Carbon::parse($d)->format('Y-m'))
                ->all()
        );

        return $enrolledMonths
            ->reject(fn($m) => isset($paidOrExemptSet[$m]))
            ->sort()
            ->map(fn($m) => Carbon::createFromFormat('Y-m', $m)->locale('ar')->isoFormat('MMMM YYYY'))
            ->values()
            ->all();
    }
}