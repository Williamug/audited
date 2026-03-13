<?php

namespace Williamug\Audited\Traits;

use Williamug\Audited\Services\ActivityLogService;

trait Auditable
{
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
