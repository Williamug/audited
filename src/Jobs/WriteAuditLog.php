<?php

namespace Williamug\Audited\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class WriteAuditLog implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public function __construct(
        private readonly string $modelClass,
        private readonly array $data,
    ) {}

    public function handle(): void
    {
        $this->modelClass::create($this->data);
    }
}
