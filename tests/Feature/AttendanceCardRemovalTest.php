<?php

use App\Models\{Attendance, Branch, Center, Circle, Student, User};
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\{Hash, Schema};

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $center = Center::create(['name' => 'مركز']);
    $branch = Branch::create(['name' => 'فرع', 'center_id' => $center->id]);
    $this->circle = Circle::create(['name' => 'حلقة', 'type' => 'group', 'level' => 'build', 'branch_id' => $branch->id]);
    $this->admin = User::create(['name' => 'مدير', 'email' => 'admin@card.local', 'password' => Hash::make('Test123!'), 'status' => 'active']);
    $this->admin->assignRole('admin');
    $this->student = Student::create(['name' => 'طالب', 'center_id' => $center->id, 'circle_id' => $this->circle->id, 'status' => 'مقيد', 'decision' => 'مقبول', 'gender' => 'ذكر']);
});

test('attendances table no longer has the card column and flows work without it', function () {
    expect(Schema::hasColumn('attendances', 'card'))->toBeFalse();

    // حفظ جماعي (مع قيمة card شاردة في الحمولة تُتجاهل)
    $r = $this->actingAs($this->admin)->post(route('attendance.store'), [
        'circle_id' => $this->circle->id,
        'date' => now()->toDateString(),
        'attendance' => [
            ['student_id' => $this->student->id, 'status' => 'present', 'card' => 7, 'notes' => 'ملحوظة'],
        ],
    ]);
    $r->assertRedirect(route('attendance.index'));
    $record = Attendance::first();
    expect($record)->not->toBeNull()->and($record->status)->toBe('present')->and($record->notes)->toBe('ملحوظة');

    // صفحتا الفهرس والتعديل تعملان بلا حقل card
    $this->actingAs($this->admin)->get(route('attendance.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('attendance.edit', $record))
        ->assertOk()->assertDontSee('name="card"', false);

    // التحديث يعمل (تغيير الحالة فقط)
    $this->actingAs($this->admin)->put(route('attendance.update', $record), [
        'student_id' => $this->student->id,
        'date' => now()->toDateString(),
        'status' => 'absent',
        'notes' => 'ملحوظة',
    ])->assertRedirect(route('attendance.index'));
    expect($record->fresh()->status)->toBe('absent');
});
