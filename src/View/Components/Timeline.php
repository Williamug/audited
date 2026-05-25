<?php

namespace Williamug\Audited\View\Components;

use Illuminate\Database\Eloquent\Model;
use Illuminate\View\Component;
use Illuminate\View\View;
use Williamug\Audited\Models\AuditLog;

class Timeline extends Component
{
    public function __construct(
        public readonly Model $subject,
        public readonly int $limit = 25,
        public readonly bool $showValues = false,
    ) {}

    public function render(): View
    {
        /** @var class-string<AuditLog> $modelClass */
        $modelClass = config('audit.model', AuditLog::class);

        return view('audited::components.timeline', [
            'logs'       => $modelClass::forSubject($this->subject)->latest()->limit($this->limit)->get(),
            'showValues' => $this->showValues,
        ]);
    }
}
