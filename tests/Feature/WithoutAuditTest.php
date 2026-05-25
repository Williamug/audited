<?php

use Williamug\Audited\Models\AuditLog;
use Williamug\Audited\Tests\Fixtures\TestProduct;
use Williamug\Audited\Tests\Fixtures\TestProductWithLabel;

test('withoutAudit suppresses log entries for that model class', function () {
    TestProduct::withoutAudit(function () {
        TestProduct::create(['name' => 'Widget A', 'price' => 100]);
    });

    $this->assertDatabaseCount('audit_logs', 0);
});

test('withoutAudit only suppresses the target model class', function () {
    TestProduct::withoutAudit(function () {
        TestProduct::create(['name' => 'Widget A', 'price' => 100]);
        TestProductWithLabel::create(['name' => 'Widget B', 'price' => 200]);
    });

    // TestProduct: no log; TestProductWithLabel: logged
    $this->assertDatabaseCount('audit_logs', 1);
    $this->assertDatabaseHas('audit_logs', ['description' => 'Created Product: Widget B']);
});

test('withoutAudit re-enables logging after the callback', function () {
    TestProduct::withoutAudit(function () {
        TestProduct::create(['name' => 'Suppressed', 'price' => 50]);
    });

    TestProduct::create(['name' => 'Logged', 'price' => 100]);

    $this->assertDatabaseCount('audit_logs', 1);
    $this->assertDatabaseHas('audit_logs', ['action' => 'create', 'module' => 'Products']);
});

test('withoutAudit re-enables logging even if the callback throws', function () {
    try {
        TestProduct::withoutAudit(function () {
            throw new RuntimeException('Boom');
        });
    } catch (RuntimeException) {
        // expected
    }

    TestProduct::create(['name' => 'Widget A', 'price' => 100]);

    $this->assertDatabaseCount('audit_logs', 1);
});
