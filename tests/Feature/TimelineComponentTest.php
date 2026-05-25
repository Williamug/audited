<?php

use Williamug\Audited\Enums\AuditAction;
use Williamug\Audited\Models\AuditLog;
use Williamug\Audited\Services\ActivityLogService;
use Williamug\Audited\Tests\Fixtures\TestProduct;
use Williamug\Audited\View\Components\Timeline;

// Helper that renders the timeline component to an HTML string.
function renderTimeline(TestProduct $subject, array $props = []): string
{
    $component = new Timeline(
        subject: $subject,
        limit: $props['limit'] ?? 25,
        showValues: $props['showValues'] ?? false,
    );

    return $component->render()->render();
}

test('timeline renders log entries for the given subject', function () {
    $product = TestProduct::create(['name' => 'Widget A', 'price' => 100]);

    $html = renderTimeline($product);

    expect($html)->toContain('Created TestProduct #' . $product->id);
});

test('timeline shows the action badge label', function () {
    $product = TestProduct::create(['name' => 'Widget A', 'price' => 100]);
    $product->update(['price' => 200]);

    $html = renderTimeline($product);

    expect($html)->toContain('Create')
        ->toContain('Update');
});

test('timeline respects the limit prop', function () {
    $product = TestProduct::create(['name' => 'Widget', 'price' => 10]);

    foreach (range(1, 5) as $i) {
        $product->update(['price' => $i * 10]);
    }

    // 1 create + 5 updates = 6 logs; limit to 2
    $html = renderTimeline($product, ['limit' => 2]);

    // Only 2 entries rendered — assert a landmark present in each entry is counted correctly
    expect(substr_count($html, 'h-2 w-2 rounded-full'))->toBe(2);
});

test('timeline renders empty state when subject has no logs', function () {
    // Create the model without going through the Auditable trait
    $product = new TestProduct(['name' => 'Ghost', 'price' => 0]);
    $product->saveQuietly();
    AuditLog::query()->delete();

    $html = renderTimeline($product);

    expect($html)->toContain('No audit history found.');
});

test('timeline does not render values diff by default', function () {
    $product = TestProduct::create(['name' => 'Widget', 'price' => 100]);
    $product->update(['price' => 200]);

    $html = renderTimeline($product, ['showValues' => false]);

    expect($html)->not->toContain('<table');
});

test('timeline renders old and new values when showValues is true', function () {
    $product = TestProduct::create(['name' => 'Widget', 'price' => 100]);
    $product->update(['price' => 200]);

    $html = renderTimeline($product, ['showValues' => true]);

    expect($html)->toContain('<table')
        ->toContain('Before')
        ->toContain('After')
        ->toContain('price');
});

test('timeline shows causer type badge for non-user causers', function () {
    $product = TestProduct::create(['name' => 'Widget', 'price' => 100]);

    AuditLog::query()->delete();

    ActivityLogService::log(
        AuditAction::Update,
        'Products',
        'Updated via import job',
        subject: $product,
        causer: new \Williamug\Audited\Causers\SystemCauser('ImportJob', 'job'),
    );

    $html = renderTimeline($product);

    expect($html)->toContain('ImportJob')
        ->toContain('job');
});

test('timeline does not render causer type badge for user actors', function () {
    $product = TestProduct::create(['name' => 'Widget', 'price' => 100]);
    AuditLog::query()->delete();

    ActivityLogService::log(AuditAction::Create, 'Products', 'Created widget', subject: $product);

    $html = renderTimeline($product);

    // The yellow system badge should NOT appear for user-type causers
    expect($html)->not->toContain('bg-yellow-100 text-yellow-700');
});

test('action label accessor formats known action correctly', function () {
    $log = AuditLog::create([
        'action'      => 'create',
        'module'      => 'Test',
        'description' => 'Test',
        'platform'    => 'cli',
    ]);

    expect($log->action_label)->toBe('Create');
});

test('action label accessor title-cases unknown custom actions', function () {
    $log = AuditLog::create([
        'action'      => 'bulk_import',
        'module'      => 'Test',
        'description' => 'Test',
        'platform'    => 'cli',
    ]);

    expect($log->action_label)->toBe('Bulk Import');
});

test('action badge color accessor returns fallback for unknown actions', function () {
    $log = AuditLog::create([
        'action'      => 'custom_action',
        'module'      => 'Test',
        'description' => 'Test',
        'platform'    => 'cli',
    ]);

    expect($log->action_badge_color)->toContain('bg-gray-100');
});
