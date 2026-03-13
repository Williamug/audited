<?php

use Williamug\Audited\Enums\AuditAction;
use Williamug\Audited\Models\AuditLog;
use Williamug\Audited\Services\ActivityLogService;
use Williamug\Audited\Tests\Fixtures\TestUser;

test('log creates an audit log entry', function () {
    $user = TestUser::create(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'password' => 'secret']);
    $this->actingAs($user);

    ActivityLogService::log(AuditAction::Create, 'Products', 'Created Widget A');

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $user->id,
        'user_name' => 'Jane Doe',
        'action' => 'create',
        'module' => 'Products',
        'description' => 'Created Widget A',
    ]);
});

test('log accepts a plain string action', function () {
    ActivityLogService::log('transfer', 'Inventory', 'Moved 10 units to Warehouse B');

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'transfer',
        'module' => 'Inventory',
    ]);
});

test('log records old and new values', function () {
    ActivityLogService::log(
        AuditAction::Update,
        'Products',
        'Updated Widget A',
        ['name' => 'Widget A', 'price' => 100],
        ['name' => 'Widget A Pro', 'price' => 150],
    );

    $log = AuditLog::first();

    expect($log->old_values)->toBe(['name' => 'Widget A', 'price' => 100])
        ->and($log->new_values)->toBe(['name' => 'Widget A Pro', 'price' => 150]);
});

test('log strips sensitive fields', function () {
    $user = TestUser::create(['name' => 'Jane', 'email' => 'jane@example.com', 'password' => 'secret']);
    $this->actingAs($user);

    ActivityLogService::log(
        AuditAction::Update,
        'Users',
        'Updated user',
        ['name' => 'Old Name', 'password' => 'old_hash', 'remember_token' => 'abc'],
        ['name' => 'New Name', 'password' => 'new_hash', 'remember_token' => 'xyz'],
    );

    $log = AuditLog::first();

    expect($log->old_values)
        ->not->toHaveKey('password')
        ->not->toHaveKey('remember_token')
        ->toHaveKey('name');

    expect($log->new_values)
        ->not->toHaveKey('password')
        ->not->toHaveKey('remember_token')
        ->toHaveKey('name');
});

test('log works for unauthenticated requests', function () {
    ActivityLogService::log(AuditAction::FailedLogin, 'Authentication', 'Failed login attempt');

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => null,
        'user_name' => null,
        'action' => 'failed_login',
    ]);
});

test('log reads user level from configured field', function () {
    config(['audit.user_level_field' => 'role']);

    $user = TestUser::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret', 'role' => 'Diocese']);
    $this->actingAs($user);

    ActivityLogService::log(AuditAction::Export, 'Reports', 'Exported report');

    $this->assertDatabaseHas('audit_logs', [
        'user_level' => 'Diocese',
    ]);
});

test('log reads user name from configured field', function () {
    config(['audit.user_name_field' => 'email']);

    $user = TestUser::create(['name' => 'Jane', 'email' => 'jane@example.com', 'password' => 'secret']);
    $this->actingAs($user);

    ActivityLogService::log(AuditAction::Create, 'Users', 'Created a user');

    $this->assertDatabaseHas('audit_logs', [
        'user_name' => 'jane@example.com',
    ]);
});
