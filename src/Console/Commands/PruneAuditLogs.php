<?php

namespace Williamug\Audited\Console\Commands;

use Illuminate\Console\Command;

class PruneAuditLogs extends Command
{
    protected $signature = 'audit:prune {--months= : Number of months to retain (overrides config)}';

    protected $description = 'Delete audit logs older than the configured retention period';

    public function handle(): void
    {
        $months = (int) ($this->option('months') ?? config('audit.prune_after_months', 3));

        $cutoff = now()->subMonths($months);

        $modelClass = config('audit.model');

        $deleted = $modelClass::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Pruned {$deleted} audit log(s) older than {$months} month(s) (before {$cutoff->toDateString()}).");
    }
}
