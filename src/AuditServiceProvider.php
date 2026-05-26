<?php

namespace Williamug\Audited;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Williamug\Audited\AuditManager;
use Williamug\Audited\Console\Commands\InstallAudit;
use Williamug\Audited\Console\Commands\PruneAuditLogs;
use Williamug\Audited\Listeners\LogAuthEvents;
use Williamug\Audited\Livewire\AuditLogTable;
use Williamug\Audited\Livewire\AuditTimeline;
use Williamug\Audited\View\Components\Timeline;

class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/audit.php',
            'audit',
        );

        $this->app->singleton('audited', fn () => new AuditManager());
    }

    public function boot(): void
    {
        $this->registerPublishables();
        $this->registerCommands();
        $this->registerAuthListener();
        $this->registerSchedule();
        $this->registerRequestId();
        $this->registerViews();
        $this->registerLivewireComponents();
        $this->registerApiRoutes();
        $this->registerVueAssets();
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
        if (config('audit.prune_after_months') === null) {
            return;
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('audit:prune')->quarterly();
        });
    }

    private function registerRequestId(): void
    {
        // Resolved once per request (or once per console command). Every log
        // entry written during the same invocation shares this UUID so that
        // request-level tracing is possible in the audit log.
        $this->app->scoped('audit.request_id', fn () => (string) Str::uuid());
    }

    private function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'audited');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/audited'),
        ], 'audited-views');

        Blade::componentNamespace('Williamug\\Audited\\View\\Components', 'audited');
    }

    private function registerLivewireComponents(): void
    {
        if (! class_exists(\Livewire\Livewire::class)) {
            return;
        }

        \Livewire\Livewire::component('audited::timeline', AuditTimeline::class);
        \Livewire\Livewire::component('audited::log-table', AuditLogTable::class);
    }

    private function registerApiRoutes(): void
    {
        if (! config('audit.api_routes', false)) {
            return;
        }

        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
    }

    private function registerVueAssets(): void
    {
        $this->publishes([
            __DIR__ . '/../resources/js/components' => resource_path('js/vendor/audited'),
        ], 'audited-vue');
    }
}
