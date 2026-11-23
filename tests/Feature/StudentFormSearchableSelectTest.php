<?php

use App\Models\{Branch, Center, Circle, Student, User};
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $center = Center::create(['name' => 'مركز']);
    $branch = Branch::create(['name' => 'فرع', 'center_id' => $center->id]);
    $this->circle = Circle::create(['name' => 'حلقة جماعية', 'type' => 'group', 'level' => 'build', 'branch_id' => $branch->id, 'url' => 'https://chat.whatsapp.com/abc']);
    Circle::create(['name' => 'حلقة فردية', 'type' => 'individual', 'level' => 'build', 'branch_id' => $branch->id]);
    $this->admin = User::create(['name' => 'مدير', 'email' => 'admin@form.local', 'password' => Hash::make('Test123!'), 'status' => 'active']);
    $this->admin->assignRole('admin');
    $this->student = Student::create(['name' => 'طالب', 'center_id' => $center->id, 'circle_id' => $this->circle->id, 'status' => 'مقيد', 'decision' => 'مقبول', 'gender' => 'ذكر']);
});

test('student create and edit forms render the six entity fields as searchable selects', function () {
    foreach ([route('students.create'), route('students.edit', $this->student)] as $url) {
        $r = $this->actingAs($this->admin)->get($url);
        $r->assertOk();
        foreach (['supervisor_id', 'center_id', 'circle_id', 'current_surah_id', 'teacher_name', 'supervisor_name'] as $field) {
            $r->assertSee("searchableSelect('{$field}'", false);
        }
        // بيانات الحلقات المضمّنة + واجهة القراءة التي تستخدمها صفحتا الإنشاء والتعديل
        $r->assertSee('ALL_CIRCLES', false)->assertSee('StudentFormCircles', false)
            ->assertSee('حلقة جماعية', false)
            ->assertDontSee('id="circleSelect"', false);
        // القوائم الثابتة القصيرة تبقى selects عادية كما هي
        $r->assertSee('<select name="status"', false)->assertSee('<select name="decision"', false)
            ->assertSee('<select name="education_type"', false)->assertSee('<select name="school_grade"', false);
    }
});

test('edit form embeds the saved circle in the circles data for the plan card and whatsapp url', function () {
    $this->actingAs($this->admin)->get(route('students.edit', $this->student))
        ->assertOk()
        ->assertSee('"value":' . $this->circle->id, false)
        ->assertSee('chat.whatsapp.com', false);
});
