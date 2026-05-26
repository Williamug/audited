<?php

namespace Williamug\Audited\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Williamug\Audited\Traits\Auditable;

/**
 * A role model without $auditRelationships, used to verify pivot changes are not logged
 * when the model hasn't opted in to relationship auditing.
 */
class TestRoleNoRelAudit extends Model
{
    use Auditable;

    protected $table = 'roles';

    protected $guarded = [];

    protected string $auditModule = 'Roles';

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(TestPermission::class, 'role_permission', 'role_id', 'permission_id');
    }
}
