<?php

namespace Williamug\Audited;

use Illuminate\Database\Eloquent\Model;
use Williamug\Audited\Enums\AuditAction;
use Williamug\Audited\Services\ActivityLogService;

class AuditManager
{
    public function log(
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
