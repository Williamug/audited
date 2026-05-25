<?php

namespace Williamug\Audited\Livewire;

use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Williamug\Audited\Models\AuditLog;

class AuditLogTable extends Component
{
    use WithPagination;

    public string $search = '';
    public string $action = '';
    public string $module = '';
    public string $level = '';
    public string $platform = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public int $perPage = 15;
    public mixed $expandedId = null;

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'action', 'module', 'level', 'platform', 'dateFrom', 'dateTo', 'perPage'])) {
            $this->resetPage();
        }
    }

    public function toggleExpand(mixed $id): void
    {
        $this->expandedId = $this->expandedId == $id ? null : $id;
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'action', 'module', 'level', 'platform', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    public function hasActiveFilters(): bool
    {
        return (bool) ($this->search || $this->action || $this->module
            || $this->level || $this->platform || $this->dateFrom || $this->dateTo);
    }

    public function render(): View
    {
        /** @var class-string<AuditLog> $modelClass */
        $modelClass = config('audit.model', AuditLog::class);

        $query = $modelClass::query()->latest();

        if ($this->search) {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('user_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        if ($this->action)   $query->withAction($this->action);
        if ($this->module)   $query->forModule($this->module);
        if ($this->level)    $query->where('user_level', $this->level);
        if ($this->platform) $query->where('platform', $this->platform);
        if ($this->dateFrom) $query->whereDate('created_at', '>=', $this->dateFrom);
        if ($this->dateTo)   $query->whereDate('created_at', '<=', $this->dateTo);

        return view('audited::livewire.audit-log-table', [
            'logs'             => $query->paginate($this->perPage),
            'allActions'       => $modelClass::distinct()->orderBy('action')->pluck('action')->filter()->values(),
            'allModules'       => $modelClass::distinct()->orderBy('module')->pluck('module')->filter()->values(),
            'allLevels'        => $modelClass::distinct()->orderBy('user_level')->pluck('user_level')->filter()->values(),
            'hasActiveFilters' => $this->hasActiveFilters(),
        ]);
    }
}
