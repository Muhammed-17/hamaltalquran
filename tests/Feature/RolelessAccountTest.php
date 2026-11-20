<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;

/*
|--------------------------------------------------------------------------
| اختبارات الحساب بلا دور
|--------------------------------------------------------------------------
| حساب بلا أي دور (أُنشئ من أي مصدر غير الـ Seeder، أو حُذف دوره أثناء
| الجلسة) يجب ألا يدخل في حلقة تحويلات بين /dashboard و /login —
| يُسجَّل خروجه برسالة واضحة عند الدخول، ويُخرَج بأمان عند أول 403.
*/

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

test('role-less user is logged out at login instead of looping', function () {
    $user = User::factory()->create(['status' => 'active']);
    // بلا أي دور عمدًا

    $response = $this->post('/login', [
        'email'    => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('user whose role is removed mid-session is safely logged out on 403', function () {
    $user = User::factory()->create(['status' => 'active']);
    // بلا دور: أي صفحة محمية بصلاحية تطلق معالج 403

    $response = $this->actingAs($user)->get('/students');

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});

test('admin without explicit role branch still reaches the dashboard', function () {
    $admin = User::factory()->create(['status' => 'active']);
    $admin->assignRole('admin');

    $response = $this->post('/login', [
        'email'    => $admin->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($admin);
});