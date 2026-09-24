<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Center;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TeacherSeeder extends Seeder
{
    public function run(): void
    {
        // ─────────────────────────────────────────
        // 1) تجهيز الفروع بشكل آمن ودقيق
        // ─────────────────────────────────────────
        // جلب الفرع الرئيسي الأول من المركز الرئيسي (مقر الصديق أو الفاروق)
        $mainBranch = Branch::where('name', 'مقر الصديق')->first()
            ?? Branch::whereHas('center', fn($q) => $q->where('name', 'like', '%الرئيسي%'))->first();

        // جلب الفرع الأول من مركز العواسجة (مقر ذو النورين أو مقر علي بن أبي طالب)
        $awasjaBranch = Branch::where('name', 'مقر ذو النورين')->first()
            ?? Branch::whereHas('center', fn($q) => $q->where('name', 'like', '%العواسجة%'))->first();

        // ─────────────────────────────────────────
        // 2) مصفوفة المعلمين مع ربط كل معلم بفرعه
        // ─────────────────────────────────────────
        $teachersData = [
            // --- موظفو فرع العواسجة ---
            [
                'name'           => 'عبدالفتاح أحمد سعدون',
                'email'          => 'abdelfattah@teacher.com',
                'mobile_teacher' => '01033503750',
                'role'           => 'teacher',
                'branch_id'      => $awasjaBranch->id,
            ],
            [
                'name'           => 'محمد عليش',
                'email'          => 'mohamed.alish@teacher.com',
                'mobile_teacher' => '01011112223',
                'role'           => 'teacher',
                'branch_id'      => $awasjaBranch->id,
            ],
            // --- موظفو الفرع الرئيسي ---
            [
                'name'           => 'سعد أحمد سعد الشعراوي',
                'email'          => 'saad@teacher.com',
                'mobile_teacher' => '01032123456',
                'role'           => 'supervisor',
                'branch_id'      => $mainBranch->id,
            ],
            [
                'name'           => 'عبدالبديع أبوالمعاطي',
                'email'          => 'adbelbadea@teacher.com',
                'mobile_teacher' => '01066667778',
                'role'           => 'supervisor',
                'branch_id'      => $mainBranch->id,
            ],
            [
                'name'           => 'محمد الطيب',
                'email'          => 'mohamed.eltayeb@teacher.com',
                'mobile_teacher' => '01090129012',
                'role'           => 'teacher',
                'branch_id'      => $mainBranch->id,
            ],
        ];

        // ─────────────────────────────────────────
        // 3) عملية الإنشاء والحفظ
        // ─────────────────────────────────────────
        foreach ($teachersData as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name'     => $data['name'],
                    'password' => Hash::make('12345678'),
                    'status'   => 'active',
                ]
            );

            if (method_exists($user, 'syncRoles')) {
                $user->syncRoles([$data['role']]);
            }

            Teacher::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'mobile_teacher' => $data['mobile_teacher'],
                    'branch_id'      => $data['branch_id'],
                ]
            );
        }

        $this->command?->info('✅ تم إنشاء المعلمين وربطهم بحسابات المستخدمين والفروع المحددة بنجاح.');
    }
}
