<?php

use Williamug\Audited\Causers\SystemCauser;
use Williamug\Audited\Enums\AuditAction;
use Williamug\Audited\Models\AuditLog;
use Williamug\Audited\Services\ActivityLogService;
use Williamug\Audited\Tests\Fixtures\TestProduct;

/**
 * Render the audit-log-table view directly with the given filters.
 * This tests the view logic without requiring Livewire to be installed.
 */
function renderLogTable(array $filters = []): string
{
    $modelClass = config('audit.model', AuditLog::class);

    $search   = $filters['search']   ?? '';
    $action   = $filters['action']   ?? '';
    $module   = $filters['module']   ?? '';
    $level    = $filters['level']    ?? '';
    $platform = $filters['platform'] ?? '';
    $dateFrom = $filters['dateFrom'] ?? '';
    $dateTo   = $filters['dateTo']   ?? '';
    $expandedId = $filters['expandedId'] ?? null;

    $query = $modelClass::query()->latest();

    if ($search) {
        $query->where(function ($q) use ($search) {
            $q->where('user_name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhere('ip_address', 'like', "%{$search}%");
        });
    }

    if ($action)   $query->withAction($action);
    if ($module)   $query->forModule($module);
    if ($level)    $query->where('user_level', $level);
    if ($platform) $query->where('platform', $platform);
    if ($dateFrom) $query->whereDate('created_at', '>=', $dateFrom);
    if ($dateTo)   $query->whereDate('created_at', '<=', $dateTo);

    $hasActiveFilters = (bool) ($search || $action || $module || $level || $platform || $dateFrom || $dateTo);

    return view('audited::livewire.audit-log-table', [
        'logs'             => $query->paginate(15),
        'allActions'       => $modelClass::distinct()->orderBy('action')->pluck('action')->filter()->values(),
        'allModules'       => $modelClass::distinct()->orderBy('module')->pluck('module')->filter()->values(),
        'allLevels'        => $modelClass::distinct()->orderBy('user_level')->pluck('user_level')->filter()->values(),
        'hasActiveFilters' => $hasActiveFilters,
        'expandedId'       => $expandedId,
        'search'           => $search,
        'action'           => $action,
        'module'           => $module,
        'level'            => $level,
        'platform'         => $platform,
        'dateFrom'         => $dateFrom,
        'dateTo'           => $dateTo,
        'perPage'          => 15,
    ])->render();
}

test('table renders all audit log entries', function () {
    ActivityLogService::log(AuditAction::Create, 'Billing', 'Created invoice INV-001');
    ActivityLogService::log(AuditAction::Create, 'Members', 'Created member Jane Doe');

    $html = renderLogTable();

    expect($html)->toContain('Created invoice INV-001')
        ->toContain('Created member Jane Doe');
});

test('table shows empty state when no logs exist', function () {
    $html = renderLogTable();

    expect($html)->toContain('No audit logs found.');
});

test('table filters by search term matching description', function () {
    ActivityLogService::log(AuditAction::Create, 'Billing', 'Created invoice Alpha-001');
    ActivityLogService::log(AuditAction::Create, 'Billing', 'Created invoice Beta-002');

    $html = renderLogTable(['search' => 'Alpha']);

    expect($html)->toContain('Alpha-001')
        ->not->toContain('Beta-002');
});

test('table filters by action', function () {
    ActivityLogService::log(AuditAction::Create, 'Products', 'Created a widget');
    ActivityLogService::log(AuditAction::Update, 'Products', 'Updated the price');

    $html = renderLogTable(['action' => 'update']);

    // The update log entry description is shown; the create log entry is not
    expect($html)->toContain('Updated the price')
        ->not->toContain('Created a widget');
});

test('table filters by module', function () {
    TestProduct::create(['name' => 'Widget', 'price' => 100]);
    ActivityLogService::log(AuditAction::Export, 'Reports', 'Exported data');

    $html = renderLogTable(['module' => 'Reports']);

    expect($html)->toContain('Exported data')
        ->not->toContain('Created TestProduct');
});

test('table filters by platform showing only cli entries', function () {
    TestProduct::create(['name' => 'Widget', 'price' => 100]);

    // Tests run in console so all entries have platform = cli
    $html = renderLogTable(['platform' => 'cli']);

    expect($html)->toContain('CLI');
});

test('table shows causer type badge for system actors', function () {
    ActivityLogService::log(
        AuditAction::Create,
        'Imports',
        'Imported records',
        causer: new SystemCauser('ImportJob', 'job'),
    );

    $html = renderLogTable();

    expect($html)->toContain('ImportJob')
        ->toContain('job');
});

test('table renders platform badge for cli entries', function () {
    TestProduct::create(['name' => 'Widget', 'price' => 100]);

    $html = renderLogTable();

    expect($html)->toContain('CLI');
});

test('table renders View button for each row', function () {
    TestProduct::create(['name' => 'Widget', 'price' => 100]);

    $html = renderLogTable();

    expect($html)->toContain('View');
});

test('table renders expanded detail row when expandedId matches', function () {
    $product = TestProduct::create(['name' => 'Widget', 'price' => 100]);
    $product->update(['price' => 200]);

    $log = AuditLog::withAction('update')->first();

    $html = renderLogTable(['expandedId' => $log->id]);

    expect($html)->toContain('Changes')
        ->toContain('price')
        ->toContain('Before')
        ->toContain('After');
});

test('table renders tags section in expanded row', function () {
    ActivityLogService::log(
        AuditAction::Export,
        'Reports',
        'Exported CSV',
        tags: ['format' => 'csv', 'rows' => 500],
    );

    $log = AuditLog::first();

    $html = renderLogTable(['expandedId' => $log->id]);

    expect($html)->toContain('Tags')
        ->toContain('format')
        ->toContain('csv');
});

test('table does not show Clear Filters button when no filters active', function () {
    $html = renderLogTable();

    expect($html)->not->toContain('Clear Filters');
});

test('table shows Clear Filters button when a filter is active', function () {
    TestProduct::create(['name' => 'Widget', 'price' => 100]);

    $html = renderLogTable(['search' => 'Widget']);

    expect($html)->toContain('Clear Filters');
});

test('table shows row count in pagination footer when results exist', function () {
    // Fill more than one page to trigger hasPages()
    foreach (range(1, 20) as $i) {
        ActivityLogService::log(AuditAction::Create, 'Products', "Created widget {$i}");
    }

    $html = renderLogTable();

    expect($html)->toContain('Showing');
});

test('table action dropdown lists all distinct actions from logs', function () {
    TestProduct::create(['name' => 'Widget', 'price' => 100]);

    $html = renderLogTable();

    expect($html)->toContain('create');
});

test('table module dropdown lists all distinct modules from logs', function () {
    ActivityLogService::log(AuditAction::Export, 'Reports', 'Exported');
    ActivityLogService::log(AuditAction::Create, 'Members', 'Created member');

    $html = renderLogTable();

    expect($html)->toContain('Reports')
        ->toContain('Members');
});
