<?php

/*
|--------------------------------------------------------------------------
| المختبرون للأدمن فقط + صلاحية خاصة للمشاركين الخارجيين (بقراره)
|--------------------------------------------------------------------------
| /examiners: كانت بوابتها manage competitions فصارت role:admin في المسار
| والمتحكم ورابط الشريط الجانبي — لا يفتحها أي منح يدوي.
| /external-participants: خرجت من manage competitions إلى صلاحيتها
| الخاصة الجديدة manage external participants («إدارة المشاركين
| الخارجيين»، مجموعة المسابقات) الممنوحة للأدمن فقط مبدئيًا، فالوصول
| الحالي لم يتغير، ويستطيع الأدمن منحها لأي دور من صفحة الأدوار.
| (وصلاحية manage competitions نفسها حُذفت لاحقًا من الكتالوج.)
*/

use App\Models\Examiner;
use App\Models\ExternalParticipant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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

    $examinerUser = User::factory()->create(['status' => 'active']);
    $examinerUser->assignRole('examiner');
    $this->examiner = Examiner::create(['user_id' => $examinerUser->id]);

    $this->external = ExternalParticipant::create(['name' => 'مشارك خارجي', 'national_id' => '12345678901234']);
});

test('الأدمن وحده يفتح صفحات المختبرين وينشئ ويحذف', function () {
    $this->actingAs($this->admin)->get(route('examiners.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('examiners.create'))->assertOk();
    $this->actingAs($this->admin)->get(route('examiners.show', $this->examiner))->assertOk();
    $this->actingAs($this->admin)->get(route('examiners.edit', $this->examiner))->assertOk();

    $this->actingAs($this->admin)->post(route('examiners.store'), [
        'name' => 'مختبر جديد', 'email' => 'new.examiner@test.local',
        'password' => 'password123', 'status' => 'active',
    ])->assertRedirect(route('examiners.index'));
    $created = Examiner::whereHas('user', fn ($q) => $q->where('email', 'new.examiner@test.local'))->firstOrFail();

    $this->actingAs($this->admin)->delete(route('examiners.destroy', $created))->assertRedirect(route('examiners.index'));
    expect(Examiner::find($created->id))->toBeNull();
});

test('غير الأدمن ممنوع من المختبرين حتى لو مُنح صلاحيات المسابقات والمشاركين الخارجيين', function () {
    $this->gm->givePermissionTo(['view competitions', 'examine competition participants', 'manage external participants']);

    $this->actingAs($this->gm)->get(route('examiners.index'))->assertRedirect();
    $this->actingAs($this->gm)->get(route('examiners.create'))->assertRedirect();
    $this->actingAs($this->gm)->get(route('examiners.show', $this->examiner))->assertRedirect();
    $this->actingAs($this->gm)->get(route('examiners.edit', $this->examiner))->assertRedirect();
    $this->actingAs($this->gm)->delete(route('examiners.destroy', $this->examiner))->assertRedirect();
    expect(Examiner::find($this->examiner->id))->not->toBeNull();
});

test('الصلاحية الجديدة موجودة في الكتالوج ومع الأدمن فقط مبدئيًا', function () {
    expect(Permission::where('name', 'manage external participants')->exists())->toBeTrue()
        ->and(Role::findByName('admin')->hasPermissionTo('manage external participants'))->toBeTrue();

    foreach (['general_manager', 'manager', 'supervisor', 'teacher', 'examiner', 'guardian'] as $role) {
        expect(Role::findByName($role)->hasPermissionTo('manage external participants'))->toBeFalse($role);
    }
});

test('المشاركون الخارجيون بصلاحيتهم الخاصة: الأدمن يديرهم وغير الحامل ممنوع', function () {
    $this->actingAs($this->admin)->get(route('external-participants.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('external-participants.create'))->assertOk();
    $this->actingAs($this->admin)->get(route('external-participants.show', $this->external))->assertOk();
    $this->actingAs($this->admin)->get(route('external-participants.edit', $this->external))->assertOk();

    $this->actingAs($this->admin)->post(route('external-participants.store'), [
        'name' => 'مشارك جديد', 'national_id' => '98765432109876',
    ])->assertRedirect(route('external-participants.index'));
    $created = ExternalParticipant::where('national_id', '98765432109876')->firstOrFail();

    $this->actingAs($this->admin)->put(route('external-participants.update', $created), [
        'name' => 'مشارك معدّل', 'national_id' => '98765432109876',
    ])->assertRedirect(route('external-participants.index'));
    expect($created->fresh()->name)->toBe('مشارك معدّل');

    $this->actingAs($this->admin)->delete(route('external-participants.destroy', $created))->assertRedirect(route('external-participants.index'));
    expect(ExternalParticipant::find($created->id))->toBeNull();

    // مدير عام بلا الصلاحية الخاصة: ممنوع من كل العمليات
    $this->actingAs($this->gm)->get(route('external-participants.index'))->assertRedirect();
    $this->actingAs($this->gm)->post(route('external-participants.store'), [
        'name' => 'ممنوع', 'national_id' => '11111111111111',
    ])->assertRedirect();
    expect(ExternalParticipant::where('name', 'ممنوع')->exists())->toBeFalse();
});

test('صلاحيات المسابقات الأخرى لا تفتح المشاركين الخارجيين، وصلاحيتهم الخاصة تفتحهم', function () {
    $this->manager->givePermissionTo('examine competition participants');
    $this->actingAs($this->manager)->get(route('external-participants.index'))->assertRedirect();

    $this->manager->givePermissionTo('manage external participants');
    $this->actingAs($this->manager->fresh())->get(route('external-participants.index'))->assertOk();
    $this->actingAs($this->manager->fresh())->get(route('external-participants.create'))->assertOk();

    // وبصلاحيته الخاصة لا يقترب من المختبرين (أدمن فقط بالدور)
    $this->actingAs($this->manager->fresh())->get(route('examiners.index'))->assertRedirect();
});
