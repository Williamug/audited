<?php

namespace Williamug\Audited\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Williamug\Audited\Traits\Auditable;

/**
 * A model that defines auditLabel() and $auditExclude to test those features.
 */
class TestProductWithLabel extends Model
{
    use Auditable;

    protected $table = 'products';

    protected $guarded = [];

    protected string $auditModule = 'Products';

    /** @var array<string> */
    public array $auditExclude = ['stock_count'];

    public function auditLabel(): string
    {
        return "Product: {$this->name}";
    }
}
