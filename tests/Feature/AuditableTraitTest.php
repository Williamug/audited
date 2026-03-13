<?php

use Williamug\Audited\Models\AuditLog;
use Williamug\Audited\Tests\Fixtures\TestProduct;
use Williamug\Audited\Tests\Fixtures\TestProductWithLabel;

test('creating a model writes a log entry', function () {
    TestProduct::create(['name' => 'Widget A', 'price' => 100]);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'create',
        'module' => 'Products',
    ]);

    $log = AuditLog::first();
    expect($log->new_values)->toHaveKey('name')
        ->and($log->old_values)->toBeNull();
});

test('updating a model writes a log entry with only changed fields', function () {
    $product = TestProduct::create(['name' => 'Widget A', 'price' => 100]);
    AuditLog::query()->delete();

    $product->update(['price' => 200]);

    $this->assertDatabaseCount('audit_logs', 1);

    $log = AuditLog::first();
    expect($log->action)->toBe('update')
        ->and($log->old_values)->toBe(['price' => 100])
        ->and($log->new_values)->toBe(['price' => 200])
        ->and($log->new_values)->not->toHaveKey('name');
});

test('updating without changes does not write a log', function () {
    $product = TestProduct::create(['name' => 'Widget A', 'price' => 100]);
    AuditLog::query()->delete();

    $product->save();

    $this->assertDatabaseCount('audit_logs', 0);
});

test('deleting a model writes a log entry', function () {
    $product = TestProduct::create(['name' => 'Widget A', 'price' => 100]);
    AuditLog::query()->delete();

    $product->delete();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'delete',
        'module' => 'Products',
    ]);

    $log = AuditLog::first();
    expect($log->old_values)->toHaveKey('name')
        ->and($log->new_values)->toBeNull();
});

test('audit label method is used in description', function () {
    TestProductWithLabel::create(['name' => 'Widget A', 'price' => 100]);

    $this->assertDatabaseHas('audit_logs', [
        'description' => 'Created Product: Widget A',
    ]);
});

test('audit module defaults to class basename', function () {
    TestProduct::create(['name' => 'Widget A', 'price' => 50]);

    $this->assertDatabaseHas('audit_logs', ['module' => 'Products']);
});

test('audit exclude strips specified fields', function () {
    TestProductWithLabel::create(['name' => 'Widget A', 'price' => 100, 'stock_count' => 50]);

    $log = AuditLog::first();
    expect($log->new_values)
        ->not->toHaveKey('stock_count')
        ->toHaveKey('name');
});
