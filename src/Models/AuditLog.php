<?php

namespace Williamug\Audited\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $guarded = [];

    /**
     * Resolve the table name from config so consuming apps can rename it.
     */
    public function getTable(): string
    {
        return config('audit.table', 'audit_logs');
    }

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Extra columns to merge into every log entry written by ActivityLogService.
     *
     * Override this in your custom model to stamp tenant, branch, or any other
     * application-specific columns automatically — without changing call sites.
     * The column names are entirely up to the consuming application.
     *
     * Example:
     *   protected static function extraColumns(): array
     *   {
     *       return [
     *           'company_id' => auth()->user()?->company_id,
     *           'branch_id'  => auth()->user()?->branch_id,
     *       ];
     *   }
     *
     * @return array<string, mixed>
     */
    protected static function extraColumns(): array
    {
        return [];
    }

    /**
     * The user who performed the action.
     *
     * May return null if the user has since been deleted, which is why
     * user_name is also stored as a plain-text snapshot on every entry.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('audit.user_model'));
    }
}
