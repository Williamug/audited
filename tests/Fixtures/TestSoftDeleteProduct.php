<?php

namespace Williamug\Audited\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Williamug\Audited\Traits\Auditable;

class TestSoftDeleteProduct extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'products';

    protected $guarded = [];

    protected string $auditModule = 'Products';
}
