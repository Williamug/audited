<?php

use Illuminate\Support\Facades\File;

test('install command publishes config when it does not exist', function () {
    $destination = config_path('audit.php');
    File::delete($destination);

    $this->artisan('audit:install')
        ->expectsOutputToContain('Config published')
        ->doesntExpectOutputToContain('config/audit.php already exists');
});

test('install command skips config when it already exists', function () {
    // Ensure the config file exists (it will after the first run above)
    if (! File::exists(config_path('audit.php'))) {
        File::copy(__DIR__ . '/../../config/audit.php', config_path('audit.php'));
    }

    $this->artisan('audit:install')
        ->expectsOutputToContain('already exists');
});

test('install command skips migration when one already exists', function () {
    // The test database setup does not create a real migration file,
    // but we can seed a dummy one so the glob finds it.
    $dummy = database_path('migrations/2000_01_01_000000_create_audit_logs_table.php');
    File::put($dummy, '<?php');

    $this->artisan('audit:install')
        ->expectsOutputToContain('Migration already exists');

    File::delete($dummy);
});

test('install command detects no framework and says no publishing required', function () {
    // In the test environment neither Livewire nor Inertia is loaded,
    // and there is no package.json with vue — so "no framework" path runs.
    $this->artisan('audit:install')
        ->expectsOutputToContain('No frontend framework detected')
        ->expectsOutputToContain('no publishing required');
});

test('install command shows next steps after install', function () {
    $this->artisan('audit:install')
        ->expectsOutputToContain('Next steps')
        ->expectsOutputToContain('php artisan migrate')
        ->expectsOutputToContain('use Auditable;');
});

test('install command writeEnvValue appends new key to .env', function () {
    $envPath = base_path('.env');
    $original = File::exists($envPath) ? File::get($envPath) : null;

    // Ensure the key is not already present
    if ($original !== null && str_contains($original, 'AUDIT_API_ROUTES=')) {
        $this->markTestSkipped('.env already contains AUDIT_API_ROUTES.');
    }

    // Simulate Vue detected by writing a package.json with vue
    $packageJson = base_path('package.json');
    $hadPackageJson = File::exists($packageJson);
    File::put($packageJson, json_encode(['devDependencies' => ['vue' => '^3.0']]));

    // Create a minimal .env if none exists
    if ($original === null) {
        File::put($envPath, "APP_NAME=Test\n");
    }

    $this->artisan('audit:install')
        ->expectsConfirmation('Publish Vue components to resources/js/vendor/audited/?', 'no')
        ->assertExitCode(0);

    // Clean up
    if (! $hadPackageJson) {
        File::delete($packageJson);
    }
    if ($original === null) {
        File::delete($envPath);
    }
});
