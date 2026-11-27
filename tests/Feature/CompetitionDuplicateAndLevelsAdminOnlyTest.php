<?php

/*
|--------------------------------------------------------------------------
| نسخ المسابقة وإدارة مستوياتها للأدمن فقط (بقراره)
|--------------------------------------------------------------------------
| كانا يعملان بصلاحية manage competitions، فصارا role:admin:
| مسار النسخ، ومسارات اختيار/حفظ/حذف مستويات المسابقة، وبوابات
| CompetitionLevelController، وأزرار صفحة المستويات.
| عرض مستويات المسابقة نفسه (صفحة القائمة) لم يتغير — بوابته
| view competitions. (وصلاحية manage competitions نفسها حُذفت
| لاحقًا وصارت النتائج والنتيجة اليدوية للأدمن فقط بالدور.)
*/

use App\Models\Competition;
use App\Models\CompetitionLevel;
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

    $this->competition = Competition::create(['name' => 'مسابقة', 'status' => 'active']);
    $this->level = Level::create(['name' => 'مستوى أول']);
    $this->level2 = Level::create(['name' => 'مستوى ثانٍ']);
    $this->competitionLevel = CompetitionLevel::create([
        'competition_id' => $this->competition->id, 'level_id' => $this->level->id,
    ]);
});

test('الأدمن وحده ينسخ المسابقة ويدير مستوياتها', function () {
    $this->actingAs($this->admin)->post(route('competitions.duplicate', $this->competition))
        ->assertRedirect(route('competitions.index'));
    expect(Competition::where('name', 'مسابقة (نسخة)')->exists())->toBeTrue();

    $this->actingAs($this->admin)->get(route('competitions.levels.select', $this->competition))->assertOk();

    $this->actingAs($this->admin)->put(route('competitions.levels.update', $this->competition), [
        'level_ids' => [$this->level->id, $this->level2->id],
    ])->assertRedirect();
    expect($this->competition->competitionLevels()->count())->toBe(2);

    $cl = $this->competition->competitionLevels()->where('level_id', $this->level2->id)->firstOrFail();
    $this->actingAs($this->admin)->delete(route('competitions.levels.destroy', [$this->competition, $cl]))->assertRedirect();
    expect(CompetitionLevel::find($cl->id))->toBeNull();
});

test('غير الأدمن ممنوع من النسخ وإدارة المستويات حتى لو مُنح كل صلاحيات المسابقات المتبقية', function () {
    $this->gm->givePermissionTo(['view competitions', 'view competition participants', 'create competition participants', 'edit competition participants', 'delete competition participants', 'export competition participants', 'examine competition participants', 'view level questions', 'create level questions', 'edit level questions', 'delete level questions', 'view competition examiners', 'select competition examiners', 'delete competition examiners', 'edit examiner levels', 'select examiner questions', 'manage external participants']);

    $this->actingAs($this->gm)->post(route('competitions.duplicate', $this->competition))->assertRedirect();
    expect(Competition::where('name', 'مسابقة (نسخة)')->exists())->toBeFalse();

    $this->actingAs($this->gm)->get(route('competitions.levels.select', $this->competition))->assertRedirect();
    $this->actingAs($this->gm)->put(route('competitions.levels.update', $this->competition), [
        'level_ids' => [$this->level2->id],
    ])->assertRedirect();
    $this->actingAs($this->gm)->delete(route('competitions.levels.destroy', [$this->competition, $this->competitionLevel]))->assertRedirect();

    expect($this->competition->competitionLevels()->count())->toBe(1)
        ->and(CompetitionLevel::find($this->competitionLevel->id))->not->toBeNull();
});

test('عرض مستويات المسابقة لم يتغير والنتائج للأدمن فقط ولو مُنحت كل الصلاحيات', function () {
    // المدير العام عنده view competitions فيرى قائمة مستويات المسابقة
    $this->actingAs($this->gm)->get(route('competitions.levels', $this->competition))->assertOk();

    // النتائج كانت بـ manage competitions وحُذفت فصارت بالدور: ممنوعة ولو مُنحت كل الصلاحيات المتبقية
    $this->gm->givePermissionTo(['view competitions', 'view competition participants', 'create competition participants', 'edit competition participants', 'delete competition participants', 'export competition participants', 'examine competition participants', 'view level questions', 'create level questions', 'edit level questions', 'delete level questions', 'view competition examiners', 'select competition examiners', 'delete competition examiners', 'edit examiner levels', 'select examiner questions', 'manage external participants']);
    $this->actingAs($this->gm->fresh())->get(route('competitions.results', $this->competition))->assertRedirect();
});
