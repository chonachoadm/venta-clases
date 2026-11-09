<?php

test('the public self-registration endpoint no longer exists', function () {
    $this->post('/users/create', [
        'name' => 'Intruder',
        'phone' => '123456',
        'email' => 'intruder@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ])->assertNotFound();

    $this->assertDatabaseMissing('users', ['email' => 'intruder@example.com']);
});

test('the placeholder plan selection page no longer exists', function () {
    $this->get('/plan')->assertNotFound();
});
