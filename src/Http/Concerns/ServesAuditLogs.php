<?php

namespace Williamug\Audited\Http\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Williamug\Audited\Models\AuditLog;

/**
 * Add to an Inertia controller to get pre-filtered, pre-transformed audit data
 * ready to pass to the Vue AuditLogTable or AuditTimeline components.
 *
 * Usage:
 *
 *   use Williamug\Audited\Http\Concerns\ServesAuditLogs;
 *
 *   class AuditController extends Controller
 *   {
 *       use ServesAuditLogs;
 *
 *       public function index(Request $request)
 *       {
 *           return Inertia::render('Admin/AuditLog', $this->auditLogProps($request));
 *       }
 *   }
 */
trait ServesAuditLogs
{
    protected function auditLogProps(Request $request): array
    {
        $modelClass = config('audit.model', AuditLog::class);
        $query = $modelClass::query()->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('user_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        if ($action   = $request->input('action'))   $query->withAction($action);
        if ($module   = $request->input('module'))   $query->forModule($module);
        if ($level    = $request->input('level'))    $query->where('user_level', $level);
        if ($platform = $request->input('platform')) $query->where('platform', $platform);
        if ($dateFrom = $request->input('dateFrom')) $query->whereDate('created_at', '>=', $dateFrom);
        if ($dateTo   = $request->input('dateTo'))   $query->whereDate('created_at', '<=', $dateTo);

        $perPage = min((int) $request->input('perPage', 15), 100);
        $logs = $query->paginate($perPage)->withQueryString();
        $logs->through(fn ($log) => $this->transformAuditLog($log));

        return [
            'logs'       => $logs,
            'allActions' => $modelClass::distinct()->orderBy('action')->pluck('action')->filter()->values(),
            'allModules' => $modelClass::distinct()->orderBy('module')->pluck('module')->filter()->values(),
            'allLevels'  => $modelClass::distinct()->orderBy('user_level')->pluck('user_level')->filter()->values(),
            'filters'    => $request->only(['search', 'action', 'module', 'level', 'platform', 'dateFrom', 'dateTo']),
        ];
    }

    protected function auditTimelineProps(Request $request, Model $subject, int $perPage = 10): array
    {
        $modelClass = config('audit.model', AuditLog::class);

        $logs = $modelClass::forSubject($subject)
            ->latest()
            ->paginate(min($perPage, 100))
            ->withQueryString();

        $logs->through(fn ($log) => $this->transformAuditLog($log));

        return ['logs' => $logs];
    }

    private function transformAuditLog(object $log): array
    {
        return [
            'id'               => $log->id,
            'action'           => $log->action,
            'action_label'     => $log->action_label,
            'action_badge_color' => $log->action_badge_color,
            'module'           => $log->module,
            'description'      => $log->description,
            'user_name'        => $log->user_name,
            'user_level'       => $log->user_level,
            'causer_type'      => $log->causer_type,
            'platform'         => $log->platform,
            'ip_address'       => $log->ip_address,
            'user_agent'       => $log->user_agent,
            'url'              => $log->url,
            'http_method'      => $log->http_method,
            'route_name'       => $log->route_name,
            'auth_guard'       => $log->auth_guard,
            'request_id'       => $log->request_id,
            'old_values'       => $log->old_values,
            'new_values'       => $log->new_values,
            'tags'             => $log->tags,
            'created_at'       => $log->created_at,
        ];
    }
}
