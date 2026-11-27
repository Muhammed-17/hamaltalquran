<?php

/*
|--------------------------------------------------------------------------
| حذف صلاحية manage competitions واستبدالها بدور الأدمن (بقراره)
|--------------------------------------------------------------------------
| الصلاحية حُذفت من الكتالوج والمنح نهائيًا، وآخر قسمين كانا يعملان بها
| (نتائج المسابقة وتصديرها، وفورم النتيجة اليدوية وحفظها) صارت بوابتهما
| role:admin في المسارات والمتحكمين والأزرار. الحامل الوحيد كان الأدمن
| أصلًا فلم يتغير وصول أي مستخدم — لكن لا يمكن منحها بعد اليوم.
*/

use App\Models\Center;
use App\Models\Competition;
use App\Models\CompetitionLevel;
use App\Models\CompetitionParticipant;
use App\Models\ExternalParticipant;
use App\Models\Level;
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

    $this->competition = Competition::create(['name' => 'مسابقة', 'status' => 'active']);
    $level = Level::create(['name' => 'مستوى']);
    $competitionLevel = CompetitionLevel::create([
        'competition_id' => $this->competition->id, 'level_id' => $level->id,
    ]);
    $external = ExternalParticipant::create(['name' => 'مشارك خارجي', 'national_id' => '12345678901234']);
    $center = Center::create(['name' => 'مركز الاختبار']);
    $this->participant = CompetitionParticipant::create([
        'competition_id' => $this->competition->id,
        'competition_level_id' => $competitionLevel->id,
        'center_id' => $center->id,
        'external_participant_id' => $external->id,
    ]);
});

test('الصلاحية لم تعد موجودة في الكتالوج بعد الـ Seeder', function () {
    expect(Permission::where('name', 'manage competitions')->exists())->toBeFalse();

    // مجموعات المسابقات الباقية كما هي
    expect(Permission::where('name', 'view competitions')->exists())->toBeTrue()
        ->and(Permission::where('name', 'examine competition participants')->exists())->toBeTrue()
        ->and(Permission::where('name', 'manage external participants')->exists())->toBeTrue();
});

test('الأدمن وحده يفتح نتائج المسابقة ويصدّرها ويفتح فورم النتيجة اليدوية', function () {
    $this->actingAs($this->admin)->get(route('competitions.results', $this->competition))->assertOk();
    $this->actingAs($this->admin)->get(route('competitions.results.export', $this->competition))->assertOk();
    $this->actingAs($this->admin)->get(route('competitions.participants.manual-result.form', $this->participant))->assertOk();
});

test('غير الأدمن ممنوع من النتائج والنتيجة اليدوية ولو مُنح كل صلاحيات المسابقات المتبقية', function () {
    $this->gm->givePermissionTo([
        'view competitions', 'view competition participants', 'create competition participants',
        'edit competition participants', 'delete competition participants', 'export competition participants',
        'examine competition participants', 'view level questions', 'create level questions',
        'edit level questions', 'delete level questions', 'view competition examiners',
        'select competition examiners', 'delete competition examiners', 'edit examiner levels',
        'select examiner questions', 'manage external participants',
    ]);

    $this->actingAs($this->gm->fresh())->get(route('competitions.results', $this->competition))->assertRedirect();
    $this->actingAs($this->gm->fresh())->get(route('competitions.results.export', $this->competition))->assertRedirect();
    $this->actingAs($this->gm->fresh())->get(route('competitions.participants.manual-result.form', $this->participant))->assertRedirect();
    $this->actingAs($this->gm->fresh())->post(route('competitions.participants.manual-result.store', $this->participant), [
        'total_score' => 50,
    ])->assertRedirect();

    expect($this->participant->competitionResult()->exists())->toBeFalse();
});
