<?php

use App\Models\Examiner;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

/*
|--------------------------------------------------------------------------
| اختبارات الأدوار: الهبوط بعد الدخول وحدود الوصول
|--------------------------------------------------------------------------
| لكل دور: تسجيل الدخول يهبط به على بوابته الصحيحة، وصفحاته تعمل،
| ومناطق غيره مغلقة أمامه (إعادة توجيه لا 200).
*/

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function makeRoleUser(string $role): User
{
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole($role);

    if ($role === 'teacher') {
        Teacher::create(['user_id' => $user->id]);
    }

    if ($role === 'examiner') {
        Examiner::create(['user_id' => $user->id]);
    }

    return $user;
}

test('teacher lands on students index and can view it', function () {
    $teacher = makeRoleUser('teacher');

    $this->post('/login', [
        'email'    => $teacher->email,
        'password' => 'password',
    ])->assertRedirect(route('students.index'));

    $this->actingAs($teacher)->get('/students')->assertOk();
    $this->actingAs($teacher)->get('/dashboard')->assertRedirect();
});

test('guardian lands on guardian dashboard and can view own pages', function () {
    $guardian = makeRoleUser('guardian');

    $this->post('/login', [
        'email'    => $guardian->email,
        'password' => 'password',
    ])->assertRedirect(route('guardian.dashboard.own'));

    $this->actingAs($guardian)->get('/guardian/my-dashboard')->assertOk();
    $this->actingAs($guardian)->get('/guardian/my-attendance')->assertOk();
    $this->actingAs($guardian)->get('/guardian/my-subscription')->assertOk();
    $this->actingAs($guardian)->get('/students')->assertRedirect();
    $this->actingAs($guardian)->get('/dashboard')->assertRedirect();
});

test('examiner lands on examiner dashboard and can view it', function () {
    $examiner = makeRoleUser('examiner');

    $this->post('/login', [
        'email'    => $examiner->email,
        'password' => 'password',
    ])->assertRedirect(route('examiner.dashboard'));

    $this->actingAs($examiner)->get('/examiner/dashboard')->assertOk();
    $this->actingAs($examiner)->get('/students')->assertRedirect();
    $this->actingAs($examiner)->get('/dashboard')->assertRedirect();
});

test('general manager lands on students index', function () {
    $manager = makeRoleUser('general_manager');

    $this->post('/login', [
        'email'    => $manager->email,
        'password' => 'password',
    ])->assertRedirect(route('students.index'));
});

test('guardian hitting a forbidden page with no previous page is redirected to own dashboard', function () {
    $guardian = makeRoleUser('guardian');

    // محاكاة غياب صفحة سابقة مختلفة: السابقة هي نفس صفحة اللوحة المحظورة
    $response = $this->actingAs($guardian)
        ->withSession(['_previous' => ['url' => route('dashboard')]])
        ->get('/dashboard');

    $response->assertRedirect(route('guardian.dashboard.own'));
});
