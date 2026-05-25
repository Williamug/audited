<?php

namespace Williamug\Audited\Livewire;

use Illuminate\Database\Eloquent\Model;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Williamug\Audited\Models\AuditLog;

class AuditTimeline extends Component
{
    use WithPagination;

    public string $subjectType;
    public int|string $subjectId;
    public int $perPage = 10;
    public bool $showValues = false;

    public function mount(Model $subject, int $perPage = 10, bool $showValues = false): void
    {
        $this->subjectType  = get_class($subject);
        $this->subjectId    = $subject->getKey();
        $this->perPage      = $perPage;
        $this->showValues   = $showValues;
    }

    public function render(): View
    {
        /** @var class-string<AuditLog> $modelClass */
        $modelClass = config('audit.model', AuditLog::class);

        $subject = (new $this->subjectType)->find($this->subjectId);

        return view('audited::livewire.audit-timeline', [
            'logs'       => $modelClass::forSubject($subject)->latest()->paginate($this->perPage),
            'showValues' => $this->showValues,
        ]);
    }
}
