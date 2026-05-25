<?php

namespace Williamug\Audited\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use Williamug\Audited\Enums\AuditAction;

/**
 * @method static void log(AuditAction|string $action, string $module, string $description, ?array $oldValues = null, ?array $newValues = null, ?object $causer = null, ?Model $subject = null, ?array $tags = null)
 *
 * @see \Williamug\Audited\AuditManager
 */
class Audited extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'audited';
    }
}
