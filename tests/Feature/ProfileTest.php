<?php

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

test('profile page is displayed', function () {
    // Create role and permission
    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'edit profile', 'guard_name' => 'web']);
    $role->givePermissionTo('edit profile');

    $user = User::factory()->create();
    $user->assignRole('admin');

    $response = \Pest\Laravel\actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('profile information can be updated', function () {
    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'edit profile', 'guard_name' => 'web']);
    $role->givePermissionTo('edit profile');

    $user = User::factory()->create();
    $user->assignRole('admin');

    $response = \Pest\Laravel\actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'edit profile', 'guard_name' => 'web']);
    $role->givePermissionTo('edit profile');

    $user = User::factory()->create();
    $user->assignRole('admin');

    $response = \Pest\Laravel\actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertNotNull($user->refresh()->email_verified_at);
});

// لا توجد ميزة حذف الحساب ذاتيًا في النظام: صفحة البروفايل للعرض والتعديل فقط
// (GET/PATCH)، ولا يوجد route لحذف الحساب — الحسابات تديرها الإدارة.
test('user cannot delete their own account', function () {
    $user = User::factory()->create();

    $response = \Pest\Laravel\actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ]);

    $response->assertStatus(405);
    expect(auth()->check())->toBeTrue();
    $this->assertDatabaseHas('users', ['id' => $user->id]);
});

test('guests cannot view the profile page', function () {
    $response = $this->get('/profile');

    $response->assertRedirect('/login');
});