<?php

namespace Williamug\Audited\Console\Commands;

use Illuminate\Console\Command;

class PruneAuditLogs extends Command
{
    protected $signature = 'audit:prune
                            {--months= : Number of months to retain (overrides config)}
                            {--dry-run : Preview how many logs would be deleted without deleting}';

    protected $description = 'Delete audit logs older than the configured retention period';

    public function handle(): void
    {
        $configMonths = config('audit.prune_after_months');
        $optionMonths = $this->option('months');

        if ($optionMonths === null && $configMonths === null) {
            $this->warn('Pruning is disabled (prune_after_months is null). Pass --months=N to override.');
            return;
        }

        $months = (int) ($optionMonths ?? $configMonths);
        $cutoff = now()->subMonths($months);
        $modelClass = config('audit.model');
        $query = $modelClass::query()->where('created_at', '<', $cutoff);

        if ($this->option('dry-run')) {
            $count = $query->count();
            $this->info("[dry-run] Would prune {$count} audit log(s) older than {$months} month(s) (before {$cutoff->toDateString()}).");
            return;
        }

        $deleted = $query->delete();

        $this->info("Pruned {$deleted} audit log(s) older than {$months} month(s) (before {$cutoff->toDateString()}).");
    }
}
