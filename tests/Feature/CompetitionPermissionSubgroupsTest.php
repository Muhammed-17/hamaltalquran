<?php

/*
|--------------------------------------------------------------------------
| مجموعات فرعية لصلاحيات المسابقات + عناوين بارزة في صفحة الأدوار (بقراره)
|--------------------------------------------------------------------------
| صلاحيات المسابقات الـ17 كانت كلها تحت مجموعة واحدة «المسابقات» فبدت
| كتلة واحدة مربكة. قُسّمت في الكتالوج إلى ثلاث مجموعات فرعية (أسئلة
| مستوى المسابقة / مختبرين المسابقة / مشاركو المسابقة) وبقيت العامة تحت
| «المسابقات»، وأضيفت عناوينها في واجهة الأدوار، وصار رأس كل مجموعة
| شريطًا بارزًا بعدد صلاحياتها والمفعّل منها للدور. أسماء الصلاحيات
| وبواباتها وحاملوها لم تتغير إطلاقًا.
*/

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

test('صلاحيات المسابقات موزعة على مجموعتها العامة وثلاث مجموعات فرعية', function () {
    $groupOf = fn (string $name) => Permission::where('name', $name)->firstOrFail()->group;

    expect($groupOf('view competitions'))->toBe('competitions')
        ->and($groupOf('manage external participants'))->toBe('competitions');

    foreach (['view level questions', 'create level questions', 'edit level questions', 'delete level questions'] as $name) {
        expect($groupOf($name))->toBe('competitions questions', $name);
    }

    foreach (['view competition examiners', 'select competition examiners', 'edit examiner levels', 'delete competition examiners', 'select examiner questions'] as $name) {
        expect($groupOf($name))->toBe('competitions examiners', $name);
    }

    foreach (['view competition participants', 'create competition participants', 'edit competition participants', 'delete competition participants', 'export competition participants', 'examine competition participants'] as $name) {
        expect($groupOf($name))->toBe('competitions participants', $name);
    }

    // لا صلاحية فُقدت في التقسيم: المجموعات الأربع تحمل الـ17 كلها
    $total = Permission::whereIn('group', ['competitions', 'competitions questions', 'competitions examiners', 'competitions participants'])->count();
    expect($total)->toBe(17);
});

test('صفحة الأدوار تعرض العناوين الفرعية للمسابقات وعدّاد كل مجموعة', function () {
    $admin = User::factory()->create(['status' => 'active']);
    $admin->assignRole('admin');

    $this->actingAs($admin)->get(route('admin.roles.index'))
        ->assertOk()
        ->assertSee('أسئلة مستوى المسابقة')
        ->assertSee('مختبرين المسابقة')
        ->assertSee('مشاركو المسابقة')
        ->assertSee('مفعّلة', false);
});
