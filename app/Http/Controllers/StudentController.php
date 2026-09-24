<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreStudentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Models\Student;
use App\Models\Circle;
use App\Models\User;
use App\Models\Scopes\CenterScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Services\UserAccessService;
use \App\Services\EducationStageExclusionService;

class StudentController extends Controller
{
    public function __construct(protected UserAccessService $access) {}

    private array $constructionFields = [
        'circle_id',
        'study_system',
        'current_surah_id',
        'new_memorization_plan',
        'revision_plan',
        'old_memorization_plan',
        'placement_evaluation',
    ];

    private array $itqanFields = [
        'previous_memorization_side',
        'previous_khatamat_count',
        'current_review_amount',
        'self_evaluation',
        'tajweed_matn',
        'desired_path',
        'preferred_time_mastery',
        'teacher_name',
        'itqan_details',
    ];

    private array $ibdaFields = [
        'previous_licenses_and_chains',
        'desired_narration_and_path',
        'preferred_time_creativity',
        'supervisor_name',
        'ibda_details',
    ];

    private array $studentColumns = [
        'name',
        'date_of_birth',
        'gender',
        'second_phone',
        'address',
        'guardian_id',
        'status',
        'suspended_at',
        'circle_id',
        'education_type',
        'educational_stage',
        'school_grade',
        'previous_school',
        'center_entry_level',
        'join_date',
        'whatsapp_number',
        'health_status',
        'notes',
        'supervisor_id',
        'applicant',
        'center_id',
        'whatsapp_owner',
        'additional_contact_owner',
        'learning_difficulties',
        'personal_traits',
        'hobbies',
        'reading',
        'student_exit_status',
        'decision',
        'subscription_fees',
        'received_tools',
        'student_code',
    ];

    // ─────────────────────────────────────────
    public function index(Request $request)
    {
        $this->authorize('viewAny', Student::class);

        $user  = Auth::user();
        $query = Student::query()
            ->select([
                'id',
                'name',
                'status',
                'decision',
                'circle_id',
                'center_id',
                'educational_stage',
                'date_of_birth',
                'student_code',
                'whatsapp_number',
                'school_grade',
                'gender',
            ])
            ->with('circle:id,name');

        if ($user->hasRole('guardian')) {
            $query->where('guardian_id', $user->id);
        }

        if ($user->hasRole('supervisor') || $user->hasRole('teacher')) {
            $query->where('status', 'مقيد');
        }

        $query
            ->when($request->q, fn($q, $v) => $q->where(
                fn($q) => $q
                    ->where('name', 'like', "%$v%")
                    ->orWhere('student_code', 'like', "%$v%")
                    ->orWhere('whatsapp_number', 'like', "%$v%")
            ))
            ->when($request->status,            fn($q, $v) => $q->where('status', $v))
            ->when($request->circle_id,         fn($q, $v) => $q->where('circle_id', $v))
            ->when($request->center_id,         fn($q, $v) => $q->where('center_id', $v))
            ->when($request->educational_stage, fn($q, $v) => $q->where('educational_stage', $v))
            ->when($request->school_grade,      fn($q, $v) => $q->where('school_grade', $v))
            ->when($request->gender,            fn($q, $v) => $q->where('gender', $v))
            ->when($request->decision,          fn($q, $v) => $q->where('decision', $v))
            ->when($request->age_min, fn($q, $v) => $q->whereRaw(
                'TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= ?',
                [max(1, min(99, (int) $v))]
            ))
            ->when($request->age_max, fn($q, $v) => $q->whereRaw(
                'TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) <= ?',
                [max(1, min(99, (int) $v))]
            ));

        $allowedSorts = ['name', 'status', 'educational_stage', 'age', 'circle_name'];
        $sortField    = in_array($request->sort, $allowedSorts) ? $request->sort : 'name';
        $sortDir      = $request->dir === 'desc' ? 'desc' : 'asc';

        if ($sortField === 'age') {
            $query->orderByRaw("TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) $sortDir");
        } elseif ($sortField === 'circle_name') {
            $query->leftJoin('circles', 'students.circle_id', '=', 'circles.id')
                ->orderBy('circles.name', $sortDir)
                ->select('students.*');
        } else {
            $query->orderBy($sortField, $sortDir);
        }

        // ✅ الفرق الجوهري: paginate() مباشرة + withQueryString() بدل JSON response
        $students = $query->paginate(30)->withQueryString();

        $circles = $this->access->accessibleCircles($user)->get();
        $centers = $this->access->accessibleCenters($user)->get();

        $teachers = auth()->user()->hasRole('admin')
            ? \App\Models\Teacher::with('user')->get()
            : collect();

        return view('students.index', compact('students', 'circles', 'centers', 'teachers'));
    }

    // ─────────────────────────────────────────
    public function export(Request $request)
    {
        $this->authorize('viewAny', Student::class);

        $filters = $request->only([
            'q',
            'status',
            'circle_id',
            'center_id',
            'educational_stage',
            'school_grade',
            'decision',
            'age_min',
            'age_max',
        ]);

        $fileName = 'الطلاب-' . now()->format('Y-m-d') . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\StudentsExport($filters),
            $fileName
        );
    }
    // ─────────────────────────────────────────

    public function excludedReview(EducationStageExclusionService $exclusionService)
    {
        $this->authorize('viewAny', Student::class);

        $excluded = $exclusionService->getExcludedStudents();

        return view('students.excluded_review', [
            'excluded' => $excluded,
        ]);
    }

    // ─────────────────────────────────────────
    public function create()
    {
        $this->authorize('create', Student::class);

        $user    = Auth::user();
        $teacher = $this->access->teacher($user);

        return view('students.create', [
            'student'            => new Student(),
            'circles'            => $this->access->accessibleCircles($user)->get(),
            'centers'            => $this->access->accessibleCenters($user)->get(),
            'teachers'           => $this->access->accessibleTeachers($user)->get(),
            'supervisors'        => $this->access->accessibleSupervisors($user)->get(),
            'guardians'          => User::role('guardian')->get(),
            'subscriptionPrices' => DB::table('subscription_prices')->get(),
            'surahs'             => \App\Models\Surah::orderBy('number')->get(),
        ]);
    }
    // ─────────────────────────────────────────
    public function store(StoreStudentRequest $request)
    {
        $this->authorize('create', Student::class);

        DB::beginTransaction();
        try {
            $data         = $request->validated();
            $existingUser = null;

            $data['guardian_id'] = $this->resolveGuardianId(
                $data['guardian_id'] ?? null,
                $data['guardian_name'] ?? null,
                $data['parent_email'] ?? null,
                $data['password'] ?? null,
                $existingUser,
            );

            $studentData = array_intersect_key($data, array_flip($this->studentColumns));

            if (($studentData['status'] ?? '') === 'متوقف') {
                $studentData['suspended_at'] = now();
            }

            $student = Student::create($studentData);
            if ($student->circle_id) {
                \App\Models\CircleAssignmentHistory::create([
                    'student_id' => $student->id,
                    'circle_id'  => $student->circle_id,
                    'from_date'  => $student->join_date ?? now(),
                    'to_date'    => null,
                ]);
            }

            $this->syncDetailRecord($student, $data, 'create');

            $this->syncGuardianStatus($student->fresh()->guardian_id);

            // ✅ بناء رابط واتساب الترحيبي لو الرقم موجود ولو الحلقة عندها رابط مجموعة
            // ✅ بناء رابط واتساب الترحيبي فقط لو المستخدم وافق على الإرسال من الـ Modal
            // ✅ وبشرط إن الحلقة المختارة فعليًا عندها رابط مجموعة (متثقش في قيمة نص الرسالة القادمة من الكلاينت لوحدها)
            $whatsappLink = null;
            $circleUrl    = optional($student->circle)->url;
            if (
                $request->input('send_whatsapp_message') === '1'
                && $circleUrl
                && !empty($studentData['whatsapp_number'])
            ) {
                $number = preg_replace('/[^0-9]/', '', $studentData['whatsapp_number']);
                if (str_starts_with($number, '0')) {
                    $number = '2' . $number;
                }
                $text = $request->input('whatsapp_message_text') ?: "تم إضمامكم إلى مركز حملة القرآن يرجى الضغط على الرابط للدخول إلى المجموعة\n" . $circleUrl;
                $whatsappLink = 'https://wa.me/' . $number . '?text=' . urlencode($text);
            }
            DB::commit();

            $message = $existingUser
                ? 'تم تسجيل الطالب وربطه بحساب ولي الأمر الموجود ✓'
                : 'تم تسجيل الطالب بنجاح ✓';

            if ($request->expectsJson() || $request->ajax()) {
                session()->flash('success', $message);

                return response()->json([
                    'success'       => true,
                    'message'       => $message,
                    'redirect'      => route('students.index'),
                    'whatsapp_link' => $whatsappLink,
                    'student'       => $student->load(['constructionDetail', 'itqanDetail', 'ibdaDetail']),
                ]);
            }

            return redirect()->route('students.index')
                ->with('success', $message)
                ->with('whatsapp_link', $whatsappLink);
        } catch (\Exception $e) {
            DB::rollBack();
            $errorMessage = 'حدث خطأ أثناء تسجيل الطالب: ' . $e->getMessage();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $errorMessage], 500);
            }

            return redirect()->back()->with('error', $errorMessage)->withInput();
        }
    }

    // ─────────────────────────────────────────
    public function show($id)
    {
        $student = Student::withoutGlobalScope(CenterScope::class)->findOrFail($id);
        $this->authorize('view', $student);
        $this->authorizeStudentCenter($student);

        $student->load([
            'circle.mainTeachers',
            'circle.assistantTeachers',
            'guardian',
            'attendances',
            'subscriptions',
            'constructionDetail',
            'itqanDetail',
            'ibdaDetail',
            'supervisor.user',
            'behavioralNotes.teacher.user',
            'weeklyFollowups.teacher.user',
            'weeklyFollowups.newMemorizations.toSurah',
            'weeklyFollowups.revisions.toSurah',
            'competitionParticipants.competition',
            'competitionParticipants.competitionLevel.level',
            'competitionParticipants.competitionResult',
        ]);

        $totalAttendance = $student->attendances->count();
        $presentCount    = $student->attendances->where('status', 'present')->count();
        $lateCount       = $student->attendances->where('status', 'late')->count();
        $absentCount     = $student->attendances->where('status', 'absent')->count();
        $excusedCount    = $student->attendances->where('status', 'excused')->count();
        $attendanceRate  = $totalAttendance > 0
            ? round((($presentCount + $lateCount) / $totalAttendance) * 100)
            : 0;

        $startDate = $student->join_date
            ? $student->join_date->copy()->startOfMonth()
            : $student->created_at->copy()->startOfMonth();

        $endDate = $student->status === 'متوقف' && $student->suspended_at
            ? $student->suspended_at->copy()->startOfMonth()
            : now()->startOfMonth();

        $subscriptionIndex = $student->subscriptions->keyBy(fn($s) => $s->month->format('Y-m'));
        $feeTimeline       = collect();
        $checkDate         = $startDate->copy();

        while ($checkDate->lte($endDate)) {
            $sub = $subscriptionIndex->get($checkDate->format('Y-m'));
            $feeTimeline->push((object) [
                'month'        => $checkDate->copy(),
                'subscription' => $sub,
                'is_paid'      => $sub && $sub->status === 'مدفوع',
            ]);
            $checkDate->addMonth();
        }

        $surahTestResults = \App\Models\StudentSurahTestResult::where('student_id', $student->id)
            ->with(['surahTest.surah', 'surahTest.circle', 'surahTest.teacher.user'])
            ->whereHas('surahTest')
            ->get()
            ->sortByDesc(fn($result) => $result->surahTest->test_date);

        return view('students.show', [
            'student'           => $student,
            'attendanceRate'    => $attendanceRate,
            'presentCount'      => $presentCount,
            'lateCount'         => $lateCount,
            'absentCount'       => $absentCount,
            'excusedCount'      => $excusedCount,
            'unpaidMonthsCount' => $student->overdue_months_count,
            'paidMonthsCount'   => $student->subscriptions->where('status', 'مدفوع')->count(),
            'feeTimeline'       => $feeTimeline->sortByDesc('month'),
            'suspendedPastDebt' => $student->suspended_past_debt,
            'surahTestResults'  => $surahTestResults,
        ]);
    }

    // ─────────────────────────────────────────
    public function edit($id)
    {
        $student = Student::withoutGlobalScope(CenterScope::class)->findOrFail($id);
        $this->authorize('update', $student);
        $this->authorizeStudentCenter($student);

        $user    = Auth::user();
        $teacher = $this->access->teacher($user);

        $student->load(['guardian', 'constructionDetail', 'itqanDetail', 'ibdaDetail', 'circle']);

        $circles = $this->access->accessibleCircles($user)->get();

        // ✅ تأكد أن حلقة الطالب الحالية موجودة ضمن القائمة حتى لو خارج نطاق المستخدم
        $currentCircleId = $student->constructionDetail->circle_id ?? $student->circle_id ?? null;
        if ($currentCircleId && !$circles->contains('id', $currentCircleId)) {
            $studentCircle = Circle::find($currentCircleId);
            if ($studentCircle) {
                $circles = $circles->push($studentCircle);
            }
        }

        return view('students.edit', [
            'student'            => $student,
            'circles'            => $circles,
            'centers'            => $this->access->accessibleCenters($user)->get(),
            'teachers'           => $this->access->accessibleTeachers($user)->get(),
            'supervisors'        => $this->access->accessibleSupervisors($user)->get(),
            'guardians'          => User::role('guardian')->get(),
            'subscriptionPrices' => DB::table('subscription_prices')->get(),
            'construction'       => $student->constructionDetail,
            'itqan'              => $student->itqanDetail,
            'ibda'               => $student->ibdaDetail,
            'surahs'             => \App\Models\Surah::orderBy('number')->get(),
        ]);
    }

    // ─────────────────────────────────────────
    // ─────────────────────────────────────────
    public function update(UpdateStudentRequest $request, $id)
    {
        $student = Student::withoutGlobalScope(CenterScope::class)->findOrFail($id);
        $this->authorize('update', $student);
        $this->authorizeStudentCenter($student);

        DB::beginTransaction();
        try {
            $data         = $request->validated();
            $existingUser = null;

            $data['guardian_id'] = $this->resolveGuardianId(
                $data['guardian_id'] ?? null,
                $data['guardian_name'] ?? null,
                $data['parent_email'] ?? null,
                $data['password'] ?? null,
                $existingUser,
            );

            $studentData = array_intersect_key($data, array_flip($this->studentColumns));

            // ✅ صلاحية تغيير الحالة والقرار
            if (isset($studentData['status']) || isset($studentData['decision'])) {
                if (!auth()->user()->can('manage student status')) {
                    unset($studentData['status'], $studentData['suspended_at'], $studentData['decision']);
                }
            }

            // ✅ صلاحية تغيير الحلقة
            if (isset($studentData['circle_id'])) {
                if (!auth()->user()->can('assign student to circle')) {
                    unset($studentData['circle_id']);
                }
            }

            if (isset($studentData['status'])) {
                if ($studentData['status'] === 'متوقف' && $student->status !== 'متوقف') {
                    $studentData['suspended_at'] = now();
                } elseif ($studentData['status'] !== 'متوقف') {
                    $studentData['suspended_at'] = null;
                }
            }


            if (!$this->hasStudentChanges($student, $studentData, $data)) {
                DB::rollBack();

                if ($request->expectsJson() || $request->ajax()) {
                    session()->flash('info', 'لم يتم إجراء أي تعديل.');

                    return response()->json([
                        'success'       => true,
                        'message'       => 'لم يتم إجراء أي تعديل.',
                        'redirect'      => route('students.index'),
                        'whatsapp_link' => null,
                    ]);
                }

                return redirect()->route('students.index')->with('info', 'لم يتم إجراء أي تعديل.');
            }

            // ✅ حفظ الحلقة القديمة قبل التحديث لمقارنتها بعده
            $oldCircleId = $student->circle_id;

            $student->update($studentData);

            // ✅ تسجيل انتقال الحلقة في جدول التاريخ إذا تغيّرت فعليًا
            $circleChanged = isset($studentData['circle_id']) && $studentData['circle_id'] != $oldCircleId;
            if ($circleChanged) {
                \App\Models\CircleAssignmentHistory::openNewFor($student->id, $studentData['circle_id']);
            }

            // ✅ لو المستخدم مالوش صلاحية تغيير الحلقة، امنع تحديثها في تفاصيل البناء كمان
            // عشان نضمن تطابق circle_id بين جدول students وجدول student_construction_details
            $syncData = $data;
            if (!auth()->user()->can('assign student to circle')) {
                $syncData['circle_id'] = $student->circle_id; // ارجعها للقيمة الحالية المحفوظة فعليًا
            }

            $this->syncDetailRecord($student, $syncData, 'update');

            // ✅ مزامنة حالة ولي الأمر
            $this->syncGuardianStatus($student->fresh()->guardian_id);

            // ✅ بناء رابط واتساب الترحيبي فقط لو: المستخدم وافق من الـ Modal + الحلقة اتغيرت فعليًا
            // + الحلقة الجديدة عندها رابط مجموعة (تحقق من السيرفر، مش من الكلاينت فقط)
            $whatsappLink = null;
            $newCircleUrl = optional($student->fresh()->circle)->url;

            if (
                $request->input('send_whatsapp_message') === '1'
                && $circleChanged
                && $newCircleUrl
                && !empty($studentData['whatsapp_number'] ?? $student->whatsapp_number)
            ) {
                $whatsappNumber = $studentData['whatsapp_number'] ?? $student->whatsapp_number;
                $number = preg_replace('/[^0-9]/', '', $whatsappNumber);
                if (str_starts_with($number, '0')) {
                    $number = '2' . $number;
                }
                $text = $request->input('whatsapp_message_text')
                    ?: "تم إضمامكم إلى مركز حملة القرآن يرجى الضغط على الرابط للدخول إلى المجموعة\n" . $newCircleUrl;
                $whatsappLink = 'https://wa.me/' . $number . '?text=' . urlencode($text);
            }

            DB::commit();

            $message = $existingUser
                ? 'تم التحديث وربط ولي الأمر الموجود مسبقاً ✓'
                : 'تم تحديث بيانات الطالب بنجاح ✓';

            if ($request->expectsJson() || $request->ajax()) {
                session()->flash('success', $message);

                return response()->json([
                    'success'       => true,
                    'message'       => $message,
                    'redirect'      => route('students.index'),
                    'whatsapp_link' => $whatsappLink,
                ]);
            }

            return redirect()->route('students.index')
                ->with('success', $message)
                ->with('whatsapp_link', $whatsappLink);
        } catch (\Exception $e) {
            DB::rollBack();
            $errorMessage = 'حدث خطأ: ' . $e->getMessage();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $errorMessage], 500);
            }

            return redirect()->back()->with('error', $errorMessage)->withInput();
        }
    }
    // ─────────────────────────────────────────
    public function destroy($id)
    {
        $student = Student::withoutGlobalScope(CenterScope::class)->findOrFail($id);
        $this->authorize('delete', $student);
        $this->authorizeStudentCenter($student);

        if ($student->subscriptions()->exists() || $student->attendances()->exists()) {
            return redirect()->back()->with(
                'error',
                'لا يمكن حذف الطالب لوجود سجلات حضور أو اشتراكات مرتبطة به'
            );
        }

        // ✅ احفظ guardian_id قبل الحذف
        $guardianId = $student->guardian_id;
        $student->delete();

        // ✅ مزامنة حالة ولي الأمر بعد الحذف
        $this->syncGuardianStatus($guardianId);

        return redirect()->route('students.index')->with('success', 'تم حذف الطالب بنجاح');
    }

    // ─────────────────────────────────────────
    // Private Helpers
    // ─────────────────────────────────────────

    private function authorizeStudentCenter(Student $student): void
    {
        $user = Auth::user();

        if ($user->hasRole('admin')) return;
        if ($user->hasRole('guardian')) return;

        $accessibleCenterIds = $this->access->accessibleCenters($user)->pluck('id');

        if ($accessibleCenterIds->isNotEmpty() && !$accessibleCenterIds->contains($student->center_id)) {
            abort(403, 'ليس لديك صلاحية الوصول لبيانات هذا الطالب');
        }
    }

    private function resolveGuardianId(
        mixed   $guardianId,
        ?string $guardianName,
        ?string $parentEmail,
        ?string $password,
        ?User   &$existingUser,
    ): int|null {

        if ($guardianId === 'new') {

            if (!auth()->user()->can('create', Student::class)) {
                abort(403, 'ليس لديك صلاحية إنشاء حساب ولي أمر');
            }

            if (!empty($parentEmail)) {
                $existingUser = User::role('guardian')
                    ->where('email', $parentEmail)
                    ->first();
            }

            if ($existingUser) {
                return $existingUser->id;
            }

            $emailToUse = !empty($parentEmail)
                ? $parentEmail
                : 'guardian_' . uniqid() . '@temp.local';

            $guardian = User::create([
                'name'     => $guardianName,
                'email'    => $emailToUse,
                'password' => Hash::make($password ?? Str::random(16)),
                'status'   => 'active',
                'email_verified_at' => now(),
            ]);

            $guardian->assignRole('guardian');

            return $guardian->id;
        }

        if ($guardianId === 'none' || $guardianId === null) {
            return null;
        }

        $intId    = (int) $guardianId;
        $guardian = User::role('guardian')->find($intId);

        if (!$guardian) {
            abort(422, 'ولي الأمر المحدد غير موجود أو غير مصرح به');
        }

        return $guardian->id;
    }
    private function syncDetailRecord(Student $student, array $data, string $mode): void
    {
        if (isset($data['current_surah']) && !isset($data['current_surah_id'])) {
            $data['current_surah_id'] = $data['current_surah'];
        }
        $entryLevel = $data['center_entry_level'] ?? $student->center_entry_level;

        $map = [
            'construction' => [
                'relation' => 'constructionDetail',
                'fields'   => $this->constructionFields,
                'others'   => ['itqanDetail', 'ibdaDetail'],
            ],
            'mastery' => [
                'relation' => 'itqanDetail',
                'fields'   => $this->itqanFields,
                'others'   => ['constructionDetail', 'ibdaDetail'],
            ],
            'creativity' => [
                'relation' => 'ibdaDetail',
                'fields'   => $this->ibdaFields,
                'others'   => ['constructionDetail', 'itqanDetail'],
            ],
        ];

        if (!isset($map[$entryLevel])) return;

        $config   = $map[$entryLevel];
        $relation = $config['relation'];
        $fields   = array_intersect_key($data, array_flip($config['fields']));

        // ✅ إعادة تسمية الحقل المخصص لكل مستوى إلى اسم العمود الفعلي preferred_time في الجدول
        if ($entryLevel === 'mastery' && array_key_exists('preferred_time_mastery', $fields)) {
            $fields['preferred_time'] = $fields['preferred_time_mastery'];
            unset($fields['preferred_time_mastery']);
        }
        if ($entryLevel === 'creativity' && array_key_exists('preferred_time_creativity', $fields)) {
            $fields['preferred_time'] = $fields['preferred_time_creativity'];
            unset($fields['preferred_time_creativity']);
        }

        if ($entryLevel === 'construction') {
            $studySystem = $fields['study_system'] ?? 'group';
            $circleId    = $fields['circle_id'] ?? null;

            if ($studySystem === 'group' && $circleId) {
                // ✅ لا تُنسخ قيم الخطة الجماعية أبدًا — تُقرأ دائمًا من سجل الحلقة الرئيسي وقت العرض
                // هذا يمنع تضارب البيانات لو تم تعديل خطة الحلقة لاحقًا
                $fields['current_surah_id']      = null;
                $fields['new_memorization_plan']  = null;
                $fields['revision_plan']          = null;
                $fields['old_memorization_plan']  = null;
                $fields['placement_evaluation']  = $fields['placement_evaluation'] ?? null;
            } else {
                // ✅ الفردي: خطة خاصة بالطالب، لازم تتملى من الفورم
                $fields['study_system']          = $studySystem;
                $fields['new_memorization_plan'] = $fields['new_memorization_plan'] ?? '';
                $fields['revision_plan']         = $fields['revision_plan'] ?? '';
                $fields['old_memorization_plan'] = $fields['old_memorization_plan'] ?? '';
            }
        }

        if ($mode === 'create') {
            $student->$relation()->create($fields);
        } else {
            $student->$relation()->updateOrCreate(['student_id' => $student->id], $fields);
            foreach ($config['others'] as $other) {
                $student->$other()->delete();
            }
        }
    }

    // ✅ مزامنة حالة ولي الأمر بناءً على حالة أبنائه
    private function syncGuardianStatus(int|null $guardianId): void
    {
        if (!$guardianId) return;

        $guardian = User::find($guardianId);
        if (!$guardian) return;

        $hasActiveStudents = Student::where('guardian_id', $guardianId)
            ->whereIn('status', ['مقيد', 'مسافر'])
            ->exists();

        $guardian->update([
            'status' => $hasActiveStudents ? 'active' : 'inactive',
        ]);
    }

    // ✅ FIX: مقارنة بيانات الطالب الأساسية + تفاصيل المستوى الحالي (بناء/إتقان/إبداع)
    // مقابل البيانات المُرسَلة، لتحديد هل يوجد تغيير فعلي يستحق التحديث
    private function hasStudentChanges(Student $student, array $studentData, array $rawData): bool
    {
        // 1) مقارنة أعمدة جدول students مباشرة
        foreach ($studentData as $key => $value) {
            $current = $student->{$key};

            // ✅ hobbies مخزّن كـ JSON/array — قارن بعد التطبيع
            if ($key === 'hobbies') {
                $currentArr = is_string($current) ? (json_decode($current, true) ?? []) : (array) ($current ?? []);
                $incomingArr = is_string($value) ? (json_decode($value, true) ?? []) : (array) ($value ?? []);
                sort($currentArr);
                sort($incomingArr);
                if ($currentArr !== $incomingArr) {
                    return true;
                }
                continue;
            }

            // ✅ التواريخ (date_of_birth, join_date, suspended_at) قد تكون Carbon instances
            if ($current instanceof \Illuminate\Support\Carbon) {
                $currentStr = $current->format('Y-m-d');
                $incomingStr = $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d') : null;
                if ($currentStr !== $incomingStr) {
                    return true;
                }
                continue;
            }

            if ((string) ($current ?? '') !== (string) ($value ?? '')) {
                return true;
            }
        }

        // 2) مقارنة تفاصيل المستوى الحالي (construction / mastery / creativity)
        $entryLevel = $rawData['center_entry_level'] ?? $student->center_entry_level;

        $detailMap = [
            'construction' => ['relation' => 'constructionDetail', 'fields' => $this->constructionFields],
            'mastery'      => ['relation' => 'itqanDetail',        'fields' => $this->itqanFields],
            'creativity'   => ['relation' => 'ibdaDetail',         'fields' => $this->ibdaFields],
        ];

        if (isset($detailMap[$entryLevel])) {
            $relation = $detailMap[$entryLevel]['relation'];
            $fields   = $detailMap[$entryLevel]['fields'];
            $detail   = $student->{$relation};

            foreach ($fields as $field) {
                if (!array_key_exists($field, $rawData)) continue;

                // ✅ preferred_time_mastery/creativity يقابلان عمود preferred_time الفعلي في الجدول
                $columnField = in_array($field, ['preferred_time_mastery', 'preferred_time_creativity'])
                    ? 'preferred_time'
                    : $field;

                $current  = $detail?->{$columnField};
                $incoming = $rawData[$field];

                if ((string) ($current ?? '') !== (string) ($incoming ?? '')) {
                    return true;
                }
            }
        }

        return false;
    }
}
