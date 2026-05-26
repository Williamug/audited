<?php

namespace Williamug\Audited\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Williamug\Audited\Traits\Auditable;

class TestRole extends Model
{
    use Auditable;

    protected $table = 'roles';

    protected $guarded = [];

    protected string $auditModule = 'Roles';

    /** @var array<string> */
    public array $auditRelationships = ['permissions'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(TestPermission::class, 'role_permission', 'role_id', 'permission_id');
    }
}
