<?php

/*
|--------------------------------------------------------------------------
| صفحة ملفات التفسير /tafsir-files للأدمن فقط (بقراره)
|--------------------------------------------------------------------------
| كانت بوابتها صلاحية manage competitions، فصارت role:admin في المسار
| والمتحكم ورابط الشريط الجانبي. الصلاحية نفسها باقية وتعمل في أقسامها
| الأخرى (إدارة مستويات المسابقة ونتائجها) — فحتى من يُمنح
| manage competitions يدويًا لا يفتح /tafsir-files، بينما يفتح بها
| صفحة نتائج المسابقة.
*/

use App\Models\TafsirFile;
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

    $this->file = TafsirFile::create(['name' => 'ملف تجريبي']);
});

test('الأدمن وحده يفتح صفحات ملفات التفسير وينشئ ويعدل ويحذف', function () {
    $this->actingAs($this->admin)->get(route('tafsir-files.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('tafsir-files.create'))->assertOk();
    $this->actingAs($this->admin)->get(route('tafsir-files.edit', $this->file))->assertOk();

    $this->actingAs($this->admin)->post(route('tafsir-files.store'), ['name' => 'ملف جديد'])->assertRedirect(route('tafsir-files.index'));
    $created = TafsirFile::where('name', 'ملف جديد')->firstOrFail();

    $this->actingAs($this->admin)->put(route('tafsir-files.update', $created), ['name' => 'ملف معدّل'])->assertRedirect(route('tafsir-files.index'));
    expect($created->fresh()->name)->toBe('ملف معدّل');

    $this->actingAs($this->admin)->delete(route('tafsir-files.destroy', $created))->assertRedirect(route('tafsir-files.index'));
    expect(TafsirFile::find($created->id))->toBeNull();
});

test('غير الأدمن ممنوع من كل صفحات ملفات التفسير وعملياتها', function () {
    foreach ([$this->gm, $this->manager] as $nonAdmin) {
        $this->actingAs($nonAdmin)->get(route('tafsir-files.index'))->assertRedirect();
        $this->actingAs($nonAdmin)->get(route('tafsir-files.create'))->assertRedirect();
        $this->actingAs($nonAdmin)->get(route('tafsir-files.edit', $this->file))->assertRedirect();
        $this->actingAs($nonAdmin)->post(route('tafsir-files.store'), ['name' => 'ممنوع'])->assertRedirect();
        $this->actingAs($nonAdmin)->put(route('tafsir-files.update', $this->file), ['name' => 'ممنوع'])->assertRedirect();
        $this->actingAs($nonAdmin)->delete(route('tafsir-files.destroy', $this->file))->assertRedirect();
    }

    expect(TafsirFile::where('name', 'ممنوع')->exists())->toBeFalse()
        ->and($this->file->fresh()->name)->toBe('ملف تجريبي');
});

test('منح كل صلاحيات المسابقات المتبقية لغير الأدمن لا يفتح /tafsir-files لكنه يفتح الأقسام صاحبة الصلاحيات', function () {
    $this->gm->givePermissionTo(['view competitions', 'view competition participants', 'create competition participants', 'edit competition participants', 'delete competition participants', 'export competition participants', 'examine competition participants', 'view level questions', 'create level questions', 'edit level questions', 'delete level questions', 'view competition examiners', 'select competition examiners', 'delete competition examiners', 'edit examiner levels', 'select examiner questions', 'manage external participants']);
    expect($this->gm->fresh()->can('view competitions'))->toBeTrue();

    $this->actingAs($this->gm)->get(route('tafsir-files.index'))->assertRedirect();
    $this->actingAs($this->gm)->get(route('tafsir-files.create'))->assertRedirect();

    // الصلاحيات الممنوحة تعمل في أقسامها: المشاركون الخارجيون بصلاحيتهم الخاصة
    $this->actingAs($this->gm)->get(route('external-participants.index'))->assertOk();
});
