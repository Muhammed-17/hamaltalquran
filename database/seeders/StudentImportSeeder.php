<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\StudentConstructionDetail;
use App\Models\StudentItqanDetail;
use App\Models\StudentIbdaDetail;
use App\Models\Center;
use App\Models\Circle;
use App\Models\Teacher;
use App\Models\Surah;
use App\Models\User;
use App\Models\Branch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StudentImportSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/students_import.json');

        if (!file_exists($path)) {
            $this->command->error("ملف البيانات غير موجود: {$path}");
            return;
        }

        $rows = json_decode(file_get_contents($path), true);

        $this->command->info('عدد السجلات المراد استيرادها: ' . count($rows));

        $skippedCircles = [];

        DB::transaction(function () use ($rows, &$skippedCircles) {
            foreach ($rows as $row) {
                // ── المركز ──────────────────────────────────────
                $centerId = null;
                if (!empty($row['center_name'])) {
                    $center = Center::where('name', $row['center_name'])->first();

                    if (!$center) {
                        $this->command->warn("مركز غير موجود، تم تخطي السجل: {$row['name']} ({$row['center_name']})");
                        continue;
                    }

                    $centerId = $center->id;
                }

                // ── المشرف (مسجل البيانات) ─────────────────────
                $supervisorId = null;
                if (!empty($row['supervisor_name'])) {
                    $user = User::where('name', $row['supervisor_name'])->first();

                    if ($user) {
                        $supervisor = Teacher::where('user_id', $user->id)->first();
                        $supervisorId = $supervisor?->id;
                    }

                    if (!$supervisorId) {
                        $this->command->warn("مشرف غير موجود، تم تخطيه: {$row['supervisor_name']}");
                    }
                }

                // ── الحلقة ──────────────────────────────────────
                // نستخدم الآن circle_name + branch_name معًا (لو متوفرين) بدل
                // البحث باسم الحلقة فقط داخل كل فروع المركز، لأن نفس اسم
                // الحلقة ممكن يتكرر في أكتر من فرع تحت نفس المركز (زي
                // "أبو بكر الصديق" في فرع الرئيسي مقابل فرع الصديق)، وده كان
                // ممكن يودّي لربط الطالب بحلقة غلط.
                $circleId = null;
                if (!empty($row['circle_name']) && $centerId) {
                    $circleQuery = Circle::whereHas('branch', function ($q) use ($centerId, $row) {
                        $q->where('center_id', $centerId);
                        if (!empty($row['branch_name'])) {
                            $q->where('name', trim($row['branch_name']));
                        }
                    })->where('name', trim($row['circle_name']));

                    $matches = $circleQuery->get();

                    if ($matches->count() === 1) {
                        $circleId = $matches->first()->id;
                    } elseif ($matches->count() > 1) {
                        // نفس الاسم في أكتر من فرع ولسه مش متحدد branch_name
                        // في بيانات الطالب → نسجل تحذير بدل ما نخمّن.
                        $this->command->warn(
                            "اسم حلقة غير محدد الفرع (متكرر في أكتر من فرع)، تم تخطيه: " .
                            "{$row['name']} - {$row['circle_name']} ({$row['center_name']})"
                        );
                        $skippedCircles[] = $row['name'];
                    } else {
                        $this->command->warn(
                            "حلقة غير موجودة، تم تخطيها: {$row['circle_name']}" .
                            (!empty($row['branch_name']) ? " / {$row['branch_name']}" : '') .
                            " ({$row['center_name']}) - الطالب: {$row['name']}"
                        );
                        $skippedCircles[] = $row['name'];
                    }
                }

                // ── السورة الحالية (لمستوى البناء) ─────────────
                $surahId = null;
                if (!empty($row['current_surah_name'])) {
                    $surah = Surah::where('name_arabic', 'like', '%' . trim($row['current_surah_name']) . '%')->first();
                    $surahId = $surah?->id;
                }

                // ── إنشاء / تحديث الطالب ────────────────────────
                $student = Student::updateOrCreate(
                    [
                        'name' => $row['name'],
                        'date_of_birth' => $row['date_of_birth'],
                    ],
                    [
                        'gender' => $row['gender'],
                        'second_phone' => $row['second_phone'],
                        'address' => $row['address'],
                        'status' => 'مقيد',
                        'circle_id' => $circleId,
                        'education_type' => $row['education_type'] ?: 'غير محدد',
                        'educational_stage' => $row['educational_stage'],
                        'school_grade' => $row['school_grade'],
                        'previous_school' => $row['previous_school'],
                        'center_entry_level' => $row['center_entry_level'],
                        'join_date' => $row['join_date'] ?? ($row['timestamp'] ? Carbon::parse($row['timestamp'])->format('Y-m-d') : null),
                        'whatsapp_number' => $row['whatsapp_number'],
                        'health_status' => $row['health_status'],
                        'notes' => $row['notes'],
                        'supervisor_id' => $supervisorId,
                        'applicant' => $row['applicant'],
                        'center_id' => $centerId,
                        'whatsapp_owner' => $row['whatsapp_owner'],
                        'additional_contact_owner' => null,
                        'learning_difficulties' => $row['learning_difficulties'],
                        'personal_traits' => $row['personal_traits'],
                        'hobbies' => $row['hobbies'],
                        'reading' => $row['reading'],
                        'student_exit_status' => $row['student_exit_status'],
                        'decision' => 'مقبول',
                        'subscription_fees' => $row['subscription_fees'],
                        'received_tools' => null,
                    ]
                );

                // ── تفاصيل مستوى البناء ──────────────────────────
                if ($row['center_entry_level'] === 'construction') {
                    StudentConstructionDetail::updateOrCreate(
                        ['student_id' => $student->id],
                        [
                            'circle_id' => $circleId,
                            'study_system' => $row['study_system'] ?? 'group',
                            'current_surah_id' => $surahId,
                            'new_memorization_plan' => $row['new_memorization_plan'],
                            'revision_plan' => null,
                            'old_memorization_plan' => $row['old_memorization_plan'],
                            'placement_evaluation' => $row['placement_evaluation'],
                        ]
                    );
                }

                // ── تفاصيل مستوى الإتقان ─────────────────────────
                if ($row['center_entry_level'] === 'mastery') {
                    StudentItqanDetail::updateOrCreate(
                        ['student_id' => $student->id],
                        [
                            'previous_memorization_side' => $row['previous_memorization_side'],
                            'previous_khatamat_count' => $row['previous_khatamat_count'],
                            'current_review_amount' => $row['current_review_amount'],
                            'self_evaluation' => $row['self_evaluation'],
                            'tajweed_matn' => $row['tajweed_matn'],
                            'desired_path' => $row['desired_path'],
                            'preferred_time' => $row['itqan_preferred_time'],
                            'teacher_name' => $row['itqan_teacher_name'],
                        ]
                    );
                }

                // ── تفاصيل مستوى الإبداع ─────────────────────────
                if ($row['center_entry_level'] === 'creativity') {
                    StudentIbdaDetail::updateOrCreate(
                        ['student_id' => $student->id],
                        [
                            'previous_licenses_and_chains' => $row['previous_licenses_and_chains'],
                            'desired_narration_and_path' => $row['desired_narration_and_path'],
                            'preferred_time' => $row['ibda_preferred_time'],
                            'supervisor_name' => null,
                        ]
                    );
                }
            }
        });

        $this->command->info('تم استيراد بيانات الطلاب بنجاح.');

        if (!empty($skippedCircles)) {
            $this->command->warn('عدد الطلاب اللي اتسجلوا بدون حلقة (راجع التحذيرات فوق): ' . count($skippedCircles));
        }
    }
}