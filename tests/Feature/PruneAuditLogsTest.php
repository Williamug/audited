<?php

use Williamug\Audited\Models\AuditLog;

function logAttributes(array $overrides = []): array
{
    return array_merge([
        'user_id' => null,
        'user_name' => 'Test User',
        'user_level' => null,
        'platform' => 'web',
        'action' => 'create',
        'module' => 'Products',
        'description' => 'Test log entry',
        'old_values' => null,
        'new_values' => null,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'PHPUnit',
    ], $overrides);
}

test('it deletes audit logs older than configured months', function () {
    AuditLog::create(logAttributes(['created_at' => now()->subMonths(4)]));
    AuditLog::create(logAttributes(['created_at' => now()->subMonths(3)->subDay()]));
    $recent = AuditLog::create(logAttributes(['created_at' => now()->subMonths(2)]));

    $this->artisan('audit:prune')
        ->assertSuccessful()
        ->expectsOutputToContain('Pruned 2 audit log(s)');

    $this->assertDatabaseHas('audit_logs', ['id' => $recent->id]);
    $this->assertDatabaseCount('audit_logs', 1);
});

test('it accepts a custom months option', function () {
    AuditLog::create(logAttributes(['created_at' => now()->subMonths(7)]));
    $kept = AuditLog::create(logAttributes(['created_at' => now()->subMonths(5)]));

    $this->artisan('audit:prune', ['--months' => 6])
        ->assertSuccessful()
        ->expectsOutputToContain('Pruned 1 audit log(s)');

    $this->assertDatabaseHas('audit_logs', ['id' => $kept->id]);
});

test('it reports zero when no old logs exist', function () {
    AuditLog::create(logAttributes(['created_at' => now()->subMonth()]));

    $this->artisan('audit:prune')
        ->assertSuccessful()
        ->expectsOutputToContain('Pruned 0 audit log(s)');

    $this->assertDatabaseCount('audit_logs', 1);
});

test('it uses prune after months from config', function () {
    config(['audit.prune_after_months' => 1]);

    AuditLog::create(logAttributes(['created_at' => now()->subMonths(2)]));
    AuditLog::create(logAttributes(['created_at' => now()->subWeeks(2)]));

    $this->artisan('audit:prune')
        ->assertSuccessful()
        ->expectsOutputToContain('Pruned 1 audit log(s)');
});
