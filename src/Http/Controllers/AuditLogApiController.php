<?php

namespace Williamug\Audited\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Williamug\Audited\Models\AuditLog;

class AuditLogApiController extends Controller
{
    public function index(Request $request): JsonResponse
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
        $logs->through(fn ($log) => $this->transform($log));

        return response()->json([
            'logs'       => $logs,
            'allActions' => $modelClass::distinct()->orderBy('action')->pluck('action')->filter()->values(),
            'allModules' => $modelClass::distinct()->orderBy('module')->pluck('module')->filter()->values(),
            'allLevels'  => $modelClass::distinct()->orderBy('user_level')->pluck('user_level')->filter()->values(),
        ]);
    }

    public function timeline(Request $request): JsonResponse
    {
        $modelClass = config('audit.model', AuditLog::class);

        $perPage = min((int) $request->input('perPage', 10), 100);

        $logs = $modelClass::query()
            ->where('subject_type', $request->input('subject_type'))
            ->where('subject_id', $request->input('subject_id'))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $logs->through(fn ($log) => $this->transform($log));

        return response()->json(['logs' => $logs]);
    }

    private function transform(object $log): array
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
