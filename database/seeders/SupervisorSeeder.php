<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Teacher;
use App\Models\Center;
use App\Models\Branch;
use Illuminate\Database\Seeder;

class SupervisorSeeder extends Seeder
{
    /**
     * ملاحظات قبل التشغيل:
     * 1) عدّل الإيميلات (وكلمة المرور لو حبيت) في:
     *    database/seeders/data/supervisors.json
     * 2) شغّل هذا الـ Seeder الأول قبل StudentsImportSeeder:
     *    php artisan db:seed --class=SupervisorSeeder
     * 3) بعد كده StudentsImportSeeder هيلاقي المشرفين موجودين بالفعل
     *    ويربطهم بالاسم بدل ما يعمل إيميلات وهمية.
     * 4) كلمة المرور الافتراضية هنا "password" — غيّرها لكل مشرف
     *    بعد أول تسجيل دخول، أو عدّلها هنا قبل التشغيل.
     */
    public function run(): void
    {
        $path = database_path('seeders/data/supervisors.json');

        if (!file_exists($path)) {
            $this->command->error("ملف البيانات غير موجود: {$path}");
            return;
        }

        $rows = json_decode(file_get_contents($path), true);

        $defaultCenter = Center::where('name', 'صبيح')->firstOrFail();
        $defaultBranch = Branch::where('name', 'الرئيسي')
            ->where('center_id', $defaultCenter->id)
            ->firstOrFail();

        foreach ($rows as $row) {
            $user = User::firstOrCreate(
                ['name' => $row['name']],
                [
                    'email' => $row['email'],
                    'password' => bcrypt('password'),
                    'status' => 'active',
                ]
            );

            Teacher::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'mobile_teacher' => $row['phone'] ?? null,
                    'branch_id' => $defaultBranch->id,
                ]
            );
        }

        $this->command->info('تم إنشاء حسابات المشرفين: ' . count($rows));
    }
}