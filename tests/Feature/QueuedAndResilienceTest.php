<?php

use Illuminate\Support\Facades\Queue;
use Williamug\Audited\Enums\AuditAction;
use Williamug\Audited\Jobs\WriteAuditLog;
use Williamug\Audited\Models\AuditLog;
use Williamug\Audited\Services\ActivityLogService;

// ── Queue ────────────────────────────────────────────────────────────────────

test('when queue is configured a job is dispatched instead of a direct write', function () {
    Queue::fake();
    config(['audit.queue' => true]);

    ActivityLogService::log(AuditAction::Create, 'Products', 'Created Widget');

    Queue::assertPushed(WriteAuditLog::class);
    $this->assertDatabaseCount('audit_logs', 0);
});

test('when a named queue is configured the job is dispatched on that queue', function () {
    Queue::fake();
    config(['audit.queue' => 'audit']);

    ActivityLogService::log(AuditAction::Create, 'Products', 'Created Widget');

    Queue::assertPushedOn('audit', WriteAuditLog::class);
});

test('the dispatched job writes the log entry when handled', function () {
    config(['audit.queue' => false]);

    $job = new WriteAuditLog(config('audit.model'), [
        'user_id' => null, 'user_name' => null, 'user_level' => null,
        'platform' => 'cli', 'action' => 'create', 'module' => 'Products',
        'description' => 'Created via job', 'old_values' => null, 'new_values' => null,
        'ip_address' => null, 'user_agent' => null,
        'subject_type' => null, 'subject_id' => null, 'request_id' => null,
    ]);

    $job->handle();

    $this->assertDatabaseHas('audit_logs', ['description' => 'Created via job']);
});

// ── Silent failures ───────────────────────────────────────────────────────────

test('with silent_failures enabled exceptions are swallowed', function () {
    config(['audit.silent_failures' => true, 'audit.table' => 'nonexistent_table']);

    // Should not throw even though the table does not exist
    ActivityLogService::log(AuditAction::Create, 'Test', 'This write will fail');

    expect(true)->toBeTrue();
});

test('with silent_failures disabled exceptions bubble up', function () {
    config(['audit.silent_failures' => false, 'audit.table' => 'nonexistent_table']);

    expect(fn () => ActivityLogService::log(AuditAction::Create, 'Test', 'This write will fail'))
        ->toThrow(\Exception::class);
});

// ── Request ID ───────────────────────────────────────────────────────────────

test('all logs within the same request share a request_id', function () {
    ActivityLogService::log(AuditAction::Create, 'Products', 'First log');
    ActivityLogService::log(AuditAction::Update, 'Products', 'Second log');

    $ids = AuditLog::pluck('request_id')->unique();

    expect($ids)->toHaveCount(1)
        ->and($ids->first())->not->toBeNull();
});

test('request_id is a valid uuid', function () {
    ActivityLogService::log(AuditAction::Create, 'Products', 'A log entry');

    $requestId = AuditLog::first()->request_id;

    expect($requestId)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
});
