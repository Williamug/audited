<?php

namespace Williamug\Audited\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallAudit extends Command
{
    protected $signature = 'audit:install';

    protected $description = 'Install Audited: publish config, migration, and optional view assets';

    public function handle(): void
    {
        $this->info('Installing Audited...');
        $this->newLine();

        $this->publishConfig();
        $this->publishMigration();

        $this->newLine();

        $stack = $this->detectStack();
        $this->reportDetection($stack);

        $this->newLine();

        $this->offerViews($stack);

        $this->newLine();
        $this->showNextSteps($stack);
    }

    // ── Core publishing ───────────────────────────────────────────────────────

    private function publishConfig(): void
    {
        $destination = config_path('audit.php');

        if (File::exists($destination)) {
            $this->line('  <fg=yellow>~</> config/audit.php already exists — skipped.');
            return;
        }

        $this->callSilently('vendor:publish', ['--tag' => 'audit-config']);
        $this->line('  <fg=green>✓</> Config published to config/audit.php');
    }

    private function publishMigration(): void
    {
        $existing = glob(database_path('migrations/*_create_audit_logs_table.php'));

        if (! empty($existing)) {
            $this->line('  <fg=yellow>~</> Migration already exists — skipped.');
            return;
        }

        $stub = File::get(__DIR__ . '/../../../database/migrations/create_audit_logs_table.php.stub');
        $stub = str_replace('{{ table }}', config('audit.table', 'audit_logs'), $stub);

        $filename    = date('Y_m_d_His') . '_create_audit_logs_table.php';
        $destination = database_path("migrations/{$filename}");

        File::put($destination, $stub);
        $this->line("  <fg=green>✓</> Migration created: database/migrations/{$filename}");
    }

    // ── Stack detection ───────────────────────────────────────────────────────

    private function detectStack(): array
    {
        $stack = [];

        if (class_exists(\Livewire\Livewire::class) || class_exists(\Livewire\Facades\Livewire::class)) {
            $stack[] = 'livewire';
        }

        if (class_exists(\Inertia\Inertia::class)) {
            $stack[] = 'inertia';
        }

        if ($this->hasVueInPackageJson()) {
            $stack[] = 'vue';
        }

        return $stack;
    }

    private function hasVueInPackageJson(): bool
    {
        $path = base_path('package.json');

        if (! File::exists($path)) {
            return false;
        }

        $pkg = json_decode(File::get($path), true);

        return isset($pkg['dependencies']['vue'])
            || isset($pkg['devDependencies']['vue']);
    }

    private function reportDetection(array $stack): void
    {
        if (empty($stack)) {
            $this->line('  No frontend framework detected — Blade components are available with no extra setup.');
            return;
        }

        $labels = array_map(fn ($s) => "<fg=cyan>{$s}</>", $stack);
        $this->line('  Detected: ' . implode(', ', $labels));
    }

    // ── View / asset publishing ───────────────────────────────────────────────

    private function offerViews(array $stack): void
    {
        $hasVue = in_array('vue', $stack) || in_array('inertia', $stack);

        // Blade and Livewire views are served directly from the package — no
        // publishing needed to use them. Only Vue components must be published
        // because they need to go through the project's JS asset pipeline.

        if ($hasVue) {
            $this->offerVueComponents();
        } else {
            $this->line('  Views are served directly from the package — no publishing required.');
            $this->line('  To customise the markup later: <fg=yellow>php artisan vendor:publish --tag=audited-views</>');
        }
    }

    private function offerVueComponents(): void
    {
        $destination = resource_path('js/vendor/audited');

        if (File::isDirectory($destination)) {
            $this->line('  <fg=yellow>~</> Vue components already published — skipped.');
            return;
        }

        if (! $this->confirm('Publish Vue components to resources/js/vendor/audited/?', true)) {
            $this->line('  Skipped. Run <fg=yellow>php artisan vendor:publish --tag=audited-vue</> whenever you\'re ready.');
            return;
        }

        $this->callSilently('vendor:publish', ['--tag' => 'audited-vue']);
        $this->line('  <fg=green>✓</> Vue components published to resources/js/vendor/audited/');

        if ($this->confirm('Enable the built-in JSON API routes (AUDIT_API_ROUTES=true)?', true)) {
            $this->writeEnvValue('AUDIT_API_ROUTES', 'true');
            $this->line('  <fg=green>✓</> AUDIT_API_ROUTES=true written to .env');
        } else {
            $this->line('  You can enable them later by adding <fg=yellow>AUDIT_API_ROUTES=true</> to .env.');
        }
    }

    // ── .env helper ───────────────────────────────────────────────────────────

    private function writeEnvValue(string $key, string $value): void
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            return;
        }

        $env = File::get($envPath);

        if (preg_match("/^{$key}=/m", $env)) {
            $env = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $env);
        } else {
            $env = rtrim($env) . "\n{$key}={$value}\n";
        }

        File::put($envPath, $env);
    }

    // ── Next steps ────────────────────────────────────────────────────────────

    private function showNextSteps(array $stack): void
    {
        $hasLivewire = in_array('livewire', $stack);
        $hasVue      = in_array('vue', $stack) || in_array('inertia', $stack);
        $hasInertia  = in_array('inertia', $stack);

        $this->info('Done. Next steps:');
        $this->line('  1. Review <fg=yellow>config/audit.php</> and adjust to your project.');
        $this->line('  2. Run: <fg=yellow>php artisan migrate</>');
        $this->line('  3. Add <fg=yellow>use Auditable;</> to any model you want to track.');

        if ($hasLivewire) {
            $this->line('  4. Drop <fg=yellow><livewire:audited::log-table /></> on your admin audit page — works immediately, no publishing required.');
            $this->line('     Drop <fg=yellow><livewire:audited::timeline :subject="$model" /></> on any detail page.');
        } elseif ($hasVue && $hasInertia) {
            $this->line('  4. Use the <fg=yellow>ServesAuditLogs</> trait in your Inertia controller.');
            $this->line('     Import <fg=yellow>AuditLogTable</> and <fg=yellow>AuditTimeline</> from resources/js/vendor/audited/.');
        } elseif ($hasVue) {
            $this->line('  4. Import <fg=yellow>AuditLogTable</> and <fg=yellow>AuditTimeline</> from resources/js/vendor/audited/.');
            $this->line('     Both components are self-fetching — no controller required.');
        } else {
            $this->line('  4. Use <fg=yellow><x-audited::timeline :subject="$model" /></> on any detail page.');
        }

        $this->line('  5. Optionally log manual events: <fg=yellow>Audited::log(\'action\', \'Module\', \'Description\')</>');
    }
}
