<?php

namespace Database\Seeders;

use App\Models\Student;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StudentCircleRegisterSeeder extends Seeder
{
    /**
     * طلاب سجل حلقتي «أبو بكر الصديق» و«عبدالله بن مسعود» (من ملف السجل المرفوع 2026-11-27).
     *
     * ملاحظات قبل التشغيل:
     * 1) شغّل CenterBranchCircleSeeder الأول (الحلقة تُربط بالاسم الحرفي).
     * 2) البيانات في: database/seeders/data/students_circle_register.json
     * 3) Idempotent: المطابقة برقم القيد (student_code) من عمود «م» في السجل،
     *    وإعادة التشغيل تحدّث نفس السجلات ولا تكررها.
     * 4) أعمدة السجل التي لا يقابلها حقل في جدول الطلاب (الفئة، المستوى، خاتم،
     *    معفي من الاشتراك) تُحفظ في notes بنفس أسلوب StudentRosterSeeder.
     * 5) تواريخ الميلاد المستقبلية في السجل (2026) اعتُبرت غير حقيقية وحُذفت —
     *    أُبقي فقط على التواريخ المنطقية (٤ طلاب).
     */
    public function run(): void
    {
        $path = database_path('seeders/data/students_circle_register.json');

        if (!file_exists($path)) {
            $this->command->error("ملف البيانات غير موجود: {$path}");
            return;
        }

        $rows = json_decode(file_get_contents($path), true);

        $this->command->info('عدد السجلات المراد استيرادها: ' . count($rows));

        // الحلقات الحقيقية (name => [circle_id, center_id]) — تحميل دفعة واحدة
        $circles = DB::table('circles')
            ->join('branches', 'circles.branch_id', '=', 'branches.id')
            ->select('circles.id as circle_id', 'circles.name as circle_name', 'branches.center_id')
            ->get()
            ->keyBy('circle_name');

        $missingCircles = [];
        $created = 0;
        $updated = 0;
        $failed = 0;

        foreach ($rows as $row) {
            $circleId = null;
            $centerId = null;

            if (!empty($row['circle_name'])) {
                $match = $circles->get(trim($row['circle_name']));

                if ($match) {
                    $circleId = $match->circle_id;
                    $centerId = $match->center_id;
                } else {
                    $missingCircles[$row['circle_name']] = ($missingCircles[$row['circle_name']] ?? 0) + 1;
                }
            }

            try {
                $student = Student::updateOrCreate(
                    ['student_code' => (string) $row['student_code']],
                    [
                        'name' => $row['name'],
                        'status' => $row['status'] ?? 'مقيد',
                        'decision' => $row['decision'] ?? 'مقبول',
                        'gender' => $row['gender'] ?? 'ذكر',
                        'date_of_birth' => $row['date_of_birth'] ?? null,
                        'education_type' => $row['education_type'] ?? 'غير محدد',
                        'educational_stage' => $row['educational_stage'] ?? null,
                        'school_grade' => $row['school_grade'] ?? null,
                        'circle_id' => $circleId,
                        'center_id' => $centerId,
                        'notes' => $row['notes'] ?? null,
                    ]
                );

                $student->wasRecentlyCreated ? $created++ : $updated++;
            } catch (\Throwable $e) {
                $failed++;
                $this->command->warn("فشل سجل {$row['student_code']} ({$row['name']}): {$e->getMessage()}");
            }
        }

        $this->command->info("تم إنشاء {$created} طالب، وتحديث {$updated}، وفشل {$failed}.");

        if ($missingCircles) {
            foreach ($missingCircles as $name => $count) {
                $this->command->warn("حلقة غير موجودة: {$name} ({$count} طالب سُجّلوا بدون حلقة)");
            }
        }
    }
}
