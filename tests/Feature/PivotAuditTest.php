<?php

use Williamug\Audited\Models\AuditLog;
use Williamug\Audited\Tests\Fixtures\TestPermission;
use Williamug\Audited\Tests\Fixtures\TestRole;
use Williamug\Audited\Tests\Fixtures\TestRoleNoRelAudit;

test('attaching a pivot relationship logs the attached IDs', function () {
    $role = TestRole::create(['name' => 'Admin']);
    $perm = TestPermission::create(['name' => 'edit-posts']);
    AuditLog::query()->delete();

    $role->permissions()->attach($perm->id);

    $this->assertDatabaseCount('audit_logs', 1);

    $log = AuditLog::first();
    expect($log->action)->toBe('update')
        ->and($log->module)->toBe('Roles')
        ->and($log->description)->toContain('Attached permissions')
        ->and($log->new_values)->toBe(['permissions' => [$perm->id]])
        ->and($log->old_values)->toBeNull()
        ->and($log->subject_type)->toBe(TestRole::class)
        ->and($log->subject_id)->toBe($role->id);
});

test('detaching a pivot relationship logs the detached IDs', function () {
    $role = TestRole::create(['name' => 'Admin']);
    $perm = TestPermission::create(['name' => 'edit-posts']);
    $role->permissions()->attach($perm->id);
    AuditLog::query()->delete();

    $role->permissions()->detach($perm->id);

    $this->assertDatabaseCount('audit_logs', 1);

    $log = AuditLog::first();
    expect($log->action)->toBe('update')
        ->and($log->description)->toContain('Detached permissions')
        ->and($log->old_values)->toBe(['permissions' => [$perm->id]])
        ->and($log->new_values)->toBeNull();
});

test('syncing pivot relationships creates separate attach and detach log entries', function () {
    $role  = TestRole::create(['name' => 'Admin']);
    $perm1 = TestPermission::create(['name' => 'edit-posts']);
    $perm2 = TestPermission::create(['name' => 'delete-posts']);
    $role->permissions()->attach($perm1->id);
    AuditLog::query()->delete();

    $role->permissions()->sync([$perm2->id]);

    $this->assertDatabaseCount('audit_logs', 2);

    $actions = AuditLog::pluck('description')->all();
    expect(implode(' ', $actions))
        ->toContain('Detached')
        ->toContain('Attached');
});

test('syncing with no changes does not write any log entries', function () {
    $role = TestRole::create(['name' => 'Admin']);
    $perm = TestPermission::create(['name' => 'edit-posts']);
    $role->permissions()->attach($perm->id);
    AuditLog::query()->delete();

    $role->permissions()->sync([$perm->id]);

    $this->assertDatabaseCount('audit_logs', 0);
});

test('attaching an empty array does not write a log entry', function () {
    $role = TestRole::create(['name' => 'Admin']);
    AuditLog::query()->delete();

    $role->permissions()->attach([]);

    $this->assertDatabaseCount('audit_logs', 0);
});

test('model without auditRelationships does not log pivot changes', function () {
    $role = TestRoleNoRelAudit::create(['name' => 'Editor']);
    $perm = TestPermission::create(['name' => 'edit-posts']);
    AuditLog::query()->delete();

    $role->permissions()->attach($perm->id);
    $role->permissions()->detach($perm->id);

    $this->assertDatabaseCount('audit_logs', 0);
});

test('withoutAudit suppresses pivot log entries', function () {
    $role = TestRole::create(['name' => 'Admin']);
    $perm = TestPermission::create(['name' => 'edit-posts']);
    AuditLog::query()->delete();

    TestRole::withoutAudit(function () use ($role, $perm) {
        $role->permissions()->attach($perm->id);
    });

    $this->assertDatabaseCount('audit_logs', 0);
});

test('pivot log entry is linked to the correct subject model', function () {
    $role = TestRole::create(['name' => 'Admin']);
    $perm = TestPermission::create(['name' => 'edit-posts']);
    AuditLog::query()->delete();

    $role->permissions()->attach($perm->id);

    $log = AuditLog::first();
    expect($log->subject_type)->toBe(TestRole::class)
        ->and($log->subject_id)->toBe($role->id);
});
