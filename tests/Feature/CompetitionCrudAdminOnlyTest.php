<?php

/*
|--------------------------------------------------------------------------
| حذف صلاحيات إنشاء/تعديل/حذف المسابقات وتحويلها للأدمن فقط (بقراره)
|--------------------------------------------------------------------------
| create/edit/delete competitions حُذفت من كتالوج الصلاحيات نهائيًا،
| وصارت عملياتها بوابة role:admin في المسارات وCompetitionPolicy
| وأزرار صفحة المسابقات. الحاملون لم يتغيروا (كانت مع الأدمن وحده)،
| لكن لا يمكن منحها بعد اليوم لأي دور من صفحة الأدوار.
| view competitions باقية كما هي.
*/

use App\Models\Competition;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $mk = function (string $role) {
        $u = User::factory()->create(['status' => 'active']);
        $u->assignRole($role);

        return $u;
    };

    $this->admin = $mk('admin');
    $this->gm = $mk('general_manager');

    $this->competition = Competition::create(['name' => 'مسابقة قائمة', 'status' => 'active']);
});

test('الصلاحيات الثلاث لم تعد موجودة في الكتالوج بعد الـ Seeder', function () {
    foreach (['create competitions', 'edit competitions', 'delete competitions'] as $name) {
        expect(Permission::where('name', $name)->exists())->toBeFalse($name);
    }

    // الباقي من مجموعة المسابقات كما هو
    expect(Permission::where('name', 'view competitions')->exists())->toBeTrue()
        ->and(Permission::where('name', 'manage external participants')->exists())->toBeTrue();
});

test('الأدمن وحده ينشئ ويعدل ويحذف وينسخ المسابقات', function () {
    $this->actingAs($this->admin)->get(route('competitions.create'))->assertOk();

    $storeResponse = $this->actingAs($this->admin)->post(route('competitions.store'), [
        'name' => 'مسابقة جديدة', 'status' => 'draft',
    ]);
    $created = Competition::where('name', 'مسابقة جديدة')->firstOrFail();
    $storeResponse->assertRedirect(route('competitions.levels', $created));

    $this->actingAs($this->admin)->get(route('competitions.edit', $created))->assertOk();
    $this->actingAs($this->admin)->put(route('competitions.update', $created), [
        'name' => 'مسابقة معدّلة', 'status' => 'active',
    ])->assertRedirect(route('competitions.index'));
    expect($created->fresh()->name)->toBe('مسابقة معدّلة');

    $this->actingAs($this->admin)->post(route('competitions.duplicate', $created))->assertRedirect(route('competitions.index'));
    expect(Competition::where('name', 'مسابقة معدّلة (نسخة)')->exists())->toBeTrue();

    $this->actingAs($this->admin)->delete(route('competitions.destroy', $created))->assertRedirect(route('competitions.index'));
    expect(Competition::find($created->id))->toBeNull();
});

test('غير الأدمن يرى المسابقات ولا ينشئ ولا يعدل ولا يحذف', function () {
    // المدير العام عنده view competitions فيفتح القائمة والعرض
    $this->actingAs($this->gm)->get(route('competitions.index'))->assertOk();

    $this->actingAs($this->gm)->get(route('competitions.create'))->assertRedirect();
    $this->actingAs($this->gm)->post(route('competitions.store'), [
        'name' => 'ممنوعة', 'status' => 'draft',
    ])->assertRedirect();
    $this->actingAs($this->gm)->get(route('competitions.edit', $this->competition))->assertRedirect();
    $this->actingAs($this->gm)->put(route('competitions.update', $this->competition), [
        'name' => 'ممنوعة', 'status' => 'active',
    ])->assertRedirect();
    $this->actingAs($this->gm)->delete(route('competitions.destroy', $this->competition))->assertRedirect();

    expect(Competition::where('name', 'ممنوعة')->exists())->toBeFalse()
        ->and($this->competition->fresh()->name)->toBe('مسابقة قائمة');
});

test('منح كل صلاحيات المسابقات المتبقية لغير الأدمن لا يعيد له إنشاء المسابقات ولا تعديلها ولا حذفها', function () {
    $this->gm->givePermissionTo(['view competitions', 'view competition participants', 'create competition participants', 'edit competition participants', 'delete competition participants', 'export competition participants', 'examine competition participants', 'view level questions', 'create level questions', 'edit level questions', 'delete level questions', 'view competition examiners', 'select competition examiners', 'delete competition examiners', 'edit examiner levels', 'select examiner questions', 'manage external participants']);
    expect($this->gm->fresh()->can('view competitions'))->toBeTrue();

    $this->actingAs($this->gm)->get(route('competitions.create'))->assertRedirect();
    $this->actingAs($this->gm)->get(route('competitions.edit', $this->competition))->assertRedirect();
    $this->actingAs($this->gm)->delete(route('competitions.destroy', $this->competition))->assertRedirect();
    expect(Competition::find($this->competition->id))->not->toBeNull();

    // وعرض المسابقات نفسه باقٍ له كما هو
    $this->actingAs($this->gm)->get(route('competitions.index'))->assertOk();
});
