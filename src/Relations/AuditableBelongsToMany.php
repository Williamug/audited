<?php

namespace Williamug\Audited\Relations;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Williamug\Audited\Services\ActivityLogService;

class AuditableBelongsToMany extends BelongsToMany
{
    /**
     * Attach one or more related models and write an audit log entry.
     */
    public function attach($id, array $attributes = [], $touch = true): void
    {
        $ids = $this->parseIds($id);

        parent::attach($id, $attributes, $touch);

        ActivityLogService::logPivotChanged(
            $this->parent,
            $this->relationName,
            'attached',
            $ids,
            $this->parent->getAuditModule(),
        );
    }

    /**
     * Detach one or more (or all) related models and write an audit log entry.
     *
     * When $ids is null (detach all), the currently attached IDs are resolved
     * before the delete so the log captures exactly what was removed.
     */
    public function detach($ids = null, $touch = true): int
    {
        $resolvedIds = is_null($ids)
            ? $this->getCurrentlyAttachedPivots()->pluck($this->relatedPivotKey)->all()
            : $this->parseIds($ids);

        $result = parent::detach($ids, $touch);

        ActivityLogService::logPivotChanged(
            $this->parent,
            $this->relationName,
            'detached',
            $resolvedIds,
            $this->parent->getAuditModule(),
        );

        return $result;
    }

    /**
     * Update an existing pivot record's extra attributes and write an audit log entry.
     */
    public function updateExistingPivot($id, array $attributes, $touch = true): int
    {
        $result = parent::updateExistingPivot($id, $attributes, $touch);

        ActivityLogService::logPivotChanged(
            $this->parent,
            $this->relationName,
            'updated',
            (array) $this->parseId($id),
            $this->parent->getAuditModule(),
        );

        return $result;
    }
}
