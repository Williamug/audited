<?php

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Williamug\Audited\Tests\Fixtures\TestUser;

test('login event writes a log entry', function () {
    $user = TestUser::create(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'password' => 'secret']);

    event(new Login('web', $user, false));

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'login',
        'module' => 'Authentication',
        'user_name' => 'Jane Doe',
        'description' => "User 'Jane Doe' logged in.",
    ]);
});

test('logout event writes a log entry', function () {
    $user = TestUser::create(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'password' => 'secret']);

    event(new Logout('web', $user));

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'logout',
        'module' => 'Authentication',
        'description' => "User 'Jane Doe' logged out.",
    ]);
});

test('logout event with null user does not write a log', function () {
    event(new Logout('web', null));

    $this->assertDatabaseCount('audit_logs', 0);
});

test('failed login event writes a log entry', function () {
    event(new Failed('web', null, ['email' => 'unknown@example.com', 'password' => 'wrong']));

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'failed_login',
        'module' => 'Authentication',
        'description' => "Failed login attempt for 'unknown@example.com'.",
    ]);
});

test('failed login uses configured credential field', function () {
    config(['audit.login_credential_field' => 'phone_number']);

    event(new Failed('web', null, ['phone_number' => '0777000111', 'password' => 'wrong']));

    $this->assertDatabaseHas('audit_logs', [
        'description' => "Failed login attempt for '0777000111'.",
    ]);
});

test('failed login falls back to unknown when credential missing', function () {
    event(new Failed('web', null, ['password' => 'wrong']));

    $this->assertDatabaseHas('audit_logs', [
        'description' => "Failed login attempt for 'unknown'.",
    ]);
});

test('auth module label is configurable', function () {
    config(['audit.auth_module' => 'Security']);

    $user = TestUser::create(['name' => 'Jane', 'email' => 'jane@example.com', 'password' => 'secret']);

    event(new Login('web', $user, false));

    $this->assertDatabaseHas('audit_logs', [
        'module' => 'Security',
    ]);
});
