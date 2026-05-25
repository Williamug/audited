<?php

use Williamug\Audited\Causers\SystemCauser;
use Williamug\Audited\Enums\AuditAction;
use Williamug\Audited\Facades\Audited;
use Williamug\Audited\Models\AuditLog;

test('Facade writes a log entry', function () {
    Audited::log(AuditAction::Create, 'Billing', 'Created invoice INV-001');

    expect(AuditLog::count())->toBe(1)
        ->and(AuditLog::first()->description)->toBe('Created invoice INV-001');
});

test('Facade accepts a plain string action', function () {
    Audited::log('bulk_import', 'Imports', 'Bulk import completed');

    expect(AuditLog::first()->action)->toBe('bulk_import');
});

test('Facade accepts named arguments', function () {
    Audited::log(
        action: AuditAction::Export,
        module: 'Reports',
        description: 'Exported CSV',
        tags: ['rows' => 500],
    );

    $log = AuditLog::first();

    expect($log->module)->toBe('Reports')
        ->and($log->tags['rows'])->toBe(500);
});

test('Facade accepts a SystemCauser', function () {
    Audited::log(
        AuditAction::Create,
        'Imports',
        'Imported records',
        causer: new SystemCauser('ImportJob', 'job'),
    );

    $log = AuditLog::first();

    expect($log->user_name)->toBe('ImportJob')
        ->and($log->causer_type)->toBe('job')
        ->and($log->user_id)->toBeNull();
});

test('helper function writes a log entry', function () {
    audited(AuditAction::Update, 'Staff', 'Updated staff record');

    expect(AuditLog::count())->toBe(1)
        ->and(AuditLog::first()->description)->toBe('Updated staff record');
});

test('helper function accepts a plain string action', function () {
    audited('approve', 'Workflows', 'Approved leave request');

    expect(AuditLog::first()->action)->toBe('approve');
});

test('helper function accepts named arguments', function () {
    audited(
        action: AuditAction::Delete,
        module: 'Members',
        description: 'Deleted member account',
        tags: ['reason' => 'request'],
    );

    $log = AuditLog::first();

    expect($log->module)->toBe('Members')
        ->and($log->tags['reason'])->toBe('request');
});

test('helper function accepts a SystemCauser', function () {
    audited(
        AuditAction::Create,
        'Jobs',
        'Processed nightly sync',
        causer: new SystemCauser('NightlySyncJob'),
    );

    $log = AuditLog::first();

    expect($log->user_name)->toBe('NightlySyncJob')
        ->and($log->causer_type)->toBe('system');
});

test('Facade and ActivityLogService write to the same table', function () {
    Audited::log(AuditAction::Create, 'A', 'From facade');
    audited(AuditAction::Create, 'B', 'From helper');
    \Williamug\Audited\Services\ActivityLogService::log(AuditAction::Create, 'C', 'From service');

    expect(AuditLog::count())->toBe(3);
});
