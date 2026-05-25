<?php

use Williamug\Audited\Causers\SystemCauser;
use Williamug\Audited\Enums\AuditAction;
use Williamug\Audited\Models\AuditLog;
use Williamug\Audited\Services\ActivityLogService;
use Williamug\Audited\Tests\Fixtures\TestProduct;
use Williamug\Audited\Tests\Fixtures\TestUser;

test('causer_type is user when an authenticated user is the actor', function () {
    $user = TestUser::create(['name' => 'Jane', 'email' => 'jane@example.com', 'password' => 'secret']);
    $this->actingAs($user);

    ActivityLogService::log(AuditAction::Create, 'Products', 'Created a widget');

    $log = AuditLog::first();

    expect($log->causer_type)->toBe('user')
        ->and($log->user_id)->toBe($user->id)
        ->and($log->user_name)->toBe('Jane');
});

test('causer_type is null when there is no actor', function () {
    ActivityLogService::log(AuditAction::FailedLogin, 'Authentication', 'Failed login attempt');

    $log = AuditLog::first();

    expect($log->causer_type)->toBeNull()
        ->and($log->user_id)->toBeNull()
        ->and($log->user_name)->toBeNull();
});

test('SystemCauser stores causer_type and name without a user_id', function () {
    ActivityLogService::log(
        AuditAction::Create,
        'Products',
        'Bulk import via job',
        causer: new SystemCauser('ImportProductsJob', 'job'),
    );

    $log = AuditLog::first();

    expect($log->causer_type)->toBe('job')
        ->and($log->user_name)->toBe('ImportProductsJob')
        ->and($log->user_id)->toBeNull()
        ->and($log->user_level)->toBeNull();
});

test('SystemCauser defaults causer_type to system', function () {
    ActivityLogService::log(
        AuditAction::Export,
        'Reports',
        'Nightly report export',
        causer: new SystemCauser('NightlyReportExport'),
    );

    expect(AuditLog::first()->causer_type)->toBe('system');
});

test('SystemCauser does not set auth_guard', function () {
    ActivityLogService::log(
        AuditAction::Delete,
        'Cache',
        'Cleared cache',
        causer: new SystemCauser('CacheClearCommand', 'command'),
    );

    expect(AuditLog::first()->auth_guard)->toBeNull();
});

test('Auditable trait create event still sets causer_type user when authenticated', function () {
    $user = TestUser::create(['name' => 'Bob', 'email' => 'bob@example.com', 'password' => 'secret']);
    $this->actingAs($user);

    TestProduct::create(['name' => 'Widget', 'price' => 50]);

    expect(AuditLog::first()->causer_type)->toBe('user');
});

test('Auditable trait create event sets causer_type null when unauthenticated', function () {
    TestProduct::create(['name' => 'Widget', 'price' => 50]);

    expect(AuditLog::first()->causer_type)->toBeNull();
});

test('custom Causer implementation works as causer', function () {
    $customCauser = new class implements \Williamug\Audited\Contracts\Causer {
        public function getCauserName(): string { return 'DataMigration v2'; }
        public function getCauserType(): string { return 'migration'; }
    };

    ActivityLogService::log(AuditAction::Update, 'Schema', 'Applied data migration', causer: $customCauser);

    $log = AuditLog::first();

    expect($log->causer_type)->toBe('migration')
        ->and($log->user_name)->toBe('DataMigration v2')
        ->and($log->user_id)->toBeNull();
});
