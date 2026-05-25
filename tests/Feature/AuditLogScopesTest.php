<?php

use Williamug\Audited\Enums\AuditAction;
use Williamug\Audited\Models\AuditLog;
use Williamug\Audited\Services\ActivityLogService;
use Williamug\Audited\Tests\Fixtures\TestProduct;
use Williamug\Audited\Tests\Fixtures\TestUser;

test('forUser scope filters by user model', function () {
    $user = TestUser::create(['name' => 'Jane', 'email' => 'jane@example.com', 'password' => 'secret']);
    $this->actingAs($user);

    ActivityLogService::log(AuditAction::Create, 'Products', 'Created Widget');
    ActivityLogService::log(AuditAction::Update, 'Products', 'Updated Widget');

    expect(AuditLog::forUser($user)->count())->toBe(2);
});

test('forUser scope filters by user id integer', function () {
    $user = TestUser::create(['name' => 'Jane', 'email' => 'jane@example.com', 'password' => 'secret']);
    $this->actingAs($user);

    ActivityLogService::log(AuditAction::Create, 'Products', 'Created Widget');

    expect(AuditLog::forUser($user->id)->count())->toBe(1);
    expect(AuditLog::forUser(999)->count())->toBe(0);
});

test('forModule scope filters by module name', function () {
    ActivityLogService::log(AuditAction::Create, 'Billing', 'Invoice created');
    ActivityLogService::log(AuditAction::Create, 'Staff', 'Staff added');

    expect(AuditLog::forModule('Billing')->count())->toBe(1);
    expect(AuditLog::forModule('Staff')->count())->toBe(1);
    expect(AuditLog::forModule('Missing')->count())->toBe(0);
});

test('withAction scope filters by AuditAction enum', function () {
    ActivityLogService::log(AuditAction::Create, 'Products', 'Created');
    ActivityLogService::log(AuditAction::Delete, 'Products', 'Deleted');

    expect(AuditLog::withAction(AuditAction::Create)->count())->toBe(1);
    expect(AuditLog::withAction(AuditAction::Delete)->count())->toBe(1);
});

test('withAction scope filters by plain string action', function () {
    ActivityLogService::log('transfer', 'Inventory', 'Moved stock');
    ActivityLogService::log(AuditAction::Create, 'Products', 'Created');

    expect(AuditLog::withAction('transfer')->count())->toBe(1);
});

test('between scope filters by date range', function () {
    // Insert directly to control created_at
    AuditLog::create([
        'user_id' => null, 'user_name' => null, 'user_level' => null,
        'platform' => 'web', 'action' => 'create', 'module' => 'Test',
        'description' => 'Old', 'ip_address' => null, 'user_agent' => null,
        'created_at' => now()->subMonths(2),
    ]);
    AuditLog::create([
        'user_id' => null, 'user_name' => null, 'user_level' => null,
        'platform' => 'web', 'action' => 'create', 'module' => 'Test',
        'description' => 'Recent', 'ip_address' => null, 'user_agent' => null,
        'created_at' => now()->subDays(3),
    ]);

    $count = AuditLog::between(now()->subWeek(), now())->count();

    expect($count)->toBe(1);
});

test('forSubject scope filters by model instance', function () {
    $product = TestProduct::create(['name' => 'Widget A', 'price' => 100]);
    AuditLog::query()->delete(); // clear create log so we start fresh

    $product->update(['price' => 200]);

    $logs = AuditLog::forSubject($product)->get();

    expect($logs)->toHaveCount(1)
        ->and($logs->first()->action)->toBe('update');
});

test('auditLogs relationship on model returns its log entries', function () {
    $product = TestProduct::create(['name' => 'Widget A', 'price' => 100]);
    $product->update(['price' => 200]);

    expect($product->auditLogs)->toHaveCount(2)
        ->and($product->auditLogs->pluck('action')->toArray())->toContain('create', 'update');
});

test('subject relationship on AuditLog resolves back to the model', function () {
    $product = TestProduct::create(['name' => 'Widget A', 'price' => 100]);

    $log = AuditLog::where('action', 'create')->first();

    expect($log->subject)->toBeInstanceOf(TestProduct::class)
        ->and($log->subject->id)->toBe($product->id);
});
