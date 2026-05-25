# Audited

[![Latest Version on Packagist](https://img.shields.io/packagist/v/williamug/audited.svg?style=flat-square)](https://packagist.org/packages/williamug/audited)
[![tests](https://github.com/Williamug/audited/actions/workflows/tests.yml/badge.svg)](https://github.com/Williamug/audited/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/williamug/audited.svg?style=flat-square)](https://packagist.org/packages/williamug/audited)


A simple, robust audit logging package for Laravel applications. Drop one trait onto a model and every create, update, and delete is automatically recorded. Authentication events, manual logging, per-model subject relationships, request-level tracing, async queue support, scheduled pruning, and a configurable schema are included out of the box.

---

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Basic Usage](#basic-usage)
  - [Automatic Model Logging with the Auditable Trait](#automatic-model-logging-with-the-auditable-trait)
  - [Customising the Log Description](#customising-the-log-description)
  - [Excluding Fields from Logs](#excluding-fields-from-logs)
  - [Manual Logging](#manual-logging)
  - [Suppressing Audit Logging](#suppressing-audit-logging)
- [Authentication Event Logging](#authentication-event-logging)
- [Soft Deletes](#soft-deletes)
- [The AuditAction Enum](#the-auditaction-enum)
- [Querying Audit Logs](#querying-audit-logs)
  - [Query Scopes](#query-scopes)
  - [Subject Relationship](#subject-relationship)
- [Extending the AuditLog Model](#extending-the-auditlog-model)
- [Multitenancy](#multitenancy)
  - [Stamping Tenant Context on Every Log Entry](#stamping-tenant-context-on-every-log-entry)
  - [Scoping Queries per Tenant](#scoping-queries-per-tenant)
  - [Branch-level Isolation](#branch-level-isolation)
  - [Full Example](#full-example)
- [Queue / Async Logging](#queue--async-logging)
- [Silent Failures](#silent-failures)
- [Request Context](#request-context)
  - [Request ID](#request-id)
  - [URL and HTTP method](#url-and-http-method)
  - [Route name](#route-name)
  - [Auth guard](#auth-guard)
  - [Upgrading existing installs](#upgrading-existing-installs)
- [Pruning Old Logs](#pruning-old-logs)
- [Advanced Configuration](#advanced-configuration)
  - [Custom User Fields](#custom-user-fields)
  - [Custom Login Credential Field](#custom-login-credential-field)
  - [Sensitive Fields](#sensitive-fields)
  - [Custom Table Name](#custom-table-name)
- [Testing](#testing)
- [Changelog](#changelog)
- [License](#license)

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | `^8.2` |
| Laravel | `^10.0`, `^11.0`, `^12.0`, `^13.0` |

---

## Installation

### 1. Require the package

```bash
composer require williamug/audited
```

### 2. Run the install command

```bash
php artisan audit:install
```

This publishes `config/audit.php` and copies a timestamped migration into `database/migrations/`. Running the command a second time is safe — it skips files that already exist.

### 3. Run the migration

```bash
php artisan migrate
```

That's it. The package is now active. Authentication events are logged automatically and the `audit:prune` command is scheduled quarterly without any further setup.

---

## Configuration

After installation, `config/audit.php` is in your application. Every option has a sensible default — you only need to change the values that differ from those defaults.

```php
// config/audit.php

return [

    // The Eloquent model used to store log entries.
    // Swap this for your own model to add extra relationships or columns.
    'model' => \Williamug\Audited\Models\AuditLog::class,

    // Your application's User model.
    'user_model' => \App\Models\User::class,

    // The field on your User model used as the display name in log entries.
    'user_name_field' => 'name',

    // Optional: a role or level field on your User model (e.g. 'role', 'level').
    // Set to null if your app does not have this concept.
    'user_level_field' => null,

    // Automatically log Login, Logout, and Failed auth events.
    'log_auth_events' => true,

    // The module label written to auth event log entries.
    'auth_module' => 'Authentication',

    // Fields stripped from old_values / new_values before saving.
    'sensitive_fields' => [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ],

    // The credential field used to identify the subject in failed login entries.
    // Common values: 'email', 'username', 'phone_number'.
    'login_credential_field' => 'email',

    // Logs older than this many months are removed by audit:prune.
    // Set to null to disable automatic pruning.
    'prune_after_months' => 3,

    // The database table name.
    'table' => 'audit_logs',

    // Set to false (default) to write audit logs synchronously.
    // Set to true to dispatch on the default queue.
    // Set to a queue name string (e.g. 'audit') to use a specific queue.
    'queue' => env('AUDIT_QUEUE', false),

    // When true, exceptions thrown during a log write are swallowed and sent
    // to Laravel's logger instead of bubbling up to the caller.
    'silent_failures' => env('AUDIT_SILENT_FAILURES', false),

];
```

---

## Basic Usage

### Automatic Model Logging with the Auditable Trait

Add `use Auditable;` to any Eloquent model. Every `created`, `updated`, and `deleted` event on that model will be recorded automatically — no observers or extra setup required.

```php
use Williamug\Audited\Traits\Auditable;

class Invoice extends Model
{
    use Auditable;

    // The module label written to log entries for this model.
    // Defaults to the class base name ('Invoice') if omitted.
    protected string $auditModule = 'Billing';
}
```

That single trait registration produces entries like:

| action | module | description |
|---|---|---|
| `create` | `Billing` | Created Invoice #42 |
| `update` | `Billing` | Updated Invoice #42 |
| `delete` | `Billing` | Deleted Invoice #42 |

**Update entries only record what changed.** If five fields exist on the model but only one was modified, `old_values` and `new_values` will each contain that one field — not the full row.

**Saving without changes produces no log entry.** If `save()` is called with no dirty fields, nothing is written.

---

### Customising the Log Description

Define `auditLabel()` on your model to replace the default `ClassName #id` label in log descriptions.

```php
class Invoice extends Model
{
    use Auditable;

    protected string $auditModule = 'Billing';

    public function auditLabel(): string
    {
        return "Invoice #{$this->invoice_number} ({$this->client_name})";
    }
}
```

Log descriptions will now read:

```
Created Invoice #INV-2024-001 (Acme Corp)
Updated Invoice #INV-2024-001 (Acme Corp)
```

---

### Excluding Fields from Logs

To exclude specific fields from being recorded for a particular model, define the `$auditExclude` property. This is applied on top of the global `sensitive_fields` in the config.

```php
class User extends Model
{
    use Auditable;

    protected string $auditModule = 'Users';

    // These fields will never appear in old_values or new_values for this model.
    public array $auditExclude = ['last_seen_at', 'login_count', 'api_token'];
}
```

---

### Manual Logging

For events that are not tied to a model lifecycle — such as approving a report, exporting data, or a custom business action — use `ActivityLogService::log()` directly.

```php
use Williamug\Audited\Enums\AuditAction;
use Williamug\Audited\Services\ActivityLogService;

// Using a built-in action from the AuditAction enum
ActivityLogService::log(
    AuditAction::Approve,
    'Collections',
    'Approved Sunday collection for St. Peter\'s Church.',
);

// Recording old and new values alongside the log entry
ActivityLogService::log(
    AuditAction::Update,
    'Settings',
    'Updated application settings.',
    ['maintenance_mode' => false],   // old values
    ['maintenance_mode' => true],    // new values
);
```

#### Using a custom action string

The `$action` parameter accepts both the `AuditAction` enum and a plain string. Use a plain string for any domain-specific action that is not in the enum — there is no need to extend it.

```php
ActivityLogService::log('transfer', 'Ministers', 'Transferred Rev. John to St. Peters Parish');
ActivityLogService::log('ordination', 'Ministers', 'Rev. James ordained as Deacon.');
ActivityLogService::log('suspension', 'Staff', 'Staff member suspended pending investigation.');
ActivityLogService::log('reconcile', 'Accounts', 'Monthly accounts reconciled.');
```

Custom action strings are stored verbatim in the `action` column. Handle them gracefully in your UI:

```blade
@php
  $action = \Williamug\Audited\Enums\AuditAction::tryFrom($log->action);
@endphp

<span class="px-2 py-1 rounded text-xs font-medium
    {{ $action?->badgeColor() ?? 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400' }}">
    {{ $action?->label() ?? ucfirst(str_replace('_', ' ', $log->action)) }}
</span>
```

#### Attaching tags to a log entry

Pass a `tags` array to attach arbitrary key-value metadata to any log entry. Useful for batch IDs, import sources, workflow step names, or any context that does not belong in the description.

```php
ActivityLogService::log(
    AuditAction::Create,
    'Members',
    'Imported member records.',
    tags: ['batch_id' => 'imp_2024_001', 'source' => 'csv', 'rows' => 1500],
);

ActivityLogService::log(
    AuditAction::Update,
    'Settings',
    'Updated fee structure.',
    ['base_fee' => 5000],
    ['base_fee' => 7500],
    tags: ['ticket' => 'SUP-442', 'approved_by' => 'finance'],
);
```

Tags are stored as JSON and cast to an array on retrieval:

```php
$log->tags; // ['batch_id' => 'imp_2024_001', 'source' => 'csv', 'rows' => 1500]
```

#### Linking a manual log entry to a specific model

Pass a `$subject` to associate the log entry with an Eloquent model. This populates `subject_type` and `subject_id` so the entry appears in `$model->auditLogs()` alongside the automatically generated entries.

```php
ActivityLogService::log(
    AuditAction::Approve,
    'Collections',
    'Approved Sunday collection.',
    subject: $collection,
);
```

#### Logging on behalf of a specific user

By default the service reads the authenticated user from `auth()->user()`. Pass an explicit `$actingUser` when you need to record a different user — for example, during auth events where the session user is not yet set.

```php
ActivityLogService::log(
    AuditAction::Create,
    'Accounts',
    "Admin created account for '{$newUser->name}'.",
    actingUser: $adminUser,
);
```

---

### Suppressing Audit Logging

Use `withoutAudit()` to run a block of code without generating any log entries for that model class. This is useful for bulk imports, seeders, and test factories where the individual operations are not meaningful audit events.

```php
// No log entries are written for Invoice inside this callback
Invoice::withoutAudit(function () use ($invoices) {
    foreach ($invoices as $data) {
        Invoice::create($data);
    }
});
```

`withoutAudit()` only suppresses the model class it is called on. Other auditable models used inside the same callback are logged normally:

```php
Invoice::withoutAudit(function () {
    Invoice::create($data);      // not logged
    AuditLog::forceCreate($row); // unaffected
    Payment::create($payData);   // logged normally if Payment uses Auditable
});
```

Logging is always re-enabled after the callback, even if the callback throws an exception.

---

## Authentication Event Logging

When `log_auth_events` is `true` in the config (the default), the package automatically listens for Laravel's `Login`, `Logout`, and `Failed` auth events and writes a log entry for each.

| Event | Action recorded |
|---|---|
| `Illuminate\Auth\Events\Login` | `login` |
| `Illuminate\Auth\Events\Logout` | `logout` |
| `Illuminate\Auth\Events\Failed` | `failed_login` |

To disable this behaviour:

```php
// config/audit.php
'log_auth_events' => false,
```

### Custom login credential field

If your app identifies users by something other than `email` (for example, `phone_number` or `username`), configure the field used to identify subjects in failed login entries:

```php
// config/audit.php
'login_credential_field' => 'phone_number',
```

---

## Soft Deletes

Models that use Laravel's `SoftDeletes` trait get full lifecycle coverage automatically — no extra configuration required.

| Event | Action recorded | Description |
|---|---|---|
| `delete()` | `delete` | Deleted Invoice #42 |
| `restore()` | `restore` | Restored Invoice #42 |
| `forceDelete()` | `force_delete` | Permanently deleted Invoice #42 |

The `restore()` event produces a clean `restore` log entry. The intermediate `updated` event that Laravel fires internally during the restore operation is automatically suppressed so you do not see a spurious `deleted_at` change in the log.

```php
use Illuminate\Database\Eloquent\SoftDeletes;
use Williamug\Audited\Traits\Auditable;

class Invoice extends Model
{
    use Auditable, SoftDeletes;

    protected string $auditModule = 'Billing';
}
```

---

## The AuditAction Enum

`Williamug\Audited\Enums\AuditAction` provides a standard set of actions that covers most applications. Each case has a `label()` method for display text and a `badgeColor()` method for Tailwind CSS badge classes.

```php
use Williamug\Audited\Enums\AuditAction;

AuditAction::Login          // 'login'
AuditAction::Logout         // 'logout'
AuditAction::FailedLogin    // 'failed_login'
AuditAction::PasswordChange // 'password_change'
AuditAction::Create         // 'create'
AuditAction::Update         // 'update'
AuditAction::Delete         // 'delete'
AuditAction::Approve        // 'approve'
AuditAction::Reject         // 'reject'
AuditAction::Export         // 'export'
AuditAction::ViewReport     // 'view_report'
AuditAction::Restore        // 'restore'
AuditAction::ForceDelete    // 'force_delete'

// Human-readable label
AuditAction::PasswordChange->label();  // 'Password Change'
AuditAction::ForceDelete->label();     // 'Force Delete'

// Tailwind CSS badge classes (with dark mode)
AuditAction::Delete->badgeColor();
// 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'

AuditAction::Restore->badgeColor();
// 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400'
```

Use these directly in Blade views to render consistent action badges:

```blade
@foreach ($logs as $log)
    @php
      $action = Williamug\Audited\Enums\AuditAction::tryFrom($log->action);
    @endphp

    <span class="px-2 py-1 rounded text-xs font-medium {{ $action?->badgeColor() ?? 'bg-gray-100 text-gray-600' }}">
        {{ $action?->label() ?? ucfirst(str_replace('_', ' ', $log->action)) }}
    </span>
@endforeach
```

---

## Querying Audit Logs

The `old_values` and `new_values` columns are automatically cast to arrays. You can query `AuditLog` directly or use the named scopes described below.

```php
use Williamug\Audited\Models\AuditLog;

// All logs for a given module, most recent first
AuditLog::forModule('Billing')->latest()->paginate(20);

// All delete actions in the past 30 days
AuditLog::withAction(AuditAction::Delete)
    ->between(now()->subDays(30), now())
    ->get();

// All actions performed by a specific user
AuditLog::forUser($user)->latest()->get();

// All log entries for a specific record
AuditLog::forSubject($invoice)->latest()->get();

// Search across description, user name, and IP address
$term = 'John';
AuditLog::query()
    ->where(function ($q) use ($term) {
        $q->where('user_name', 'like', "%{$term}%")
          ->orWhere('description', 'like', "%{$term}%")
          ->orWhere('ip_address', 'like', "%{$term}%");
    })
    ->get();
```

### Query Scopes

All scopes are chainable and work with any other Eloquent query methods.

| Scope | Description |
|---|---|
| `forUser($user)` | Filter by a `User` model instance or a raw user ID integer |
| `forModule(string $module)` | Filter by module name |
| `withAction(AuditAction\|string $action)` | Filter by action — accepts the enum or a plain string |
| `between($from, $to)` | Filter by `created_at` date range — accepts Carbon instances, strings, or timestamps |
| `forSubject(Model $subject)` | Filter by a specific Eloquent model instance |

```php
// Combine scopes freely
AuditLog::forUser($user)
    ->forModule('Billing')
    ->withAction(AuditAction::Update)
    ->between(now()->startOfMonth(), now()->endOfMonth())
    ->latest()
    ->paginate(20);
```

### Subject Relationship

Every log entry written by the `Auditable` trait stores a polymorphic link back to the model that was acted on (`subject_type` and `subject_id` columns). This link is also populated when you pass `subject:` to `ActivityLogService::log()` manually.

**Querying from the model:**

```php
// All audit log entries for this specific invoice
$invoice->auditLogs()->latest()->get();

// Paginate the audit history for a record
$invoice->auditLogs()->latest()->paginate(10);
```

**Resolving the subject from a log entry:**

```php
$log = AuditLog::find($id);

// Returns the Invoice, User, or whatever model was acted on
$log->subject;
```

**Existing apps:** see [Upgrading existing installs](#upgrading-existing-installs) for the migration that adds all new columns at once.

---

## Extending the AuditLog Model

If your application needs additional relationships or extra columns, extend the package's base model.

### Step 1 — Create your extended model

```php
// app/Models/AuditLog.php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Williamug\Audited\Models\AuditLog as BaseAuditLog;

class AuditLog extends BaseAuditLog
{
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
```

### Step 2 — Point the config to your model

```php
// config/audit.php
'model' => \App\Models\AuditLog::class,
```

### Step 3 — Add extra columns in a separate migration

Create a new migration in your application (do not modify the package migration):

```bash
php artisan make:migration add_organisation_id_to_audit_logs_table
```

```php
public function up(): void
{
    Schema::table('audit_logs', function (Blueprint $table) {
        $table->unsignedBigInteger('organisation_id')->nullable()->index()->after('user_level');
        $table->foreign('organisation_id')->references('id')->on('organisations')->nullOnDelete();
    });
}
```

---

## Multitenancy

The package supports single-database multitenancy with multiple branches out of the box. The column names are entirely up to your application — `company_id`, `tenant_id`, `business_id`, `facility_id`, `branch_id` — whatever your data model uses.

There are two sides to multitenancy: **writing** (stamping the tenant onto each log entry) and **reading** (ensuring each tenant only queries their own logs). Both are handled on your custom model.

### Stamping Tenant Context on Every Log Entry

Override `extraColumns()` on your custom model. The package calls this method on every write and merges the returned array into the log entry automatically.

```php
// app/Models/AuditLog.php
namespace App\Models;

use Williamug\Audited\Models\AuditLog as BaseAuditLog;

class AuditLog extends BaseAuditLog
{
    protected static function extraColumns(): array
    {
        return [
            'company_id' => auth()->user()?->company_id,
            'branch_id'  => auth()->user()?->branch_id,
        ];
    }
}
```

Add the corresponding columns in a migration:

```php
public function up(): void
{
    Schema::table('audit_logs', function (Blueprint $table) {
        $table->unsignedBigInteger('company_id')->nullable()->index()->after('user_level');
        $table->unsignedBigInteger('branch_id')->nullable()->index()->after('company_id');
    });
}
```

Every log entry — whether written automatically by the `Auditable` trait, by auth event listeners, or by a manual `ActivityLogService::log()` call — will now include the current user's `company_id` and `branch_id`.

### Scoping Queries per Tenant

Add a global scope to your custom model so tenant A can never read tenant B's logs:

```php
class AuditLog extends BaseAuditLog
{
    protected static function boot(): void
    {
        parent::boot();

        static::addGlobalScope('tenant', function ($query) {
            $query->where('company_id', auth()->user()?->company_id);
        });
    }

    protected static function extraColumns(): array
    {
        return [
            'company_id' => auth()->user()?->company_id,
            'branch_id'  => auth()->user()?->branch_id,
        ];
    }
}
```

### Branch-level Isolation

Make the scope conditional — head-office users see all branches while branch users see only their own:

```php
static::addGlobalScope('tenant', function ($query) {
    $user = auth()->user();

    $query->where('company_id', $user?->company_id);

    if (! $user?->is_head_office) {
        $query->where('branch_id', $user?->branch_id);
    }
});
```

### Full Example

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Williamug\Audited\Models\AuditLog as BaseAuditLog;

class AuditLog extends BaseAuditLog
{
    protected static function boot(): void
    {
        parent::boot();

        static::addGlobalScope('tenant', function ($query) {
            $user = auth()->user();

            $query->where('company_id', $user?->company_id);

            if (! $user?->is_head_office) {
                $query->where('branch_id', $user?->branch_id);
            }
        });
    }

    protected static function extraColumns(): array
    {
        return [
            'company_id' => auth()->user()?->company_id,
            'branch_id'  => auth()->user()?->branch_id,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
```

```php
// config/audit.php
'model' => \App\Models\AuditLog::class,
```

---

## Queue / Async Logging

By default, audit log entries are written synchronously on every model event. In high-traffic applications this adds a small database write to every request. You can move all writes to a background queue with a single config change:

```php
// config/audit.php

// Dispatch on the default queue
'queue' => env('AUDIT_QUEUE', true),

// Dispatch on a named queue
'queue' => env('AUDIT_QUEUE', 'audit'),
```

Or via your `.env` file:

```dotenv
AUDIT_QUEUE=audit
```

When queued, a `WriteAuditLog` job is dispatched instead of writing directly. No call sites change — the public API is identical. Make sure your queue worker is running:

```bash
php artisan queue:work --queue=audit
```

To return to synchronous writes, set `'queue' => false` (the default).

---

## Silent Failures

By default, a failed audit log write (connection error, missing table, constraint violation) throws an exception and surfaces to the caller. In production you may prefer that audit logging never crashes a real user request:

```php
// config/audit.php
'silent_failures' => env('AUDIT_SILENT_FAILURES', true),
```

Or via `.env`:

```dotenv
AUDIT_SILENT_FAILURES=true
```

When enabled, exceptions from log writes are caught, logged to Laravel's default logger at `error` level, and then silently discarded. The rest of the request continues normally.

---

## Request Context

Every log entry automatically captures full context about the request it came from. No configuration required.

| Column | Web request | API request | CLI command |
|---|---|---|---|
| `platform` | `web` | `mobile` | `cli` |
| `ip_address` | Client IP | Client IP | `null` |
| `user_agent` | Browser UA string | Client UA string | `null` |
| `url` | Full URL with query string | Full URL | `null` |
| `http_method` | `GET`, `POST`, `PUT`, … | `GET`, `POST`, `PUT`, … | `null` |
| `route_name` | Named route or `null` | Named route or `null` | `null` |
| `auth_guard` | `web`, `api`, `admin`, … | `web`, `api`, `admin`, … | Guard name or `null` |
| `request_id` | UUID (same for all logs in the request) | UUID | UUID (per command invocation) |

### Request ID

A single HTTP request often triggers multiple audit log entries — a model update, a manual `ActivityLogService::log()` call, an observer firing. By default these entries look unrelated in the log table.

The `request_id` UUID is generated once per request using Laravel's `scoped()` container binding, which resets automatically between requests in long-running processes like Octane. All log entries from the same request share the same UUID:

```php
// All audit events from a specific request
AuditLog::where('request_id', $requestId)->get();
```

### URL and HTTP method

```php
// All POST requests to a module in the past week
AuditLog::forModule('Billing')
    ->where('http_method', 'POST')
    ->between(now()->subWeek(), now())
    ->get();
```

### Route name

The named route is more stable than the URL path — routes are renamed less often than URLs change. Use it to group logs by feature:

```php
AuditLog::where('route_name', 'invoices.update')->latest()->get();
```

### Auth guard

In applications with multiple authentication guards (e.g. `web` for the admin panel, `api` for the mobile app), the guard column tells you which path the user came through:

```php
// All actions performed via the API guard
AuditLog::where('auth_guard', 'api')->forUser($user)->get();
```

### Upgrading existing installs

If you are upgrading from an earlier version, add all new columns with a single migration:

```bash
php artisan make:migration upgrade_audit_logs_table
```

```php
public function up(): void
{
    Schema::table('audit_logs', function (Blueprint $table) {
        // Tags — arbitrary key-value context on manual log entries
        $table->json('tags')->nullable()->after('description');

        // Request context
        $table->string('url', 2048)->nullable()->after('user_agent');
        $table->string('http_method', 10)->nullable()->after('url');
        $table->string('route_name', 255)->nullable()->index()->after('http_method');
        $table->string('auth_guard', 50)->nullable()->after('route_name');

        // Polymorphic subject link
        $table->string('subject_type', 255)->nullable()->after('auth_guard');
        $table->unsignedBigInteger('subject_id')->nullable()->after('subject_type');
        $table->index(['subject_type', 'subject_id']);

        // Request ID tracing
        $table->string('request_id', 36)->nullable()->index()->after('subject_id');
    });
}
```

---

## Pruning Old Logs

The `audit:prune` command deletes log entries older than the configured retention period.

```bash
# Uses prune_after_months from config (default: 3)
php artisan audit:prune

# Override the retention period for a one-off run
php artisan audit:prune --months=6

# Preview what would be deleted without deleting anything
php artisan audit:prune --dry-run
php artisan audit:prune --months=6 --dry-run
```

The command is **automatically scheduled quarterly** by the package's service provider. You do not need to add it to your application's schedule.

To disable automatic pruning, set `prune_after_months` to `null` in the config:

```php
// config/audit.php
'prune_after_months' => null,
```

When `prune_after_months` is `null` and no `--months` flag is passed, the command warns and exits without deleting anything.

---

## Advanced Configuration

### Custom User Fields

By default the package reads `name` from the authenticated user as the display name, and does not record a user level. Change these to match your User model:

```php
// config/audit.php
'user_name_field'  => 'full_name',  // or 'email', 'username', etc.
'user_level_field' => 'role',       // or 'level', 'tier', etc. Set to null to disable.
```

### Custom Login Credential Field

Applications that identify users by phone number or username rather than email should configure this field so that failed login entries contain the correct identifier:

```php
// config/audit.php
'login_credential_field' => 'phone_number',
```

### Sensitive Fields

Extend the default list of fields that are stripped before writing `old_values` and `new_values`:

```php
// config/audit.php
'sensitive_fields' => [
    'password',
    'remember_token',
    'two_factor_secret',
    'two_factor_recovery_codes',
    'two_factor_confirmed_at',
    'api_key',       // add your own
    'stripe_secret', // add your own
],
```

Per-model exclusions can also be declared with `$auditExclude` on the model (see [Excluding Fields from Logs](#excluding-fields-from-logs)).

### Custom Table Name

If `audit_logs` conflicts with an existing table in your application, change the name before running the migration:

```php
// config/audit.php
'table' => 'activity_logs',
```

---

## Testing

When writing tests that involve audit logging, use `RefreshDatabase` and assert against the `audit_logs` table directly.

```php
use Illuminate\Foundation\Testing\RefreshDatabase;
use Williamug\Audited\Enums\AuditAction;
use Williamug\Audited\Models\AuditLog;
use Williamug\Audited\Services\ActivityLogService;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_invoice_writes_an_audit_log(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Invoice::create(['number' => 'INV-001', 'amount' => 5000]);

        $this->assertDatabaseHas('audit_logs', [
            'action'  => 'create',
            'module'  => 'Billing',
            'user_id' => $user->id,
        ]);
    }

    public function test_audit_log_links_to_the_invoice(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $invoice = Invoice::create(['number' => 'INV-001', 'amount' => 5000]);

        expect($invoice->auditLogs)->toHaveCount(1)
            ->and($invoice->auditLogs->first()->action)->toBe('create');
    }

    public function test_sensitive_fields_are_not_stored(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        ActivityLogService::log(
            AuditAction::Update,
            'Users',
            'Updated user.',
            ['name' => 'Old', 'password' => 'secret'],
            ['name' => 'New', 'password' => 'newsecret'],
        );

        $log = AuditLog::latest()->first();
        $this->assertArrayNotHasKey('password', $log->old_values);
        $this->assertArrayNotHasKey('password', $log->new_values);
    }
}
```

To suppress audit logging during unrelated tests that create models, use `withoutAudit()` instead of `Model::withoutEvents()` — it only suppresses audit writes and leaves other observers intact:

```php
Invoice::withoutAudit(function () {
    Invoice::factory()->count(10)->create();
});
```

To test queued logging, use `Queue::fake()`:

```php
use Illuminate\Support\Facades\Queue;
use Williamug\Audited\Jobs\WriteAuditLog;

Queue::fake();
config(['audit.queue' => true]);

Invoice::create(['number' => 'INV-001', 'amount' => 5000]);

Queue::assertPushed(WriteAuditLog::class);
```

---

## License

The MIT License. See [LICENSE](LICENSE) for details.
