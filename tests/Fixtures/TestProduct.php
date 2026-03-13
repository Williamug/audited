<?php

namespace Williamug\Audited\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Williamug\Audited\Traits\Auditable;

class TestProduct extends Model
{
    use Auditable;

    protected $table = 'products';

    protected $guarded = [];

    protected string $auditModule = 'Products';
}
