<?php

use Illuminate\Support\Facades\Route;
use Williamug\Audited\Enums\AuditAction;
use Williamug\Audited\Http\Controllers\AuditLogApiController;
use Williamug\Audited\Models\AuditLog;
use Williamug\Audited\Services\ActivityLogService;
use Williamug\Audited\Tests\Fixtures\TestProduct;

// Register the API routes for every test in this file
// (normally they are only registered when audit.api_routes = true)
beforeEach(function () {
    Route::middleware([])
        ->prefix('audited/api')
        ->group(function () {
            Route::get('logs',     [AuditLogApiController::class, 'index']);
            Route::get('timeline', [AuditLogApiController::class, 'timeline']);
        });
});

test('index returns paginated log entries as JSON', function () {
    ActivityLogService::log(AuditAction::Create, 'Billing', 'Created invoice INV-001');
    ActivityLogService::log(AuditAction::Update, 'Members', 'Updated member Jane');

    $response = $this->getJson('/audited/api/logs');

    $response->assertOk()
        ->assertJsonStructure(['logs' => ['data', 'total', 'per_page', 'current_page'], 'allActions', 'allModules', 'allLevels'])
        ->assertJsonPath('logs.total', 2);
});

test('index returns computed action_label and action_badge_color', function () {
    ActivityLogService::log(AuditAction::Create, 'Billing', 'Created invoice');

    $response = $this->getJson('/audited/api/logs');

    $response->assertOk()
        ->assertJsonPath('logs.data.0.action_label', 'Create')
        ->assertJsonPath('logs.data.0.action', 'create');

    expect($response->json('logs.data.0.action_badge_color'))->not->toBeEmpty();
});

test('index filters by search term', function () {
    ActivityLogService::log(AuditAction::Create, 'Billing', 'Created invoice Alpha-001');
    ActivityLogService::log(AuditAction::Create, 'Billing', 'Created invoice Beta-002');

    $response = $this->getJson('/audited/api/logs?search=Alpha');

    $response->assertOk()
        ->assertJsonPath('logs.total', 1)
        ->assertJsonPath('logs.data.0.description', 'Created invoice Alpha-001');
});

test('index filters by action', function () {
    ActivityLogService::log(AuditAction::Create, 'Products', 'Created widget');
    ActivityLogService::log(AuditAction::Update, 'Products', 'Updated price');

    $response = $this->getJson('/audited/api/logs?action=update');

    $response->assertOk()
        ->assertJsonPath('logs.total', 1)
        ->assertJsonPath('logs.data.0.description', 'Updated price');
});

test('index filters by module', function () {
    ActivityLogService::log(AuditAction::Create, 'Billing', 'Created invoice');
    ActivityLogService::log(AuditAction::Export, 'Reports', 'Exported data');

    $response = $this->getJson('/audited/api/logs?module=Reports');

    $response->assertOk()
        ->assertJsonPath('logs.total', 1)
        ->assertJsonPath('logs.data.0.module', 'Reports');
});

test('index filters by platform', function () {
    ActivityLogService::log(AuditAction::Create, 'Products', 'Created widget');

    $response = $this->getJson('/audited/api/logs?platform=cli');

    $response->assertOk()
        ->assertJsonPath('logs.total', 1)
        ->assertJsonPath('logs.data.0.platform', 'cli');
});

test('index returns allActions and allModules populated from logs', function () {
    ActivityLogService::log(AuditAction::Create, 'Billing',  'Created invoice');
    ActivityLogService::log(AuditAction::Export, 'Reports',  'Exported CSV');

    $response = $this->getJson('/audited/api/logs');

    $response->assertOk();
    expect($response->json('allActions'))->toContain('create')->toContain('export');
    expect($response->json('allModules'))->toContain('Billing')->toContain('Reports');
});

test('index respects perPage parameter up to maximum of 100', function () {
    foreach (range(1, 5) as $i) {
        ActivityLogService::log(AuditAction::Create, 'Products', "Created widget {$i}");
    }

    $response = $this->getJson('/audited/api/logs?perPage=2');

    $response->assertOk()
        ->assertJsonPath('logs.per_page', 2)
        ->assertJsonPath('logs.total', 5);

    expect($response->json('logs.data'))->toHaveCount(2);
});

test('index clamps perPage to 100', function () {
    $response = $this->getJson('/audited/api/logs?perPage=999');

    $response->assertOk()
        ->assertJsonPath('logs.per_page', 100);
});

test('timeline returns log entries for a subject', function () {
    $product = TestProduct::create(['name' => 'Widget', 'price' => 100]);
    $product->update(['price' => 200]);

    $response = $this->getJson('/audited/api/timeline?subject_type=' . urlencode(TestProduct::class) . '&subject_id=' . $product->id);

    $response->assertOk()
        ->assertJsonStructure(['logs' => ['data', 'total']])
        ->assertJsonPath('logs.total', 2);
});

test('timeline returns empty data for unknown subject', function () {
    $response = $this->getJson('/audited/api/timeline?subject_type=' . urlencode(TestProduct::class) . '&subject_id=999');

    $response->assertOk()
        ->assertJsonPath('logs.total', 0);
});

test('index returns empty data when no logs exist', function () {
    $response = $this->getJson('/audited/api/logs');

    $response->assertOk()
        ->assertJsonPath('logs.total', 0);
});
