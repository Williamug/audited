<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Audit Log Model
    |--------------------------------------------------------------------------
    | The Eloquent model used to store audit log entries. Swap this for your
    | own model that extends Williamug\Audited\Models\AuditLog to add extra
    | relationships (e.g. a tenant or organisation relationship).
    */
    'model' => \Williamug\Audited\Models\AuditLog::class,

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    | The Eloquent model that represents your application's users.
    */
    'user_model' => \App\Models\User::class,

    /*
    |--------------------------------------------------------------------------
    | User Field Snapshot
    |--------------------------------------------------------------------------
    | These fields are read from the authenticated user at the time of logging
    | and stored as a snapshot on the log entry. This keeps the log readable
    | even if the user record is later modified or deleted.
    |
    | 'user_name_field'  — field used as the human-readable display name.
    | 'user_level_field' — optional role/level label (e.g. "Admin", "Manager").
    |                      Set to null if your app has no such concept.
    */
    'user_name_field' => 'name',
    'user_level_field' => null,

    /*
    |--------------------------------------------------------------------------
    | Authentication Event Logging
    |--------------------------------------------------------------------------
    | When true, the package automatically listens for Laravel's Login,
    | Logout, and Failed auth events and writes a log entry for each.
    | Set to false to handle auth logging yourself.
    */
    'log_auth_events' => true,

    /*
    |--------------------------------------------------------------------------
    | Authentication Module Label
    |--------------------------------------------------------------------------
    | The module string written to the log for auth events. Change this to
    | match whatever module naming convention your application uses.
    */
    'auth_module' => 'Authentication',

    /*
    |--------------------------------------------------------------------------
    | Sensitive Fields
    |--------------------------------------------------------------------------
    | These fields are stripped from old_values and new_values before the log
    | entry is saved. Add any field that must never be stored in the log.
    */
    'sensitive_fields' => [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ],

    /*
    |--------------------------------------------------------------------------
    | Login Credential Field
    |--------------------------------------------------------------------------
    | The credential field used as the identifier in your login form.
    | Used to identify the subject in failed login log entries.
    | Common values: 'email', 'username', 'phone_number'.
    */
    'login_credential_field' => 'email',

    /*
    |--------------------------------------------------------------------------
    | Pruning
    |--------------------------------------------------------------------------
    | Audit logs older than this many months are deleted when audit:prune runs.
    | The command is scheduled quarterly by the service provider automatically.
    | Set to null to disable automatic pruning entirely.
    */
    'prune_after_months' => 3,

    /*
    |--------------------------------------------------------------------------
    | Table Name
    |--------------------------------------------------------------------------
    | The database table used to store audit log entries. Change this only if
    | 'audit_logs' conflicts with an existing table in your application.
    */
    'table' => 'audit_logs',

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    | Set to false (default) to write audit logs synchronously.
    | Set to true to dispatch on the default queue.
    | Set to a queue name string (e.g. 'audit') to use a specific queue.
    |
    | Async logging avoids adding latency to requests in high-traffic apps.
    */
    'queue' => env('AUDIT_QUEUE', false),

    /*
    |--------------------------------------------------------------------------
    | Silent Failures
    |--------------------------------------------------------------------------
    | When true, exceptions thrown during a log write are swallowed and sent
    | to Laravel's logger instead of bubbling up to the caller. Useful in
    | production to ensure audit logging never breaks application features.
    */
    'silent_failures' => env('AUDIT_SILENT_FAILURES', false),

    /*
    |--------------------------------------------------------------------------
    | Vue / Inertia API Routes
    |--------------------------------------------------------------------------
    | Set to true (or set AUDIT_API_ROUTES=true in .env) to register the
    | built-in JSON endpoints used by the Vue components in self-fetch mode.
    |
    | Endpoints (relative to api_prefix):
    |   GET /audited/api/logs      — paginated log table data
    |   GET /audited/api/timeline  — subject-scoped timeline data
    |
    | 'api_prefix'     — URL prefix for both endpoints.
    | 'api_middleware' — middleware stack applied to both endpoints.
    |                    Include your auth middleware here.
    */
    'api_routes'     => env('AUDIT_API_ROUTES', false),
    'api_prefix'     => 'audited/api',
    'api_middleware' => ['web', 'auth'],

];
