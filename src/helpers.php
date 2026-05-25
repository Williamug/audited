<?php

use Illuminate\Database\Eloquent\Model;
use Williamug\Audited\Enums\AuditAction;
use Williamug\Audited\Services\ActivityLogService;

if (! function_exists('audited')) {
    function audited(
        AuditAction|string $action,
        string $module,
        string $description,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?object $causer = null,
        ?Model $subject = null,
        ?array $tags = null,
    ): void {
        ActivityLogService::log($action, $module, $description, $oldValues, $newValues, $causer, $subject, $tags);
    }
}
