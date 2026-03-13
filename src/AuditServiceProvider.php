<?php

namespace Williamug\Audited;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Williamug\Audited\Console\Commands\InstallAudit;
use Williamug\Audited\Console\Commands\PruneAuditLogs;
use Williamug\Audited\Listeners\LogAuthEvents;

class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge package defaults first so any key not published by the consuming
        // app still has a sensible fallback from the package's own config file.
        $this->mergeConfigFrom(
            __DIR__ . '/../config/audit.php',
            'audit',
        );
    }

    public function boot(): void
    {
        $this->registerPublishables();
        $this->registerCommands();
        $this->registerAuthListener();
        $this->registerSchedule();
    }

    private function registerPublishables(): void
    {
        $this->publishes([
            __DIR__ . '/../config/audit.php' => config_path('audit.php'),
        ], 'audit-config');
    }

    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallAudit::class,
                PruneAuditLogs::class,
            ]);
        }
    }

    private function registerAuthListener(): void
    {
        if (! config('audit.log_auth_events', true)) {
            return;
        }

        Event::listen(Login::class, LogAuthEvents::class);
        Event::listen(Logout::class, LogAuthEvents::class);
        Event::listen(Failed::class, LogAuthEvents::class);
    }

    private function registerSchedule(): void
    {
        if (! config('audit.prune_after_months')) {
            return;
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('audit:prune')->quarterly();
        });
    }
}
