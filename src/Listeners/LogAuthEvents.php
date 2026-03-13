<?php

namespace Williamug\Audited\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Williamug\Audited\Enums\AuditAction;
use Williamug\Audited\Services\ActivityLogService;

class LogAuthEvents
{
    public function handle(object $event): void
    {
        match (true) {
            $event instanceof Login => $this->logLogin($event),
            $event instanceof Logout => $this->logLogout($event),
            $event instanceof Failed => $this->logFailed($event),
            default => null,
        };
    }

    private function logLogin(Login $event): void
    {
        $nameField = config('audit.user_name_field', 'name');
        $name = $event->user->{$nameField};

        // Pass $event->user explicitly — during the Login event the session is
        // not yet written, so auth()->user() would return null inside the service.
        ActivityLogService::log(
            AuditAction::Login,
            config('audit.auth_module', 'Authentication'),
            "User '{$name}' logged in.",
            actingUser: $event->user,
        );
    }

    private function logLogout(Logout $event): void
    {
        if (! $event->user) {
            return;
        }

        $nameField = config('audit.user_name_field', 'name');
        $name = $event->user->{$nameField};

        ActivityLogService::log(
            AuditAction::Logout,
            config('audit.auth_module', 'Authentication'),
            "User '{$name}' logged out.",
            actingUser: $event->user,
        );
    }

    private function logFailed(Failed $event): void
    {
        $credentialField = config('audit.login_credential_field', 'email');
        $identifier = $event->credentials[$credentialField] ?? 'unknown';

        ActivityLogService::log(
            AuditAction::FailedLogin,
            config('audit.auth_module', 'Authentication'),
            "Failed login attempt for '{$identifier}'.",
        );
    }
}
