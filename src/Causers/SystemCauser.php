<?php

namespace Williamug\Audited\Causers;

use Williamug\Audited\Contracts\Causer;

final class SystemCauser implements Causer
{
    public function __construct(
        private readonly string $name,
        private readonly string $type = 'system',
    ) {}

    public function getCauserName(): string
    {
        return $this->name;
    }

    public function getCauserType(): string
    {
        return $this->type;
    }
}
