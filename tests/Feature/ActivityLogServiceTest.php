<?php

namespace Williamug\Audited\Tests\Feature;

use Williamug\Audited\Enums\AuditAction;
use Williamug\Audited\Models\AuditLog;
use Williamug\Audited\Services\ActivityLogService;
use Williamug\Audited\Tests\Fixtures\TestUser;
use Williamug\Audited\Tests\TestCase;

class ActivityLogServiceTest extends TestCase
{
    public function test_log_creates_an_audit_log_entry(): void
    {
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
    }

    public function test_log_accepts_a_plain_string_action(): void
    {
        ActivityLogService::log('transfer', 'Inventory', 'Moved 10 units to Warehouse B');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'transfer',
            'module' => 'Inventory',
        ]);
    }

    public function test_log_records_old_and_new_values(): void
    {
        ActivityLogService::log(
            AuditAction::Update,
            'Products',
            'Updated Widget A',
            ['name' => 'Widget A', 'price' => 100],
            ['name' => 'Widget A Pro', 'price' => 150],
        );

        $log = AuditLog::first();

        $this->assertEquals(['name' => 'Widget A', 'price' => 100], $log->old_values);
        $this->assertEquals(['name' => 'Widget A Pro', 'price' => 150], $log->new_values);
    }

    public function test_log_strips_sensitive_fields(): void
    {
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

        $this->assertArrayNotHasKey('password', $log->old_values);
        $this->assertArrayNotHasKey('remember_token', $log->old_values);
        $this->assertArrayNotHasKey('password', $log->new_values);
        $this->assertArrayNotHasKey('remember_token', $log->new_values);
        $this->assertArrayHasKey('name', $log->old_values);
        $this->assertArrayHasKey('name', $log->new_values);
    }

    public function test_log_works_for_unauthenticated_requests(): void
    {
        ActivityLogService::log(AuditAction::FailedLogin, 'Authentication', 'Failed login attempt');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => null,
            'user_name' => null,
            'action' => 'failed_login',
        ]);
    }

    public function test_log_reads_user_level_from_configured_field(): void
    {
        config(['audit.user_level_field' => 'role']);

        $user = TestUser::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret', 'role' => 'Diocese']);
        $this->actingAs($user);

        ActivityLogService::log(AuditAction::Export, 'Reports', 'Exported report');

        $this->assertDatabaseHas('audit_logs', [
            'user_level' => 'Diocese',
        ]);
    }

    public function test_log_reads_user_name_from_configured_field(): void
    {
        config(['audit.user_name_field' => 'email']);

        $user = TestUser::create(['name' => 'Jane', 'email' => 'jane@example.com', 'password' => 'secret']);
        $this->actingAs($user);

        ActivityLogService::log(AuditAction::Create, 'Users', 'Created a user');

        $this->assertDatabaseHas('audit_logs', [
            'user_name' => 'jane@example.com',
        ]);
    }
}
