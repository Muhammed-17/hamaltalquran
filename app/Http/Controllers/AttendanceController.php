<?php

namespace App\Http\Controllers;

use App\Http\Requests\Attendance\StoreAttendanceRequest;
use App\Http\Requests\Attendance\UpdateAttendanceRequest;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\User;
use App\Notifications\SequentialAbsenceNotification;
use App\Services\UserAccessService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\AttendanceExport;
use App\Exports\AttendanceMonthlyReportExport;
use \Illuminate\Support\Collection;

class AttendanceController extends Controller
{
    public function __construct(protected UserAccessService $access) {}

    // ─────────────────────────────────────────
    // Dashboard / Report
    // ─────────────────────────────────────────
    public function report()
    {
        $this->authorize('viewAny', Attendance::class);

        $user = Auth::user();
        $query = Attendance::query();

        if ($user->hasRole('guardian')) {
            $query->whereHas('student', fn($q) => $q->where('guardian_id', $user->id));
        } elseif (!$user->hasRole(['admin', 'general_manager'])) {
            $circleIds = $this->access->accessibleCircles($user)->pluck('id');
            $circleIds->isEmpty()
                ? $query->whereRaw('1=0')
                : $query->whereHas('student', fn($q) => $q->whereIn('circle_id', $circleIds));
        }

        $stats = (clone $query)
            ->selectRaw('status, count(*) as count')
            ->where('date', '>=', now()->subDays(30))
            ->groupBy('status')
            ->get();

        $dailyStats = (clone $query)
            ->selectRaw('date, count(*) as count')
            ->where('date', '>=', now()->subDays(7))
            ->where('status', 'present')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $monthlyStats = (clone $query)
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') as month, status, count(*) as count")
            ->where('date', '>=', now()->subMonths(12))
            ->groupBy('month', 'status')
            ->orderBy('month')
            ->get()
            ->groupBy('month');

        // ✅ O(N) — Aggregation في DB بدلاً من O(N²) في PHP
        $circleStats = DB::table('attendances')
            ->join('students', 'attendances.student_id', '=', 'students.id')
            ->join('circles', 'students.circle_id', '=', 'circles.id')
            ->where('attendances.date', '>=', now()->subDays(30))
            ->selectRaw('
                circles.name as circle_name,
                COUNT(*) as total,
                SUM(CASE WHEN attendances.status = "present" THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN attendances.status = "absent" THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN attendances.status = "late" THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN attendances.status = "excused" THEN 1 ELSE 0 END) as excused,
                ROUND(
                    SUM(CASE WHEN attendances.status = "present" THEN 1 ELSE 0 END) * 100.0 / COUNT(*),
                    1
                ) as rate
            ')
            ->groupBy('circles.name')
            ->get()
            ->mapWithKeys(fn($row) => [
                $row->circle_name => (array) $row
            ]);

        return view('attendance.report', compact('stats', 'dailyStats', 'monthlyStats', 'circleStats'));
    }
    // ─────────────────────────────────────────
    // Create attendance form
    // ─────────────────────────────────────────   
    public function create(Request $request)
    {
        $this->authorize('create', Attendance::class);

        $user   = Auth::user();
        $date   = $request->get('date', Carbon::today()->format('Y-m-d'));
        $selectedTeacherId = $request->get('teacher_id');

        // ✅ لو اللي متختار هو المستخدم الحالي نفسه وهو مشرف/مدير فرع/مدير عام،
        // معناها عايز يشوف كل الحلقات اللي هو مسؤول عنها (مش بس اللي معلم فيها فعليًا)
        $isSelfSupervisorSelection = $selectedTeacherId
            && (int) $selectedTeacherId === $user->id
            && $user->hasRole(['supervisor', 'manager', 'general_manager']);

        $circlesQuery = $this->access->accessibleCircles($user);

        // ✅ فلترة الحلقات حسب المعلم (user_id → teacher.user_id)
        if ($selectedTeacherId && !$isSelfSupervisorSelection) {
            $circlesQuery->whereHas('teachers.user', function ($q) use ($selectedTeacherId) {
                $q->where('id', $selectedTeacherId);
            });
        }

        $circles = $circlesQuery->get();

        $selectedCircleId = $request->get('circle_id');

        if (!$selectedCircleId && $circles->isNotEmpty()) {
            return redirect()->route('attendance.create', array_filter([
                'date'       => $date,
                'teacher_id' => $selectedTeacherId,
                'circle_id'  => $circles->first()->id,
            ]));
        }
        $students         = collect();
        $attendanceData   = collect();

        if ($selectedCircleId) {
            $students = Student::where('circle_id', $selectedCircleId)
                ->where('status', 'مقيد')
                ->get();

            $attendanceData = Attendance::where('date', $date)
                ->whereIn('student_id', $students->pluck('id'))
                ->get()
                ->keyBy('student_id');
        }

        // جلب المعلمين (users with teacher role)
        // ✅ لو المستخدم نفسه معلم: يشوف اسمه بس في الفلتر (مفيش داعي يشوف باقي المعلمين)
        if ($user->hasRole('teacher')) {
            $teachers = collect([(object) ['id' => $user->id, 'name' => $user->name]]);
        } else {
            $teachersQuery = User::whereHas('roles', function ($q) {
                $q->where('name', 'teacher');
            })->orderBy('name');

            if (!$user->hasRole(['admin', 'general_manager'])) {
                // ✅ لمدير الفرع: accessibleCircles() ترجع كل حلقات مركزه تلقائيًا
                // ✅ للمشرف: ترجع فقط حلقاته المُشرف عليها
                $accessibleCircleIds = $this->access->accessibleCircles($user)->pluck('id');
                $teachersQuery->whereHas('teacher.circles', function ($q) use ($accessibleCircleIds) {
                    $q->whereIn('circles.id', $accessibleCircleIds);
                });
            }

            $teachers = $teachersQuery->get(['id', 'name']);

            // ✅ إضافة المستخدم الحالي نفسه للقائمة لو مشرف/مدير فرع/مدير عام
            // عشان يقدر يسجل الحضور باسمه على الحلقات اللي هو مسؤول عنها
            if (
                $user->hasRole(['supervisor', 'manager', 'general_manager'])
                && !$teachers->contains('id', $user->id)
            ) {
                $teachers->push((object) ['id' => $user->id, 'name' => $user->name . ' (أنت)']);
                $teachers = $teachers->sortBy('name')->values();
            }
        }

        return view('attendance.create', compact(
            'circles',
            'students',
            'attendanceData',
            'date',
            'selectedCircleId',
            'teachers',
            'selectedTeacherId'
        ));
    }

    // ─────────────────────────────────────────
    // Store attendance — BATCH INSERT O(A)
    // ─────────────────────────────────────────
    public function store(StoreAttendanceRequest $request)
    {
        $this->authorize('create', Attendance::class);

        $validated = $request->validated();
        $date      = $validated['date'];
        $circleId  = $validated['circle_id'];
        $user      = Auth::user();

        if (!$user->hasRole(['admin', 'general_manager'])) {
            if (! $this->access->canAccessCircle($user, $circleId)) {
                abort(403, 'ليس لديك صلاحية على هذه الحلقة.');
            }
        }

        $validStudentIds = Student::where('circle_id', $circleId)
            ->where('status', 'مقيد')
            ->pluck('id')
            ->flip(); // O(1) lookup

        $now    = now();
        $userId = Auth::id();

        // ✅ Batch Insert: O(A) — استعلام واحد بدلاً من A استعلام
        $records = collect($validated['attendance'])
            ->filter(fn($data) => $validStudentIds->has($data['student_id']))
            ->map(fn($data) => [
                'student_id' => $data['student_id'],
                'date'       => $date,
                'status'     => $data['status'],
                'card'       => (int) ($data['card'] ?? 0),   // ← جديد
                'notes'      => !empty($data['notes']) ? $data['notes'] : null,
                'user_id'    => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->toArray();

        if (!empty($records)) {
            Attendance::upsert(
                $records,
                ['student_id', 'date'],
                ['status', 'card', 'notes', 'user_id', 'updated_at']
            );
        }
        return redirect()->route('attendance.index')
            ->with('success', 'تم حفظ سجل الحضور بنجاح');
    }

    // ─────────────────────────────────────────
    // Show single attendance record
    // ─────────────────────────────────────────
    public function show(Attendance $attendance)
    {
        $this->authorize('view', $attendance);

        $attendance->load(['student.circle.mainTeachers', 'user']);

        $previousRecord = Attendance::where('student_id', $attendance->student_id)
            ->where('date', '<', $attendance->date)
            ->orderBy('date', 'desc')->first();

        $nextRecord = Attendance::where('student_id', $attendance->student_id)
            ->where('date', '>', $attendance->date)
            ->orderBy('date', 'asc')->first();

        $monthlySummary = Attendance::where('student_id', $attendance->student_id)
            ->whereMonth('date', $attendance->date->month)
            ->whereYear('date', $attendance->date->year)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return view('attendance.show', compact(
            'attendance',
            'previousRecord',
            'nextRecord',
            'monthlySummary'
        ));
    }

    // ─────────────────────────────────────────
    // Edit attendance form
    // ─────────────────────────────────────────
    public function edit(Attendance $attendance)
    {
        $this->authorize('update', $attendance);

        $attendance->load(['student.circle', 'user']);

        return view('attendance.edit', compact('attendance'));
    }

    // ─────────────────────────────────────────
    // Update attendance — مع حماية من التكرار
    // ─────────────────────────────────────────
    public function update(UpdateAttendanceRequest $request, Attendance $attendance)
    {
        $this->authorize('update', $attendance);

        $validated = $request->validated();

        // ✅ FIX: طبقة حماية إضافية (server-side) — لو مفيش أي تغيير فعلي
        // على status/notes/date، تجاهل التحديث بدل عمل UPDATE query غير ضروري.
        // ده احتياطي فقط؛ المنع الأساسي المفروض يكون بالـ JS في attendance/edit.blade.php
        if (!$this->hasAttendanceChanges($attendance, $validated)) {
            return redirect()->route('attendance.index')
                ->with('info', 'لم يتم إجراء أي تعديل.');
        }

        // ✅ التحقق من عدم وجود سجل آخر بنفس (student_id, date)
        if ($validated['date'] !== $attendance->date->format('Y-m-d')) {
            $exists = Attendance::where('student_id', $attendance->student_id)
                ->where('date', $validated['date'])
                ->where('id', '!=', $attendance->id)
                ->exists();

            if ($exists) {
                return back()
                    ->withErrors(['date' => 'يوجد سجل حضور آخر لهذا الطالب في نفس التاريخ.'])
                    ->withInput();
            }
        }

        $attendance->update([
            'status' => $validated['status'],
            'card'   => (int) ($validated['card'] ?? 0),
            'notes'  => $validated['notes'] ?? null,
            'date'   => $validated['date'],
        ]);

        return redirect()->route('attendance.index')
            ->with('success', 'تم تحديث سجل الحضور بنجاح');
    }

    // ✅ FIX: مقارنة status/notes/date الحالية مقابل المُرسَلة لتحديد وجود تغيير فعلي
    private function hasAttendanceChanges(Attendance $attendance, array $validated): bool
    {
        if ($attendance->status !== $validated['status']) {
            return true;
        }

        // ← جديد
        if ((int) $attendance->card !== (int) ($validated['card'] ?? 0)) {
            return true;
        }

        $currentNotes  = $attendance->notes ?? '';
        $incomingNotes = $validated['notes'] ?? '';
        if ((string) $currentNotes !== (string) $incomingNotes) {
            return true;
        }

        if ($attendance->date->format('Y-m-d') !== $validated['date']) {
            return true;
        }

        return false;
    }

    // ─────────────────────────────────────────
    // Delete attendance record
    // ─────────────────────────────────────────
    public function destroy(Attendance $attendance)
    {
        $this->authorize('delete', $attendance);

        $attendance->delete();

        return redirect()->route('attendance.index')
            ->with('success', 'تم حذف سجل الحضور بنجاح');
    }

    // ─────────────────────────────────────────
    // List attendance records
    // ─────────────────────────────────────────
    public function index(Request $request)
    {
        $this->authorize('viewAny', Attendance::class);

        $user      = Auth::user();
        $circleIds = $this->access->accessibleCircles($user)->pluck('id');

        $selectedDate        = $request->filled('date')
            ? Carbon::parse($request->get('date'))->format('Y-m-d')
            : null;
        $sortOrder           = in_array($request->get('sort_order', 'desc'), ['asc', 'desc'])
            ? $request->get('sort_order', 'desc') : 'desc';
        $selectedCircleId    = $request->get('circle_id');
        $selectedCenterId    = $request->get('center_id');
        $selectedRegistrarId = $request->get('user_id');
        $selectedStatus      = $request->get('status');
        $search              = $request->get('search');

        $circles = $this->access->accessibleCircles($user)->get();

        $centers = $user->hasRole(['admin', 'general_manager'])
            ? \App\Models\Center::orderBy('name')->get()
            : collect();

        $registrars = $user->can('view all teachers')
            ? User::whereHas('attendances', function ($q) use ($circleIds, $user) {
                if (!$user->hasRole(['admin', 'general_manager'])) {
                    $q->whereHas('student', fn($s) => $s->whereIn('circle_id', $circleIds));
                }
            })->get()
            : collect();

        // ── بناء الاستعلام ──────
        $attendanceQuery = Attendance::with([
            'student.circle.mainTeachers',
            'student.center',
            'user',
        ]);

        // ── فلتر التاريخ ──────
        if ($selectedDate) {
            $attendanceQuery->whereDate('date', $selectedDate);
        }

        // ── البحث باسم الطالب ──────
        if ($search) {
            $attendanceQuery->whereHas('student', fn($q) => $q->where('name', 'like', "%{$search}%"));
        }

        // ── تصفية حسب الدور ──────
        if ($user->hasRole('guardian')) {
            $attendanceQuery->whereHas('student', fn($q) => $q->where('guardian_id', $user->id));
        } elseif (!$user->hasRole(['admin', 'general_manager'])) {
            if ($circleIds->isEmpty()) {
                $emptyRecords = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
                return view('attendance.index', compact(
                    'circles',
                    'centers',
                    'registrars',
                    'selectedCircleId',
                    'selectedCenterId',
                    'selectedRegistrarId',
                    'selectedStatus',
                    'selectedDate',
                    'sortOrder',
                    'search',
                ) + ['records' => $emptyRecords]);
            }
            $attendanceQuery->whereHas('student', fn($q) => $q->whereIn('circle_id', $circleIds));
        }

        // ── فلتر الفرع ──────
        if ($selectedCenterId && $user->hasRole(['admin', 'general_manager'])) {
            $attendanceQuery->whereHas('student', fn($q) => $q->where('center_id', $selectedCenterId));
        }

        // ── فلتر الحلقة ──────
        if ($selectedCircleId) {
            if (!$user->hasRole(['admin', 'general_manager']) && !$circleIds->contains($selectedCircleId)) {
                abort(403, 'ليس لديك صلاحية على هذه الحلقة.');
            }
            $attendanceQuery->whereHas('student', fn($q) => $q->where('circle_id', $selectedCircleId));
        }

        // ── فلتر المسجل ──────
        if ($selectedRegistrarId && $user->can('view all teachers')) {
            $attendanceQuery->where('user_id', $selectedRegistrarId);
        }

        // ── فلتر الحالة ──────
        if ($selectedStatus && in_array($selectedStatus, ['present', 'absent', 'late', 'excused'])) {
            $attendanceQuery->where('status', $selectedStatus);
        }

        $records = $attendanceQuery
            ->orderBy('date', $sortOrder)
            ->paginate(20)
            ->withQueryString();

        return view('attendance.index', compact(
            'records',
            'circles',
            'centers',
            'registrars',
            'selectedCircleId',
            'selectedCenterId',
            'selectedRegistrarId',
            'selectedStatus',
            'selectedDate',
            'sortOrder',
            'search'
        ));
    }

    // ─────────────────────────────────────────
    // My Attendance (for guardians)
    // ─────────────────────────────────────────
    public function myAttendance(Request $request)
    {
        $user = Auth::user();

        if (!$user->can('view own attendance')) abort(403);

        $month        = $request->get('month', now()->format('Y-m'));
        $startOfMonth = Carbon::parse($month . '-01')->startOfMonth();
        $endOfMonth   = $startOfMonth->copy()->endOfMonth();

        $students = Student::where('guardian_id', $user->id)
            ->where('status', '!=', 'متوقف')
            ->with([
                'attendances' => fn($q) => $q
                    ->whereBetween('date', [$startOfMonth, $endOfMonth])
                    ->orderBy('date', 'desc'),
            ])->get();

        $summary = $students->map(function ($student) {
            $total   = $student->attendances->count();
            $present = $student->attendances->where('status', 'present')->count();
            $late    = $student->attendances->where('status', 'late')->count();

            return [
                'student'         => $student,
                'total_days'      => $total,
                'present'         => $present,
                'absent'          => $student->attendances->where('status', 'absent')->count(),
                'late'            => $late,
                'excused'         => $student->attendances->where('status', 'excused')->count(),
                'attendance_rate' => $total > 0
                    ? round(($present + $late) / $total * 100, 1)
                    : 0,
            ];
        });

        return view('guardians.my_attendance', compact('summary', 'month'));
    }

    // ─────────────────────────────────────────
    // Sequential Absences — DB Query O(S)
    // ─────────────────────────────────────────
    // ─────────────────────────────────────────
    // Sequential Absences — DB Query O(S)
    // ─────────────────────────────────────────
    // ─────────────────────────────────────────
    // Monthly Absences (5+ per month) — DB Query O(1)
    // ─────────────────────────────────────────
    public function sequentialAbsences(Request $request)
    {
        $this->authorize('viewAny', Attendance::class);

        $user      = Auth::user();
        $circleIds = $this->access->accessibleCircles($user)->pluck('id');

        $minAbsences = 5;

        $month        = $request->get('month', now()->format('Y-m'));
        $startOfMonth = Carbon::parse($month . '-01')->startOfMonth();
        $endOfMonth   = $startOfMonth->copy()->endOfMonth();

        $selectedCenterId = $request->get('center_id');
        $selectedCircleId = $request->get('circle_id');
        $search           = $request->get('search');

        $circles = $this->access->accessibleCircles($user)->get();

        $centers = $user->hasRole(['admin', 'general_manager'])
            ? \App\Models\Center::orderBy('name')->get()
            : collect();

        // ✅ استعلام واحد مع Subquery لعدد أيام الغياب خلال الشهر المحدد
        $students = Student::with(['circle.supervisors', 'circle.mainTeachers', 'center'])
            ->select('students.*') // ✅ ضروري: بدونها selectSub تستبدل كل الأعمدة الأخرى
            ->where('status', '!=', 'متوقف')
            ->when($user->hasRole('guardian'), fn($q) => $q->where('guardian_id', $user->id))
            ->when(
                !$user->hasRole(['admin', 'general_manager']) && !$user->hasRole('guardian'),
                fn($q) => $circleIds->isEmpty()
                    ? $q->whereRaw('1=0')
                    : $q->whereIn('circle_id', $circleIds)
            )
            ->when($selectedCenterId && $user->hasRole(['admin', 'general_manager']), function ($q) use ($selectedCenterId) {
                $q->where('center_id', $selectedCenterId);
            })
            ->when($selectedCircleId, function ($q) use ($selectedCircleId, $user, $circleIds) {
                if (!$user->hasRole(['admin', 'general_manager']) && !$circleIds->contains($selectedCircleId)) {
                    abort(403, 'ليس لديك صلاحية على هذه الحلقة.');
                }
                $q->where('circle_id', $selectedCircleId);
            })
            ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%"))
            // ✅ Subquery: عدد أيام الغياب خلال الشهر المحدد
            ->selectSub(function ($query) use ($startOfMonth, $endOfMonth) {
                $query->from('attendances')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('attendances.student_id', 'students.id')
                    ->where('attendances.status', 'absent')
                    ->whereBetween('attendances.date', [$startOfMonth, $endOfMonth]);
            }, 'absence_days')
            ->having('absence_days', '>=', $minAbsences)
            ->orderByDesc('absence_days')
            ->get();

        // ✅ استعلام واحد لجلب تواريخ الغياب الفعلية لكل الطلاب المعروضين (بدون N+1)
        $studentIds = $students->pluck('id');

        $absenceDatesByStudent = DB::table('attendances')
            ->whereIn('student_id', $studentIds)
            ->where('status', 'absent')
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->orderBy('date')
            ->get(['student_id', 'date'])
            ->groupBy('student_id');

        // ✅ إرفاق التواريخ كخاصية إضافية على كل طالب
        $students->each(function ($student) use ($absenceDatesByStudent) {
            $student->absence_dates = $absenceDatesByStudent
                ->get($student->id, collect())
                ->pluck('date')
                ->map(fn($date) => Carbon::parse($date)->format('Y-m-d'))
                ->values();
        });

        // ✅ حالة "تم التواصل" مشتقة من وجود إشعار غياب متتالي فعليًا مُرسل لهذا الطالب خلال الشهر المحدد
        $notifiedStudentIds = DB::table('notifications')
            ->where('type', \App\Notifications\SequentialAbsenceNotification::class)
            ->whereNotNull('updated_at')
            ->whereYear('created_at', $startOfMonth->year)
            ->whereMonth('created_at', $startOfMonth->month)
            ->get(['data'])
            ->map(fn($row) => (json_decode($row->data, true)['student_id'] ?? null))
            ->filter()
            ->unique()
            ->flip();


        // ✅ تحويل البيانات لصيغة {value, label} يحتاجها x-searchable-select
        $centersOptions = $centers->map(fn($c) => ['value' => (string) $c->id, 'label' => $c->name])->values();
        $circlesOptions = $circles->map(fn($c) => ['value' => (string) $c->id, 'label' => $c->name])->values();

        return view('attendance.sequential-absences', compact(
            'students',
            'circles',
            'centers',
            'centersOptions',
            'circlesOptions',
            'month',
            'selectedCenterId',
            'selectedCircleId',
            'search'
        ));
    }
    // ─────────────────────────────────────────
    // Notify guardian about sequential absences
    // ─────────────────────────────────────────
    public function notifyStudent(Student $student, Request $request)
    {
        if (!Auth::user()->can('notify attendance')) {
            abort(403, 'ليس لديك صلاحية إرسال التنبيهات.');
        }

        $validated = $request->validate([
            'message' => 'nullable|string|max:1000',
        ]);

        $key = 'notify-student:' . Auth::id();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json(['message' => "يرجى الانتظار {$seconds} ثانية قبل إرسال تنبيه آخر."], 429);
        }
        RateLimiter::hit($key, 60);

        $guardian = $student->guardian;
        if (!$guardian) {
            return response()->json(['message' => 'لا يوجد ولي أمر مرتبط بهذا الطالب.'], 422);
        }

        $alreadyNotified = $guardian->notifications()
            ->where('type', SequentialAbsenceNotification::class)
            ->whereDate('created_at', today())
            ->where('data', 'like', '%"student_id":' . $student->id . '%')
            ->exists();

        if ($alreadyNotified) {
            return response()->json(['message' => 'تم إرسال تنبيه بالفعل اليوم لهذا الطالب.'], 409);
        }

        $absenceDays = $student->attendances()
            ->where('status', 'absent')
            ->where('date', '>=', now()->subDays(30))
            ->count();

        $message = $validated['message'] ? strip_tags($validated['message']) : null;

        $guardian->notify(new SequentialAbsenceNotification($student, $absenceDays, $message));

        return response()->json(['message' => 'تم إرسال التنبيه بنجاح.']);
    }

    // ─────────────────────────────────────────
    // Export to Excel
    // ─────────────────────────────────────────
    public function export(Request $request)
    {
        $this->authorize('viewAny', Attendance::class);

        $user      = Auth::user();
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate   = $request->get('end_date', now()->format('Y-m-d'));
        $circleId  = $request->get('circle_id');

        $accessibleCircleIds = null;

        if (!$user->hasRole(['admin', 'general_manager'])) {
            $allowedIds = $this->access->accessibleCircles($user)->pluck('id');

            if ($circleId) {
                if (!$allowedIds->contains($circleId)) {
                    abort(403, 'ليس لديك صلاحية على هذه الحلقة.');
                }
            } else {
                $accessibleCircleIds = $allowedIds;
            }
        }

        $filename = 'attendance_export_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(
            new AttendanceExport($startDate, $endDate, $circleId, $accessibleCircleIds),
            $filename
        );
    }

    // ─────────────────────────────────────────
    // Monthly Report Export
    // ─────────────────────────────────────────
    public function exportMonthly(Request $request)
    {
        $this->authorize('viewAny', Attendance::class);

        $month    = $request->get('month', now()->format('Y-m'));
        $circleId = $request->get('circle_id');
        $filename = 'monthly_report_' . $month . '.xlsx';

        return Excel::download(new AttendanceMonthlyReportExport($month, $circleId), $filename);
    }
}
