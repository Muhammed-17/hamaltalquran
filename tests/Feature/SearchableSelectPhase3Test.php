<?php

use App\Models\{Branch, Center, Circle, User};
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(\Database\Seeders\BadgePermissionSeeder::class);
    $center = Center::create(['name' => 'مركز']);
    $branch = Branch::create(['name' => 'فرع', 'center_id' => $center->id]);
    Circle::create(['name' => 'حلقة', 'type' => 'group', 'level' => 'build', 'branch_id' => $branch->id]);
    $this->admin = User::create(['name' => 'مدير', 'email' => 'admin@phase3.local', 'password' => Hash::make('Test123!'), 'status' => 'active']);
    $this->admin->assignRole('admin');
});

test('collections create renders its three context fields as searchable selects with the in-place reload wiring', function () {
    $this->actingAs($this->admin)->get(route('badges.collections.create'))
        ->assertOk()
        ->assertSee("searchableSelect('teacher_id'", false)
        ->assertSee("searchableSelect('circle_id'", false)
        ->assertSee("searchableSelect('student_id'", false)
        ->assertSee('reloadCollectionsWorkspace', false)
        ->assertSee("addEventListener('searchable-change'", false)
        ->assertDontSee('<select name="teacher_id"', false)
        ->assertDontSee('<select name="circle_id"', false)
        ->assertDontSee('<select name="student_id"', false);
});

test('daily sheet renders its context fields as searchable selects with the submit wiring', function () {
    $this->actingAs($this->admin)->get(route('badges.entitlements.create'))
        ->assertOk()
        ->assertSee("searchableSelect('circle_id'", false)
        ->assertSee("searchableSelect('teacher_id'", false)
        ->assertSee('id="sheet-context-form"', false)
        ->assertSee("addEventListener('searchable-change'", false)
        ->assertDontSee('<select name="circle_id"', false)
        ->assertDontSee('<select name="teacher_id"', false);
});
