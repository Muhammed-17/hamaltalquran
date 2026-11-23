<?php

namespace App\Jobs;

use App\Models\Student;
use App\Models\StudentUnpaidMonths;
use App\Models\CircleAssignmentHistory;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CalculateUnpaidMonths implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly ?int $studentId = null // null = كل الطلاب
    ) {}

    public function handle(): void
    {
        $query = Student::query()
            ->whereIn('status', [\App\Enums\StudentStatus::Enrolled, \App\Enums\StudentStatus::Stopped]);

        if ($this->studentId) {
            $query->where('id', $this->studentId);
        }

        $students = $query->pluck('id');

        // ─── جلب كل الـ assignments دفعة واحدة ───────────────
        $assignments = CircleAssignmentHistory::whereIn('student_id', $students)
            ->get(['student_id', 'from_date', 'to_date'])
            ->groupBy('student_id');

// ─── جلب كل الأشهر المدفوعة أو المعفاة دفعة واحدة ────
        $paidMonths = Subscription::whereIn('student_id', $students)
            ->whereIn('status', \App\Enums\SubscriptionStatus::settled())
            ->get(['student_id', 'month'])
            ->groupBy('student_id')
            ->map(fn($subs) => array_flip(
                $subs->map(fn($s) => Carbon::parse($s->month)->format('Y-m'))
                    ->unique()
                    ->all()
            ));

        $currentMonth = now()->startOfMonth();
        $now          = now();

        // ─── احسب لكل طالب ───────────────────────────────────
        $upsertData = $students->map(function ($studentId) use ($assignments, $paidMonths, $currentMonth, $now) {
            $studentAssignments = $assignments->get($studentId, collect());
            $paidSet            = $paidMonths->get($studentId, []);

            $unpaidCount = 0;

            foreach ($studentAssignments as $assignment) {
                $start       = $assignment->from_date->copy()->startOfMonth();
                $end = ($assignment->to_date?->copy() ?? $currentMonth->copy())->startOfMonth();
                if ($end->gt($currentMonth)) $end = $currentMonth->copy();

                $totalMonths = $start->diffInMonths($end) + 1;

                for ($i = 0; $i < $totalMonths; $i++) {
                    $monthKey = $start->copy()->addMonths($i)->format('Y-m');
                    if (!isset($paidSet[$monthKey])) {
                        $unpaidCount++;
                    }
                }
            }

            return [
                'student_id'          => $studentId,
                'unpaid_months_count' => $unpaidCount,
                'last_calculated_at'  => $now,
                'created_at'          => $now,
                'updated_at'          => $now,
            ];
        })->values()->all();

        // ─── upsert دفعة واحدة ────────────────────────────────
        foreach (array_chunk($upsertData, 500) as $chunk) {
            StudentUnpaidMonths::upsert(
                $chunk,
                ['student_id'],
                ['unpaid_months_count', 'last_calculated_at', 'updated_at']
            );
        }
    }
}
