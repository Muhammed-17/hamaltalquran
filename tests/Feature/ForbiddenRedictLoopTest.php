<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| اختبارات حلقة إعادة التوجيه عند 403
|--------------------------------------------------------------------------
| أي مستخدم مسجل يجب أن تنتهي سلسلة تحويلاته دائمًا عند صفحة تُفتح له
| فعلًا أو عند تسجيل خروج نظيف — لا حلقة login ↔ تطبيق، ولا تبادل
| لا نهائي بين صفحتين ممنوعتين عبر قاعدة «الصفحة السابقة».
*/

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

test('user with an unlisted role is logged out cleanly instead of looping through login', function () {
    Role::create(['name' => 'auditor', 'guard_name' => 'web']);
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole('auditor');

    // دور غير معروف للنظام: لا وجهة آمنة له (حتى ملفه الشخصي خلف صلاحية)
    // فالمعالج يخرجه نظيفًا بدل حلقة login ↔ dashboard
    $response = $this->actingAs($user)
        ->from('/students')
        ->get('/students');

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});

test('examiner without profile hitting own dashboard is logged out, not bounced', function () {
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole('examiner');
    // بلا سجل Examiner: لوحته نفسها ترفضه بـ 403

    $response = $this->actingAs($user)
        ->from(route('examiner.dashboard'))
        ->get(route('examiner.dashboard'));

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});

test('ping-pong between two forbidden pages terminates in a safe logout', function () {
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole('examiner');

    // الخطوة 1: من لوحته (الممنوعة عليه) إلى صفحة الطلاب (الممنوعة أيضًا)
    // الارتداد الأول للصفحة السابقة مقبول
    $first = $this->actingAs($user)
        ->from(route('examiner.dashboard'))
        ->get('/students');
    $first->assertRedirect(route('examiner.dashboard'));
    $this->assertAuthenticatedAs($user);

    // الخطوة 2: لوحته ترفضه فورًا — سلسلة 403 متتالية، فلا ارتداد آخر
    // بل خروج آمن بدل التبادل اللانهائي
    $second = $this->actingAs($user)
        ->from('/students')
        ->get(route('examiner.dashboard'));
    $second->assertRedirect(route('login'));
    $this->assertGuest();
});

test('normal user misclick still bounces back to the previous page', function () {
    $teacher = User::factory()->create(['status' => 'active']);
    $teacher->assignRole('teacher');

    $response = $this->actingAs($teacher)
        ->from('/students')
        ->get('/centers');

    $response->assertRedirect('/students');
    $this->assertAuthenticatedAs($teacher);
});
