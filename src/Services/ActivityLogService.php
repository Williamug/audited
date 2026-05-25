<?php

namespace Williamug\Audited\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;
use Williamug\Audited\Enums\AuditAction;
use Williamug\Audited\Jobs\WriteAuditLog;

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
     * @param  object|null               $actingUser  Explicit user to record. Defaults to
     *                                                auth()->user(). Pass this when logging
     *                                                auth events where the session user is
     *                                                not yet set (e.g. Login event).
     * @param  Model|null                $subject     The Eloquent model being acted on.
     *                                                Populated automatically by the Auditable
     *                                                trait; pass explicitly for manual logs
     *                                                tied to a specific record.
     * @param  array<string,mixed>|null  $tags        Arbitrary key-value metadata to attach
     *                                                to the log entry (e.g. batch IDs, import
     *                                                sources, workflow step names).
     */
    public static function log(
        AuditAction|string $action,
        string $module,
        string $description,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?object $actingUser = null,
        ?Model $subject = null,
        ?array $tags = null,
    ): void {
        $user = $actingUser ?? auth()->user();
        $modelClass = config('audit.model');

        $data = [
            'user_id'      => $user?->getKey(),
            'user_name'    => self::resolveUserName($user),
            'user_level'   => self::resolveUserLevel($user),
            'platform'     => self::detectPlatform(),
            'action'       => $action instanceof AuditAction ? $action->value : $action,
            'module'       => $module,
            'description'  => $description,
            'tags'         => $tags,
            'old_values'   => $oldValues ? self::sanitize($oldValues) : null,
            'new_values'   => $newValues ? self::sanitize($newValues) : null,
            'ip_address'   => Request::ip(),
            'user_agent'   => Request::userAgent(),
            'url'          => self::resolveUrl(),
            'http_method'  => self::resolveHttpMethod(),
            'route_name'   => self::resolveRouteName(),
            'auth_guard'   => self::resolveAuthGuard($user),
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id'   => $subject?->getKey(),
            'request_id'   => self::resolveRequestId(),
            ...($modelClass::extraColumns()),
        ];

        self::write($modelClass, $data);
    }

    /**
     * Log a model creation event.
     * Stores all model attributes as new_values.
     */
    public static function logCreated(Model $model, string $module): void
    {
        if (self::isModelAuditingDisabled($model)) {
            return;
        }

        self::log(
            AuditAction::Create,
            $module,
            'Created ' . self::resolveLabel($model),
            null,
            self::sanitize(self::filterExcluded($model, $model->getAttributes())),
            subject: $model,
        );
    }

    /**
     * Log a model update event.
     * Stores only the changed fields to keep the log focused.
     * Silently skips if nothing actually changed or if a restore is in progress.
     */
    public static function logUpdated(Model $model, string $module): void
    {
        if (self::isModelAuditingDisabled($model)) {
            return;
        }

        if (! empty($model->auditingRestore)) {
            return;
        }

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
            subject: $model,
        );
    }

    /**
     * Log a model deletion event.
     * Stores the full model snapshot as old_values so the record
     * can be reconstructed from the audit log if needed.
     */
    public static function logDeleted(Model $model, string $module): void
    {
        if (self::isModelAuditingDisabled($model)) {
            return;
        }

        self::log(
            AuditAction::Delete,
            $module,
            'Deleted ' . self::resolveLabel($model),
            self::sanitize(self::filterExcluded($model, $model->getAttributes())),
            null,
            subject: $model,
        );
    }

    /**
     * Log a soft-deleted model being restored.
     */
    public static function logRestored(Model $model, string $module): void
    {
        if (self::isModelAuditingDisabled($model)) {
            return;
        }

        self::log(
            AuditAction::Restore,
            $module,
            'Restored ' . self::resolveLabel($model),
            null,
            self::sanitize(self::filterExcluded($model, $model->getAttributes())),
            subject: $model,
        );
    }

    /**
     * Log a model being permanently deleted (force delete).
     */
    public static function logForceDeleted(Model $model, string $module): void
    {
        if (self::isModelAuditingDisabled($model)) {
            return;
        }

        self::log(
            AuditAction::ForceDelete,
            $module,
            'Permanently deleted ' . self::resolveLabel($model),
            self::sanitize(self::filterExcluded($model, $model->getAttributes())),
            null,
            subject: $model,
        );
    }

    /**
     * Dispatch a queued job or write directly, wrapped in the silent-failure guard.
     */
    private static function write(string $modelClass, array $data): void
    {
        $queue = config('audit.queue', false);

        try {
            if ($queue !== false) {
                $job = new WriteAuditLog($modelClass, $data);
                dispatch(is_string($queue) ? $job->onQueue($queue) : $job);
                return;
            }

            $modelClass::create($data);
        } catch (\Throwable $e) {
            if (! config('audit.silent_failures', false)) {
                throw $e;
            }
            logger()->error('Audit log write failed: ' . $e->getMessage(), ['exception' => $e]);
        }
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
     * Detect the platform context: 'cli', 'mobile' (JSON/API), or 'web'.
     */
    private static function detectPlatform(): string
    {
        if (app()->runningInConsole()) {
            return 'cli';
        }

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

    /**
     * Return the UUID that identifies the current request or console invocation.
     * All log entries written during the same invocation share this ID.
     */
    private static function resolveRequestId(): ?string
    {
        try {
            return app('audit.request_id');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Full URL of the current request, null for CLI invocations.
     */
    private static function resolveUrl(): ?string
    {
        if (app()->runningInConsole()) {
            return null;
        }

        return Request::fullUrl();
    }

    /**
     * HTTP verb of the current request (GET, POST, …), null for CLI invocations.
     */
    private static function resolveHttpMethod(): ?string
    {
        if (app()->runningInConsole()) {
            return null;
        }

        return Request::method();
    }

    /**
     * Named route of the current request, null for CLI invocations or unnamed routes.
     */
    private static function resolveRouteName(): ?string
    {
        if (app()->runningInConsole()) {
            return null;
        }

        try {
            return Request::route()?->getName();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The name of the auth guard that authenticated the current user.
     * Iterates configured guards and returns the first one that reports a
     * logged-in user. Returns null when no user is authenticated.
     */
    private static function resolveAuthGuard(?object $user): ?string
    {
        if (! $user) {
            return null;
        }

        foreach (array_keys(config('auth.guards', [])) as $guard) {
            try {
                if (auth()->guard($guard)->check()) {
                    return $guard;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * Check whether the Auditable trait has suppressed logging for this model class.
     */
    private static function isModelAuditingDisabled(Model $model): bool
    {
        return method_exists($model, 'isAuditingDisabled') && $model::isAuditingDisabled();
    }
}
