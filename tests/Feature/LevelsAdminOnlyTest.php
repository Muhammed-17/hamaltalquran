<?php

/*
|--------------------------------------------------------------------------
| صفحة المستويات /levels للأدمن فقط (بقراره)
|--------------------------------------------------------------------------
| كانت بوابتها صلاحية manage competitions، فصارت role:admin في المسار
| والمتحكم ورابط الشريط الجانبي. الصلاحية نفسها باقية في الكتالوج وتعمل
| في أقسامها الأخرى (إدارة مستويات المسابقة ونتائجها) — التغيير هنا
| على /levels فقط،
| فحتى من يُمنح manage competitions يدويًا لا يفتح /levels.
*/

use App\Models\Level;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $mk = function (string $role) {
        $u = User::factory()->create(['status' => 'active']);
        $u->assignRole($role);

        return $u;
    };

    $this->admin = $mk('admin');
    $this->gm = $mk('general_manager');
    $this->manager = $mk('manager');

    $this->level = Level::create(['name' => 'مستوى تجريبي']);
});

test('الأدمن وحده يفتح صفحات المستويات وينشئ ويعدل ويحذف', function () {
    $this->actingAs($this->admin)->get(route('levels.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('levels.create'))->assertOk();
    $this->actingAs($this->admin)->get(route('levels.show', $this->level))->assertOk();
    $this->actingAs($this->admin)->get(route('levels.edit', $this->level))->assertOk();

    $this->actingAs($this->admin)->post(route('levels.store'), ['name' => 'مستوى جديد'])->assertRedirect(route('levels.index'));
    $created = Level::where('name', 'مستوى جديد')->firstOrFail();

    $this->actingAs($this->admin)->put(route('levels.update', $created), ['name' => 'مستوى معدّل'])->assertRedirect(route('levels.index'));
    expect($created->fresh()->name)->toBe('مستوى معدّل');

    $this->actingAs($this->admin)->delete(route('levels.destroy', $created))->assertRedirect(route('levels.index'));
    expect(Level::find($created->id))->toBeNull();
});

test('غير الأدمن ممنوع من كل صفحات المستويات وعملياتها', function () {
    foreach ([$this->gm, $this->manager] as $nonAdmin) {
        $this->actingAs($nonAdmin)->get(route('levels.index'))->assertRedirect();
        $this->actingAs($nonAdmin)->get(route('levels.create'))->assertRedirect();
        $this->actingAs($nonAdmin)->get(route('levels.show', $this->level))->assertRedirect();
        $this->actingAs($nonAdmin)->get(route('levels.edit', $this->level))->assertRedirect();
        $this->actingAs($nonAdmin)->post(route('levels.store'), ['name' => 'ممنوع'])->assertRedirect();
        $this->actingAs($nonAdmin)->put(route('levels.update', $this->level), ['name' => 'ممنوع'])->assertRedirect();
        $this->actingAs($nonAdmin)->delete(route('levels.destroy', $this->level))->assertRedirect();
    }

    expect(Level::where('name', 'ممنوع')->exists())->toBeFalse()
        ->and($this->level->fresh()->name)->toBe('مستوى تجريبي');
});

test('منح كل صلاحيات المسابقات المتبقية لغير الأدمن لا يفتح /levels لكنه يفتح الأقسام صاحبة الصلاحيات', function () {
    $this->gm->givePermissionTo(['view competitions', 'view competition participants', 'create competition participants', 'edit competition participants', 'delete competition participants', 'export competition participants', 'examine competition participants', 'view level questions', 'create level questions', 'edit level questions', 'delete level questions', 'view competition examiners', 'select competition examiners', 'delete competition examiners', 'edit examiner levels', 'select examiner questions', 'manage external participants']);
    expect($this->gm->fresh()->can('view competitions'))->toBeTrue();

    $this->actingAs($this->gm)->get(route('levels.index'))->assertRedirect();
    $this->actingAs($this->gm)->get(route('levels.create'))->assertRedirect();

    // الصلاحيات الممنوحة تعمل في أقسامها: المشاركون الخارجيون بصلاحيتهم الخاصة
    $this->actingAs($this->gm)->get(route('external-participants.index'))->assertOk();
});
