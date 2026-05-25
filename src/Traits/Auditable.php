<?php

namespace Williamug\Audited\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Williamug\Audited\Services\ActivityLogService;

trait Auditable
{
    // Per-class flag — each model using this trait has its own copy.
    protected static bool $auditingEnabled = true;

    // Set to true between restoring/restored events so logUpdated skips
    // the internal save() that restore() fires before the restored event.
    public bool $auditingRestore = false;

    /**
     * Boot the trait and register model event listeners.
     *
     * Laravel automatically calls boot{TraitName}() when a model boots,
     * so no manual registration in the model is needed.
     */
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            ActivityLogService::logCreated($model, $model->getAuditModule());
        });

        static::updated(function ($model) {
            ActivityLogService::logUpdated($model, $model->getAuditModule());
        });

        static::deleted(function ($model) {
            ActivityLogService::logDeleted($model, $model->getAuditModule());
        });

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class))) {
            static::restoring(function ($model) {
                $model->auditingRestore = true;
            });

            static::restored(function ($model) {
                $model->auditingRestore = false;
                ActivityLogService::logRestored($model, $model->getAuditModule());
            });

            static::forceDeleted(function ($model) {
                ActivityLogService::logForceDeleted($model, $model->getAuditModule());
            });
        }
    }

    /**
     * Run $callback without writing any audit log entries for this model class.
     * Useful for bulk imports, seeders, and test setup.
     *
     * Only suppresses logs for this specific model class. Other auditable
     * models used inside the callback will still be logged normally.
     *
     * Logging is always re-enabled after the callback, even if it throws.
     */
    public static function withoutAudit(callable $callback): void
    {
        static::$auditingEnabled = false;

        try {
            $callback();
        } finally {
            static::$auditingEnabled = true;
        }
    }

    public static function isAuditingDisabled(): bool
    {
        return ! static::$auditingEnabled;
    }

    /**
     * All audit log entries that reference this model as their subject.
     */
    public function auditLogs(): MorphMany
    {
        $modelClass = config('audit.model');

        return $this->morphMany($modelClass, 'subject');
    }

    /**
     * Resolve the module name for log entries from this model.
     *
     * Resolution priority:
     *   1. $auditModule property on the model  → protected string $auditModule = 'Staff';
     *   2. Class base name fallback             → 'Invoice', 'Product', etc.
     *
     * Override getAuditModule() on the model for fully dynamic resolution.
     */
    public function getAuditModule(): string
    {
        return property_exists($this, 'auditModule')
            ? $this->auditModule
            : class_basename($this);
    }
}
