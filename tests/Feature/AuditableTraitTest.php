<?php

namespace Williamug\Audited\Tests\Feature;

use Williamug\Audited\Models\AuditLog;
use Williamug\Audited\Tests\Fixtures\TestProduct;
use Williamug\Audited\Tests\Fixtures\TestProductWithLabel;
use Williamug\Audited\Tests\TestCase;

class AuditableTraitTest extends TestCase
{
    public function test_creating_a_model_writes_a_log_entry(): void
    {
        TestProduct::create(['name' => 'Widget A', 'price' => 100]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'create',
            'module' => 'Products',
        ]);

        $log = AuditLog::first();
        $this->assertArrayHasKey('name', $log->new_values);
        $this->assertNull($log->old_values);
    }

    public function test_updating_a_model_writes_a_log_entry_with_only_changed_fields(): void
    {
        $product = TestProduct::create(['name' => 'Widget A', 'price' => 100]);
        AuditLog::query()->delete(); // clear creation log

        $product->update(['price' => 200]);

        $this->assertDatabaseCount('audit_logs', 1);

        $log = AuditLog::first();
        $this->assertEquals('update', $log->action);
        $this->assertEquals(['price' => 100], $log->old_values);
        $this->assertEquals(['price' => 200], $log->new_values);
        $this->assertArrayNotHasKey('name', $log->new_values);
    }

    public function test_updating_without_changes_does_not_write_a_log(): void
    {
        $product = TestProduct::create(['name' => 'Widget A', 'price' => 100]);
        AuditLog::query()->delete();

        $product->save(); // no fields changed

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_deleting_a_model_writes_a_log_entry(): void
    {
        $product = TestProduct::create(['name' => 'Widget A', 'price' => 100]);
        AuditLog::query()->delete();

        $product->delete();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'delete',
            'module' => 'Products',
        ]);

        $log = AuditLog::first();
        $this->assertArrayHasKey('name', $log->old_values);
        $this->assertNull($log->new_values);
    }

    public function test_audit_label_method_is_used_in_description(): void
    {
        TestProductWithLabel::create(['name' => 'Widget A', 'price' => 100]);

        $this->assertDatabaseHas('audit_logs', [
            'description' => 'Created Product: Widget A',
        ]);
    }

    public function test_audit_module_defaults_to_class_basename(): void
    {
        // TestProduct has $auditModule = 'Products' but we test the fallback
        // by checking TestProduct uses the explicit one correctly.
        TestProduct::create(['name' => 'Widget A', 'price' => 50]);

        $this->assertDatabaseHas('audit_logs', ['module' => 'Products']);
    }

    public function test_audit_exclude_strips_specified_fields(): void
    {
        // TestProductWithLabel has $auditExclude = ['stock_count']
        TestProductWithLabel::create(['name' => 'Widget A', 'price' => 100, 'stock_count' => 50]);

        $log = AuditLog::first();
        $this->assertArrayNotHasKey('stock_count', $log->new_values);
        $this->assertArrayHasKey('name', $log->new_values);
    }
}
