<?php

// لا يوجد تسجيل عام في النظام: الحسابات تُنشأ من داخل لوحة الإدارة فقط،
// ولا يوجد route باسم /register أصلًا. هذه الاختبارات توثّق هذا السلوك المقصود.

test('registration screen is not available', function () {
    $response = $this->get('/register');

    $response->assertNotFound();
    $this->assertGuest();
});

test('guests cannot register themselves', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertNotFound();
    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
});
