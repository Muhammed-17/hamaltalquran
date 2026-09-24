<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\StudentItqanDetail;
use App\Models\Center;
use App\Models\Branch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StudentItqanSeeder extends Seeder
{
    /**
     * ملاحظات قبل التشغيل:
     * 1) ضع ملف students_itqan_roster.json في: database/seeders/data/students_itqan_roster.json
     * 2) شغّل: php artisan db:seed --class=StudentsItqanRosterSeeder
     * 3) كل السجلات دي طلاب جدد (مستوى الإتقان) بدون تطابق مع الملفات السابقة.
     * 4) عمود "المسار" في الكشف (تصحيح / إجازة) بيتحفظ في desired_path
     *    داخل جدول student_itqan_details.
     * 5) عمود "الشيخ" بيتحفظ كـ teacher_name (نص حر) في نفس الجدول،
     *    مش مربوط بجدول teachers لأنه مجرد اسم بدون بيانات دخول.
     */
    public function run(): void
    {
        $path = database_path('seeders/data/students_itqan_roster.json');

        if (!file_exists($path)) {
            $this->command->error("ملف البيانات غير موجود: {$path}");
            return;
        }

        $rows = json_decode(file_get_contents($path), true);

        $this->command->info('عدد السجلات المراد استيرادها: ' . count($rows));

        $defaultCenter = Center::where('name', 'صبيح')->firstOrFail();

        DB::transaction(function () use ($rows, $defaultCenter) {
            foreach ($rows as $row) {
                $studentCode = null;
                if ($row['registration_no']) {
                    $baseCode = (string) $row['registration_no'];
                    $studentCode = $baseCode;
                    $suffix = 2;
                    while (Student::where('student_code', $studentCode)->exists()) {
                        $studentCode = $baseCode . '-itqan-' . $suffix;
                        $suffix++;
                    }
                }
                $extraNotes = [];
                if (!empty($row['raw_status']) && $row['raw_status'] !== $row['status']) {
                    $extraNotes[] = 'الحالة الأصلية بالكشف: ' . $row['raw_status'];
                }
                if (!empty($row['notes'])) {
                    $extraNotes[] = $row['notes'];
                }

                $student = Student::create([
                    'name' => $row['name'],
                    'student_code' => $studentCode,
                    'gender' => $row['gender'] ?? 'ذكر',
                    'status' => $row['status'],
                    'decision' => $row['decision'],
                    'whatsapp_number' => $row['whatsapp_number'],
                    'address' => $row['address'],
                    'date_of_birth' => $row['date_of_birth'],
                    'center_entry_level' => 'mastery',
                    'center_id' => $defaultCenter->id,
                    'education_type' => 'غير محدد',
                    'notes' => $extraNotes ? implode(' | ', $extraNotes) : null,
                ]);

                StudentItqanDetail::create([
                    'student_id' => $student->id,
                    'desired_path' => $row['desired_path'],
                    'teacher_name' => $row['teacher_name'],
                ]);
            }
        });

        $this->command->info('تم استيراد كشف الإتقان بنجاح.');
    }
}
