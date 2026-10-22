<?php

namespace App\Http\Controllers;

use App\Models\Center;
use App\Models\Circle;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Attendance;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        if (Auth::user()->hasRole('guardian')) {
            return redirect()->route('guardian.dashboard');
        }

        $user = Auth::user();
        $stats = [];
        $absentStudents = collect();
        $unpaidStudents = collect();
        $chartData = ['labels' => [], 'new_students' => [], 'stopped_students' => []];
        $statusChartData = ['labels' => [], 'data' => []];

        // ─── فلتر الفرع ─────────────────────────────────────────────
        $selectedCenterId = $request->input('center_id');
        $centers = Center::orderBy('name')->get();

        // 1. Determine Scope for Students, Circles, and Subscriptions
        $studentQuery = Student::query();
        $circleQuery = Circle::query();
        $subscriptionQuery = Subscription::query();

        // تطبيق فلتر الفرع
        if ($selectedCenterId) {
            $studentQuery->whereHas('circle', fn($q) => $q->where('center_id', $selectedCenterId));
            $circleQuery->where('center_id', $selectedCenterId);
            $subscriptionQuery->whereHas('circle', fn($q) => $q->where('center_id', $selectedCenterId));
        }

        if ($user->hasRole('supervisor') && $user->teacher) {
            $supervisorId = $user->teacher->id;
            $studentQuery->whereHas('circle.supervisors', fn($q) => $q->where('teachers.id', $supervisorId));
            $circleQuery->whereHas('supervisors', fn($q) => $q->where('teachers.id', $supervisorId));
            $subscriptionQuery->whereHas('circle.supervisors', fn($q) => $q->where('teachers.id', $supervisorId));
        } elseif ($user->hasRole('teacher') && $user->teacher) {
            $teacherId = $user->teacher->id;
            $studentQuery->whereHas('circle', fn($q) => $q->whereHas('teachers', fn($t) => $t->where('teachers.id', $teacherId)));
            $circleQuery->whereHas('teachers', fn($q) => $q->where('teachers.id', $teacherId));
            $subscriptionQuery->whereHas('circle', fn($q) => $q->whereHas('teachers', fn($t) => $t->where('teachers.id', $teacherId)));
        }

        // 2. Dashboard Stats & Alerts Calculation
        if ($user->hasAnyRole(['admin', 'supervisor', 'guardian'])) {
            if ($user->hasAnyRole(['admin', 'supervisor'])) {
                $stats['students_count'] = (clone $studentQuery)->where('status', '!=', 'متوقف')->count();
                $stats['teachers_count'] = $selectedCenterId
                    ? Teacher::whereHas('circles', fn($q) => $q->where('center_id', $selectedCenterId))->count()
                    : Teacher::count();
                $stats['circles_count'] = (clone $circleQuery)->count();

                $stats['monthly_revenue'] = (clone $subscriptionQuery)
                    ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->sum('amount');

                $lastMonthRevenue = (clone $subscriptionQuery)
                    ->whereBetween('paid_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])
                    ->sum('amount');

                $stats['revenue_growth'] = $lastMonthRevenue > 0
                    ? round((($stats['monthly_revenue'] - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1)
                    : 0;

                $studentIdsForAttendance = (clone $studentQuery)->pluck('id');

                $attendanceQuery = Attendance::whereIn('student_id', $studentIdsForAttendance)
                    ->whereBetween('date', [now()->subDays(30)->startOfDay(), now()->endOfDay()]);

                $totalAttendanceRecords = (clone $attendanceQuery)->count();
                $presentRecords         = (clone $attendanceQuery)->where('status', 'present')->count();

                $stats['attendance_rate'] = $totalAttendanceRecords > 0
                    ? round(($presentRecords / $totalAttendanceRecords) * 100, 1)
                    : 0;

                // ─── نمو الطلاب: جدد + متوقفين ─────────────────────
                $rangeStart = now()->subMonths(5)->startOfMonth();
                $rangeEnd   = now()->endOfMonth();

                $newStudentsByMonth = (clone $studentQuery)
                    ->where('status', '!=', 'متوقف')
                    ->whereBetween('created_at', [$rangeStart, $rangeEnd])
                    ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month_label, COUNT(*) as cnt")
                    ->groupBy('month_label')
                    ->pluck('cnt', 'month_label');

                $stoppedStudentsByMonth = (clone $studentQuery)
                    ->where('status', 'متوقف')
                    ->whereColumn('updated_at', '!=', 'created_at')
                    ->whereBetween('updated_at', [$rangeStart, $rangeEnd])
                    ->selectRaw("DATE_FORMAT(updated_at, '%Y-%m') as month_label, COUNT(*) as cnt")
                    ->groupBy('month_label')
                    ->pluck('cnt', 'month_label');

                for ($i = 5; $i >= 0; $i--) {
                    $date     = now()->subMonths($i);
                    $monthKey = $date->format('Y-m');

                    $chartData['labels'][]           = $date->locale('ar')->isoFormat('MMMM');
                    $chartData['new_students'][]     = $newStudentsByMonth->get($monthKey, 0);
                    $chartData['stopped_students'][] = $stoppedStudentsByMonth->get($monthKey, 0);
                }

                $totalNew = array_sum($chartData['new_students']);
                $totalStopped = array_sum($chartData['stopped_students']);
                $totalStudents = $stats['students_count'] > 0 ? $stats['students_count'] : 1;
                $stats['student_growth_percentage'] = round((($totalNew - $totalStopped) / $totalStudents) * 100, 1);

                // Status Distribution
                $statusScope = $selectedCenterId
                    ? Student::whereHas('circle', fn($q) => $q->where('center_id', $selectedCenterId))
                    : Student::query();

                if ($user->hasRole('admin')) {
                    $rawStatusStats = (clone $statusScope)
                        ->selectRaw('status, count(*) as count')
                        ->groupBy('status')
                        ->pluck('count', 'status')
                        ->toArray();
                } elseif ($user->teacher) {
                    $rawStatusStats = Student::whereHas('circle.supervisors', fn($q) => $q->where('teachers.id', $user->teacher->id))
                        ->selectRaw('status, count(*) as count')
                        ->groupBy('status')
                        ->pluck('count', 'status')
                        ->toArray();
                } else {
                    $rawStatusStats = [];
                }

                $statusChartData = [
                    'labels' => array_keys($rawStatusStats),
                    'data' => array_values($rawStatusStats),
                ];
            }

            // ═══════════════════════════════════════════════════════════
            // ALERTS: Absent & Unpaid Students
            // ═══════════════════════════════════════════════════════════
            $alertStudentsQuery = Student::where('status', '!=', 'متوقف');

            if ($selectedCenterId) {
                $alertStudentsQuery->whereHas('circle', fn($q) => $q->where('center_id', $selectedCenterId));
            }

            if ($user->hasRole('supervisor') && $user->teacher) {
                $alertStudentsQuery->whereHas('circle.supervisors', fn($q) => $q->where('teachers.id', $user->teacher->id));
            } elseif ($user->hasRole('guardian')) {
                $alertStudentsQuery->where('guardian_id', $user->id);
            } elseif ($user->hasRole('teacher') && $user->teacher) {
                $alertStudentsQuery->whereIn('circle_id', $user->teacher->circles->pluck('id'));
            }

            // ─── Absent Students (Sequential Absences) ─────────────
            $absentStudents = $this->getAbsentStudents($alertStudentsQuery);

            // ─── Unpaid Students ─────────────────────────────────────
            $unpaidStudents = $this->getUnpaidStudents($alertStudentsQuery);
        }

        // Additional Role-Specific Stats
        if ($user->hasRole('teacher') && $user->teacher) {
            $stats['my_circles_count'] = $user->teacher->circles()->count();
            $stats['my_students_count'] = Student::whereIn('circle_id', $user->teacher->circles->pluck('id'))->where('status', '!=', 'متوقف')->count();
        }

        if ($user->hasRole('guardian')) {
            $stats['my_children_count'] = $user->students()->count();
        }

        return view('dashboard', compact(
            'stats',
            'absentStudents',
            'unpaidStudents',
            'chartData',
            'statusChartData',
            'centers',
            'selectedCenterId'
        ));
    }

    /**
     * Get students with sequential absences (2+ consecutive absences)
     */
    private function getAbsentStudents($query)
    {
        $students = (clone $query)->with(['circle'])->get();
        $studentIds = $students->pluck('id');

        // Query واحد لكل الطلاب بدل query لكل طالب
        $attendancesByStudent = Attendance::whereIn('student_id', $studentIds)
            ->orderBy('date', 'desc')
            ->get(['student_id', 'status'])
            ->groupBy('student_id');

        return $students->map(function ($student) use ($attendancesByStudent) {
            // آخر 30 سجل، مرتبة تصاعدياً (الأقدم أولاً) زي المنطق الأصلي
            $attendances = ($attendancesByStudent->get($student->id) ?? collect())
                ->take(30)
                ->pluck('status')
                ->reverse()
                ->values()
                ->toArray();

            if (empty($attendances)) {
                $student->absence_days = 0;
                $student->has_sequential_absence = false;
                return $student;
            }

            // Check for sequential absences (2+ consecutive)
            $hasSequential = false;
            $consecutiveCount = 0;
            $maxConsecutive = 0;

            foreach ($attendances as $status) {
                if ($status === 'absent') {
                    $consecutiveCount++;
                    $maxConsecutive = max($maxConsecutive, $consecutiveCount);
                    if ($consecutiveCount >= 2) {
                        $hasSequential = true;
                    }
                } else {
                    $consecutiveCount = 0;
                }
            }

            // Count total absence days in last 30 records
            $totalAbsences = collect($attendances)->filter(fn($s) => $s === 'absent')->count();

            $student->absence_days = $totalAbsences;
            $student->has_sequential_absence = $hasSequential || $totalAbsences >= 3;

            return $student;
        })->filter(fn($s) => $s->has_sequential_absence)
            ->sortByDesc('absence_days')
            ->take(5);
    }

    /**
     * Get students with unpaid months
     */
    private function getUnpaidStudents($query)
    {
        $students   = (clone $query)->with(['circle'])->get();
        $studentIds = $students->pluck('id');

        // نفس المصدر اللي بتستخدمه صفحة lateAndUnpaid (جدول student_unpaid_months)
        $unpaidCounts = \Illuminate\Support\Facades\DB::table('student_unpaid_months')
            ->whereIn('student_id', $studentIds)
            ->where('unpaid_months_count', '>', 0)
            ->pluck('unpaid_months_count', 'student_id');

        return $students->map(function ($student) use ($unpaidCounts) {
            $student->unpaid_months_count = $unpaidCounts->get($student->id, 0);
            return $student;
        })->filter(fn($s) => $s->unpaid_months_count > 0)
            ->sortByDesc('unpaid_months_count')
            ->take(5);
    }

    public function myDashboard(): View
    {
        /** @var User $user */
        $user = Auth::user();
        $userId = $user->id;

        $activeChildrenCount = Student::where('guardian_id', $userId)
            ->where('status', 'مقيد')
            ->count();

        $totalChildrenCount = Student::where('guardian_id', $userId)->count();

        $latestAbsences = Attendance::whereHas('student', function ($q) use ($userId) {
            $q->where('guardian_id', $userId);
        })
            ->where('status', 'absent')
            ->with('student')
            ->orderBy('date', 'desc')
            ->take(10)
            ->get();

        $unpaidSubscriptions = \Illuminate\Support\Facades\DB::table('student_unpaid_months')
            ->join('students', 'students.id', '=', 'student_unpaid_months.student_id')
            ->leftJoin('circles', 'circles.id', '=', 'students.circle_id')
            ->where('students.guardian_id', $userId)
            ->where('student_unpaid_months.unpaid_months_count', '>', 0)
            ->select(
                'students.id as student_id',
                'students.name as student_name',
                'circles.name as circle_name',
                'student_unpaid_months.unpaid_months_count'
            )
            ->orderByDesc('student_unpaid_months.unpaid_months_count')
            ->take(10)
            ->get();

        $children = Student::where('guardian_id', $userId)
            ->where('status', '!=', 'متوقف')
            ->with(['attendances' => function ($q) {
                $q->where('date', '>=', now()->startOfMonth());
            }])->get();

        $attendanceStats = $children->map(function ($child) {
            $total   = $child->attendances->count();
            $present = $child->attendances->where('status', 'present')->count();
            $late    = $child->attendances->where('status', 'late')->count();

            return [
                'name'    => $child->name,
                'id'      => $child->id,
                'rate'    => $total > 0 ? round(($present + $late) / $total * 100, 1) : 0,
                'total'   => $total,
                'present' => $present,
            ];
        });

        $unpaidMonthsTotal = \Illuminate\Support\Facades\DB::table('student_unpaid_months')
            ->join('students', 'students.id', '=', 'student_unpaid_months.student_id')
            ->where('students.guardian_id', $userId)
            ->sum('student_unpaid_months.unpaid_months_count');


        return view('guardians.my_dashboard', compact(
            'activeChildrenCount',
            'totalChildrenCount',
            'latestAbsences',
            'unpaidSubscriptions',
            'attendanceStats',
            'unpaidMonthsTotal'
        ));
    }
}
