<?php

namespace Williamug\Audited\Contracts;

interface Causer
{
    public function getCauserName(): string;

    public function getCauserType(): string;
}
