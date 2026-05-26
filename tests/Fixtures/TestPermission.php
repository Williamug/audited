<?php

namespace Williamug\Audited\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class TestPermission extends Model
{
    protected $table = 'permissions';

    protected $guarded = [];
}
