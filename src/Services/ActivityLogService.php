<?php

namespace Williamug\Audited\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;
use Williamug\Audited\Enums\AuditAction;

class ActivityLogService
{
    /**
     * Record an activity in the audit log.
     *
     * @param  AuditAction|string        $action      A package enum value or a plain string
     *                                                 for app-specific actions not in the enum.
     * @param  string                    $module      The module name, e.g. 'Staff', 'Billing'.
     * @param  string                    $description Human-readable summary of what happened.
     * @param  array<string,mixed>|null  $oldValues   State before the change.
     * @param  array<string,mixed>|null  $newValues   State after the change.
     */
    /**
     * @param  AuditAction|string        $action
     * @param  string                    $module
     * @param  string                    $description
     * @param  array<string,mixed>|null  $oldValues
     * @param  array<string,mixed>|null  $newValues
     * @param  object|null               $actingUser  Explicit user to record. Defaults to
     *                                                 auth()->user(). Pass this when logging
     *                                                 auth events where the session user is
     *                                                 not yet set (e.g. Login event).
     */
    public static function log(
        AuditAction|string $action,
        string $module,
        string $description,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?object $actingUser = null,
    ): void {
        $user = $actingUser ?? auth()->user();
        $modelClass = config('audit.model');

        $modelClass::create([
            'user_id'     => $user?->getKey(),
            'user_name'   => self::resolveUserName($user),
            'user_level'  => self::resolveUserLevel($user),
            'platform'    => self::detectPlatform(),
            'action'      => $action instanceof AuditAction ? $action->value : $action,
            'module'      => $module,
            'description' => $description,
            'old_values'  => $oldValues ? self::sanitize($oldValues) : null,
            'new_values'  => $newValues ? self::sanitize($newValues) : null,
            'ip_address'  => Request::ip(),
            'user_agent'  => Request::userAgent(),
            ...($modelClass::extraColumns()),
        ]);
    }

    /**
     * Log a model creation event.
     * Stores all model attributes as new_values.
     */
    public static function logCreated(Model $model, string $module): void
    {
        self::log(
            AuditAction::Create,
            $module,
            'Created ' . self::resolveLabel($model),
            null,
            self::sanitize(self::filterExcluded($model, $model->getAttributes())),
        );
    }

    /**
     * Log a model update event.
     * Stores only the changed fields to keep the log focused.
     * Silently skips if nothing actually changed.
     */
    public static function logUpdated(Model $model, string $module): void
    {
        $dirty = $model->getDirty();

        if (empty($dirty)) {
            return;
        }

        $old = array_intersect_key($model->getOriginal(), $dirty);

        self::log(
            AuditAction::Update,
            $module,
            'Updated ' . self::resolveLabel($model),
            self::sanitize(self::filterExcluded($model, $old)),
            self::sanitize(self::filterExcluded($model, $dirty)),
        );
    }

    /**
     * Log a model deletion event.
     * Stores the full model snapshot as old_values so the record
     * can be reconstructed from the audit log if needed.
     */
    public static function logDeleted(Model $model, string $module): void
    {
        self::log(
            AuditAction::Delete,
            $module,
            'Deleted ' . self::resolveLabel($model),
            self::sanitize(self::filterExcluded($model, $model->getAttributes())),
            null,
        );
    }

    /**
     * Resolve a human-readable label for the model.
     *
     * If the model defines auditLabel(), that is used.
     * Otherwise falls back to "ClassName #primaryKey".
     */
    private static function resolveLabel(Model $model): string
    {
        if (method_exists($model, 'auditLabel')) {
            return $model->auditLabel();
        }

        return class_basename($model) . ' #' . $model->getKey();
    }

    /**
     * Strip global sensitive fields (from config) before writing to the log.
     *
     * @param  array<string,mixed> $values
     * @return array<string,mixed>
     */
    private static function sanitize(array $values): array
    {
        return array_diff_key($values, array_flip(config('audit.sensitive_fields', [])));
    }

    /**
     * Strip any model-level excluded fields (from $auditExclude property).
     *
     * @param  array<string,mixed> $values
     * @return array<string,mixed>
     */
    private static function filterExcluded(Model $model, array $values): array
    {
        if (! property_exists($model, 'auditExclude')) {
            return $values;
        }

        return array_diff_key($values, array_flip($model->auditExclude));
    }

    /**
     * Detect whether the request came from a web browser or a mobile/API client.
     */
    private static function detectPlatform(): string
    {
        return Request::expectsJson() ? 'mobile' : 'web';
    }

    /**
     * Read the user's display name from the configured field.
     */
    private static function resolveUserName(?object $user): ?string
    {
        if (! $user) {
            return null;
        }

        $field = config('audit.user_name_field', 'name');

        return $user->{$field} ?? null;
    }

    /**
     * Read the optional user level/role field from the authenticated user.
     * Returns null when the field is not configured or the user is a guest.
     */
    private static function resolveUserLevel(?object $user): ?string
    {
        $field = config('audit.user_level_field');

        if (! $field || ! $user) {
            return null;
        }

        return $user->{$field} ?? null;
    }
}
