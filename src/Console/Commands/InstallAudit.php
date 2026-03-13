<?php

namespace Williamug\Audited\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallAudit extends Command
{
    protected $signature = 'audit:install';

    protected $description = 'Publish the audit config and create the audit_logs migration';

    public function handle(): void
    {
        $this->info('Installing Audited...');

        $this->publishConfig();
        $this->publishMigration();

        $this->newLine();
        $this->info('Done. Next steps:');
        $this->line('  1. Review <fg=yellow>config/audit.php</> and adjust to your project.');
        $this->line('  2. Run: <fg=yellow>php artisan migrate</>');
        $this->line('  3. Add <fg=yellow>use Auditable;</> to any model you want to track.');
    }

    private function publishConfig(): void
    {
        $destination = config_path('audit.php');

        if (File::exists($destination)) {
            $this->line('  <fg=yellow>~</> Config already exists at config/audit.php — skipped.');

            return;
        }

        $this->callSilently('vendor:publish', ['--tag' => 'audit-config']);
        $this->line('  <fg=green>✓</> Config published to config/audit.php');
    }

    private function publishMigration(): void
    {
        $stub = File::get(__DIR__ . '/../../../database/migrations/create_audit_logs_table.php.stub');
        $stub = str_replace('{{ table }}', config('audit.table', 'audit_logs'), $stub);

        $filename = date('Y_m_d_His') . '_create_audit_logs_table.php';
        $destination = database_path("migrations/{$filename}");

        File::put($destination, $stub);
        $this->line("  <fg=green>✓</> Migration created: database/migrations/{$filename}");
    }
}
