<?php

namespace Williamug\Audited\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Williamug\Audited\Enums\AuditAction;

class AuditLog extends Model
{
  protected $guarded = [];

  public function getTable(): string
  {
    return config('audit.table', 'audit_logs');
  }

  protected function casts(): array
  {
    return [
      'tags'       => 'array',
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

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

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

  /**
   * The Eloquent model that was acted on, if the log was written via the
   * Auditable trait or by passing a $subject to ActivityLogService::log().
   */
  public function subject(): MorphTo
  {
    return $this->morphTo();
  }

    // -------------------------------------------------------------------------
    // Query scopes
    // -------------------------------------------------------------------------

  /** @param  int|Model  $user */
  public function scopeForUser(Builder $query, mixed $user): Builder
  {
    $id = $user instanceof Model ? $user->getKey() : $user;

    return $query->where('user_id', $id);
  }

  public function scopeForModule(Builder $query, string $module): Builder
  {
    return $query->where('module', $module);
  }

  public function scopeWithAction(Builder $query, AuditAction|string $action): Builder
  {
    $value = $action instanceof AuditAction ? $action->value : $action;

    return $query->where('action', $value);
  }

  public function scopeBetween(Builder $query, mixed $from, mixed $to): Builder
  {
    return $query->whereBetween('created_at', [$from, $to]);
  }

  public function scopeForSubject(Builder $query, Model $subject): Builder
  {
    return $query->where('subject_type', get_class($subject))
      ->where('subject_id', $subject->getKey());
  }
}
