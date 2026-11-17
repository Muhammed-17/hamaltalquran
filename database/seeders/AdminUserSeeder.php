<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // ✅ firstOrCreate بدل create: كان create() يفشل بخطأ unique constraint
        // عند تشغيل db:seed مرة ثانية بدون --fresh (سيناريو شائع في
        // staging/production لإعادة ضبط الأدوار دون حذف البيانات).
        $user = User::firstOrCreate(
            ['email' => 'markaz@gmail.com'],
            [
                'name' => 'مركز حملة القرآن',
                'password' => bcrypt(env('ADMIN_INITIAL_PASSWORD') ?? throw new \RuntimeException('Set ADMIN_INITIAL_PASSWORD in .env')),
                'status' => 'active',
            ]
        );

        if (!$user->hasRole('admin')) {
            $user->assignRole('admin');
        }

        $this->command->info('✅ تم إنشاء حساب المدير بنجاح.');
    }
}