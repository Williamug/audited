# Audited

[![Latest Version on Packagist](https://img.shields.io/packagist/v/williamug/audited.svg?style=flat-square)](https://packagist.org/packages/williamug/audited)
[![tests](https://github.com/Williamug/audited/actions/workflows/tests.yml/badge.svg)](https://github.com/Williamug/audited/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/williamug/audited.svg?style=flat-square)](https://packagist.org/packages/williamug/audited)

_"The only Laravel audit package that ships a complete admin UI — Livewire table, Vue/Inertia table, and per-model timeline — with zero configuration."_

A simple, robust audit logging package for Laravel applications. Drop one trait onto a model and every create, update, delete, and many-to-many relationship change is automatically recorded. Authentication events, manual logging, per-model subject relationships, request-level tracing, async queue support, scheduled pruning, and a configurable schema are included out of the box.

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
    - [Facade](#facade)
    - [Global helper](#global-helper)
    - [Service class](#service-class)
  - [Suppressing Audit Logging](#suppressing-audit-logging)
- [System Actors (Causer)](#system-actors-causer)
- [Authentication Event Logging](#authentication-event-logging)
- [Soft Deletes](#soft-deletes)
- [Many-to-Many Relationships](#many-to-many-relationships)
- [The AuditAction Enum](#the-auditaction-enum)
- [Querying Audit Logs](#querying-audit-logs)
  - [Query Scopes](#query-scopes)
  - [Subject Relationship](#subject-relationship)
- [Audit Trail Viewer](#audit-trail-viewer)
  - [Global Audit Log Table](#global-audit-log-table)
  - [Per-model Timeline](#per-model-timeline)
  - [Tailwind CSS setup](#tailwind-css-setup)
  - [Customising styles with CSS](#customising-styles-with-css)
  - [Publishing and fully overriding the views](#publishing-and-fully-overriding-the-views)
- [Vue and Inertia](#vue-and-inertia)
  - [Self-fetch mode](#self-fetch-mode-standalone-vue--spa)
  - [Props / Inertia mode](#props--inertia-mode)
  - [Component props reference](#component-props-reference)
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
- [Real-World Example](#real-world-example)
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

For events not tied to a model lifecycle — approving a report, exporting data, any custom business action — the package gives you three equivalent ways to write a log entry. Pick the one that fits your style.

**Facade** (recommended — no import needed, IDE-friendly):

```php
Audited::log(AuditAction::Approve, 'Collections', 'Approved Sunday collection.');

// With old/new values
Audited::log(
    AuditAction::Update,
    'Settings',
    'Updated application settings.',
    ['maintenance_mode' => false],
    ['maintenance_mode' => true],
);
```

The `Audited` facade is registered automatically via Laravel's package auto-discovery — no `use` import required in your controllers or services.

**Global helper** (best for quick one-liners):

```php
audited(AuditAction::Approve, 'Collections', 'Approved Sunday collection.');

audited('transfer', 'Ministers', 'Transferred Rev. John to St. Peters Parish');
```

The `audited()` helper is loaded automatically via Composer's `files` autoload — available everywhere in your application with no imports.

**Service class** (explicit, useful when injecting or mocking):

```php
use Williamug\Audited\Services\ActivityLogService;

ActivityLogService::log(AuditAction::Approve, 'Collections', 'Approved Sunday collection.');
```

All three call the same underlying logic and write to the same table. The Facade and helper both support the full set of named arguments:

```php
Audited::log(
    action: AuditAction::Create,
    module: 'Members',
    description: 'Imported member records.',
    tags: ['batch_id' => 'imp_2024_001', 'source' => 'csv'],
);

audited(
    action: AuditAction::Update,
    module: 'Settings',
    description: 'Updated fee structure.',
    oldValues: ['base_fee' => 5000],
    newValues: ['base_fee' => 7500],
    tags: ['ticket' => 'SUP-442'],
);
```

#### Using a custom action string

The `$action` parameter accepts both the `AuditAction` enum and a plain string. Use a plain string for any domain-specific action that is not in the enum — there is no need to extend it.

```php
audited('transfer', 'Ministers', 'Transferred Rev. John to St. Peters Parish');
audited('ordination', 'Ministers', 'Rev. James ordained as Deacon.');
audited('suspension', 'Staff', 'Staff member suspended pending investigation.');
audited('reconcile', 'Accounts', 'Monthly accounts reconciled.');
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
Audited::log(
    AuditAction::Create,
    'Members',
    'Imported member records.',
    tags: ['batch_id' => 'imp_2024_001', 'source' => 'csv', 'rows' => 1500],
);

audited(
    AuditAction::Update,
    'Settings',
    'Updated fee structure.',
    oldValues: ['base_fee' => 5000],
    newValues: ['base_fee' => 7500],
    tags: ['ticket' => 'SUP-442', 'approved_by' => 'finance'],
);
```

Tags are stored as JSON and cast to an array on retrieval:

```php
$log->tags; // ['batch_id' => 'imp_2024_001', 'source' => 'csv', 'rows' => 1500]
```

#### Linking a manual log entry to a specific model

Pass a `subject` to associate the log entry with an Eloquent model. This populates `subject_type` and `subject_id` so the entry appears in `$model->auditLogs()` alongside the automatically generated entries.

```php
Audited::log(
    AuditAction::Approve,
    'Collections',
    'Approved Sunday collection.',
    subject: $collection,
);
```

#### Specifying the actor

By default the log reads `auth()->user()` as the actor. Pass an explicit `causer` when you need to attribute the log entry to a different user, or to a system process with no user at all.

```php
// Attribute to a specific user (e.g. admin acting on behalf of another user)
Audited::log(
    AuditAction::Create,
    'Accounts',
    "Admin created account for '{$newUser->name}'.",
    causer: $adminUser,
);
```

For system actors (jobs, commands, scheduled tasks) see [System Actors (Causer)](#system-actors-causer).

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

## System Actors (Causer)

When logging from a job, command, or scheduled task there is no authenticated user. Without explicit attribution `user_id` is `null`, which is ambiguous — was this a guest action or a background process?

The `causer` parameter solves this. It accepts any Eloquent user model (same as always), a `SystemCauser`, or anything that implements the `Causer` interface.

```php
use Williamug\Audited\Causers\SystemCauser;
use Williamug\Audited\Enums\AuditAction;
use Williamug\Audited\Services\ActivityLogService;

// Inside a queued job
ActivityLogService::log(
    AuditAction::Create,
    'Members',
    'Imported 1,500 member records from CSV.',
    causer: new SystemCauser('ImportMembersJob', 'job'),
);

// Inside an Artisan command
ActivityLogService::log(
    AuditAction::Delete,
    'Sessions',
    'Purged 230 expired sessions.',
    causer: new SystemCauser('sessions:prune', 'command'),
);

// Inside a scheduled task
ActivityLogService::log(
    AuditAction::Export,
    'Reports',
    'Generated nightly financial summary.',
    causer: new SystemCauser('NightlyReportGenerator', 'system'),
);
```

The `causer_type` column makes the actor unambiguous in every log entry:

| `user_id` | `user_name` | `causer_type` | Meaning |
|---|---|---|---|
| `42` | `Jane Doe` | `user` | Authenticated human |
| `null` | `ImportMembersJob` | `job` | Queued job |
| `null` | `sessions:prune` | `command` | Artisan command |
| `null` | `NightlyReportGenerator` | `system` | Scheduled process |
| `null` | `null` | `null` | No actor (e.g. failed login attempt) |

### Custom causer type

Implement the `Causer` interface on any class:

```php
use Williamug\Audited\Contracts\Causer;

class DataMigration implements Causer
{
    public function getCauserName(): string { return 'DataMigration v2.3'; }
    public function getCauserType(): string { return 'migration'; }
}

ActivityLogService::log(
    AuditAction::Update,
    'Schema',
    'Backfilled missing invoice_number values.',
    causer: new DataMigration(),
);
```

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

## Many-to-Many Relationships

Changes to `BelongsToMany` pivot tables are not recorded by default — pivot operations (`attach`, `detach`, `sync`, `updateExistingPivot`) bypass Eloquent's standard model events and would otherwise go untracked.

The `Auditable` trait intercepts these operations transparently through a custom relation class. Opt in per model by declaring an `$auditRelationships` array with the names of the relationships you want to track.

### Opting in

```php
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Williamug\Audited\Traits\Auditable;

class Role extends Model
{
    use Auditable;

    protected string $auditModule = 'Roles';

    // Only relationships listed here will be audited.
    public array $auditRelationships = ['permissions'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }
}
```

No other changes are needed. All four pivot operations are now automatically logged for the `permissions` relationship:

| Operation | Action recorded | `old_values` | `new_values` |
|---|---|---|---|
| `attach($ids)` | `update` | `null` | `['permissions' => [1, 2]]` |
| `detach($ids)` | `update` | `['permissions' => [1, 2]]` | `null` |
| `sync($ids)` | `update` × 2 | detach entry + attach entry | (see above) |
| `updateExistingPivot($id, $attrs)` | `update` | `null` | `['permissions' => [1]]` |

`sync()` produces two log entries when IDs change — one for the detached IDs and one for the newly attached IDs. When the synced set is identical to the current set, no entries are written.

### What it looks like

```php
$role->permissions()->attach([1, 2, 3]);
// Writes:
// action      → 'update'
// module      → 'Roles'
// description → 'Attached permissions to Role #1'
// old_values  → null
// new_values  → ['permissions' => [1, 2, 3]]
// subject     → Role #1 (polymorphic link)

$role->permissions()->detach([2]);
// Writes:
// action      → 'update'
// description → 'Detached permissions from Role #1'
// old_values  → ['permissions' => [2]]
// new_values  → null

$role->permissions()->sync([3, 4]);
// Role had [1, 2, 3] — writes two entries:
// 1. Detached permissions [1, 2]
// 2. Attached permissions [4]
// (ID 3 was already attached — no entry for it)
```

### Multiple relationships

List every relationship you want audited:

```php
public array $auditRelationships = ['permissions', 'roles', 'tags'];
```

Relationships not in the list are silently skipped even if `attach()` or `detach()` is called on them.

### Suppressing pivot logs

`withoutAudit()` suppresses pivot log entries for the model class in the same way it suppresses create/update/delete entries:

```php
Role::withoutAudit(function () use ($role) {
    $role->permissions()->sync([1, 2, 3]); // not logged
});
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

## Audit Trail Viewer

The package ships with two ready-made viewer components:

- **Global audit log table** — `<livewire:audited::log-table />` — an admin dashboard showing all entries across the entire application, with live search, filters, and expandable detail rows.
- **Per-model timeline** — `<x-audited::timeline :subject="$model" />` — a scoped history viewer for a single record, shown inline on a show/detail page.

---

### Global Audit Log Table

A full-featured, live-filtered table for an audit log admin page. Drop one tag and it works:

```blade
<livewire:audited::log-table />
```

That single tag renders:

- **Live search** — matches user name, description, and IP address as you type
- **Action filter** — dropdown auto-populated from the actions in your logs
- **Module filter** — dropdown auto-populated from the modules in your logs
- **Level filter** — filter by user role/level
- **Platform filter** — Web, Mobile, or CLI
- **Date range** — from/to date pickers
- **Clear Filters** — appears only when a filter is active
- **Paginated table** — Date & Time · User · Level · Action badge · Module · Description · Platform badge · IP Address · Device
- **Expandable detail row** — click **View** on any row to see request context, old/new value diff, and tags inline

```blade
{{-- Audit log admin page --}}
<div class="max-w-7xl mx-auto px-4 py-8">
    <h1 class="text-xl font-semibold text-gray-900 dark:text-white mb-6">Audit Log</h1>
    <livewire:audited::log-table />
</div>
```

Requires Livewire 3 or 4. The component is only registered when Livewire is detected — no error if it is absent.

---

### Per-model Timeline

A chronological history viewer scoped to one record. Drop it on any show/detail page:

```blade
<x-audited::timeline :subject="$invoice" />
```

A **timeline** is a vertical list of events ordered by time, connected by a line. Each entry shows the action badge, the module, what changed, who did it, and when.

### Blade component

```blade
{{-- Renders the last 25 audit entries for $invoice --}}
<x-audited::timeline :subject="$invoice" />

{{-- Show more entries --}}
<x-audited::timeline :subject="$invoice" :limit="50" />

{{-- Show a before/after diff table for each change --}}
<x-audited::timeline :subject="$invoice" :show-values="true" />
```

| Prop | Type | Default | Description |
|---|---|---|---|
| `subject` | `Model` | required | The Eloquent model whose history to display |
| `limit` | `int` | `25` | Maximum number of entries to show |
| `show-values` | `bool` | `false` | Render a before/after diff table for each entry |

Each rendered entry includes:

- **Action badge** — colour-coded by action type using the `AuditAction` enum
- **Module** — the area of the application (e.g. Billing, Users)
- **Description** — the human-readable summary
- **Actor name** — who performed the action, with a causer-type badge for system actors (job, command, etc.)
- **Timestamp** — relative (`2 minutes ago`) with the absolute time visible on hover

### Livewire component (live pagination)

If your application uses Livewire 3 or 4, a live-paginated version is available:

```blade
<livewire:audited::timeline :subject="$invoice" />

<livewire:audited::timeline :subject="$invoice" :per-page="20" :show-values="true" />
```

| Prop | Type | Default | Description |
|---|---|---|---|
| `subject` | `Model` | required | The Eloquent model whose history to display |
| `per-page` | `int` | `10` | Entries per page |
| `show-values` | `bool` | `false` | Render a before/after diff table for each entry |

Livewire is an **optional** dependency — the component is only registered when Livewire is detected. No error is thrown if Livewire is not installed.

### Tailwind CSS setup

The package views use Tailwind CSS utility classes. In production, Tailwind's content scanner only looks at your application files — `vendor/` is excluded by default. Add the package view path to your Tailwind config so the classes are never purged.

**Tailwind v3 (`tailwind.config.js`):**

```js
module.exports = {
  content: [
    './resources/**/*.blade.php',
    './vendor/williamug/audited/resources/views/**/*.blade.php', // ← add this
  ],
  // ...
}
```

**Tailwind v4 (`resources/css/app.css`):**

```css
@import "tailwindcss";
@source "../../vendor/williamug/audited/resources/views"; /* ← add this */
```

Without this, the audit views will render with no styling in any environment where Tailwind's output is built (staging, production, or any Vite/Mix build step).

---

### Customising styles with CSS

Every meaningful element in the package views has a semantic `audited-*` CSS class alongside the Tailwind utilities. You can target these with plain CSS in your own stylesheet — **no need to publish the views**.

**Timeline components (`<x-audited::timeline>` and `<livewire:audited::timeline>`):**

| Class | Element |
|---|---|
| `audited-timeline` | Outer wrapper |
| `audited-timeline-entry` | Each `<li>` entry |
| `audited-timeline-connector` | Vertical connecting line |
| `audited-timeline-dot` | Circle dot for each entry |
| `audited-action-badge` | Action badge (Create, Update, etc.) |
| `audited-module-label` | Module chip |
| `audited-timeline-description` | Description text |
| `audited-timeline-actor` | Actor/user name line |
| `audited-causer-badge` | Causer-type badge (job, command, etc.) |
| `audited-values-diff` | Before/after diff table wrapper |
| `audited-values-diff-field` | Field name cell |
| `audited-values-diff-before` | Old value cell |
| `audited-values-diff-after` | New value cell |
| `audited-timestamp` | `<time>` element |

**Log table (`<livewire:audited::log-table>`):**

| Class | Element |
|---|---|
| `audited-log-table` | Outer wrapper |
| `audited-log-table-filters` | Filters card |
| `audited-log-table-search` | Search input |
| `audited-log-table-select` | Filter dropdowns |
| `audited-log-table-date` | Date range inputs |
| `audited-log-table-clear` | Clear Filters button |
| `audited-log-table-row` | Each table row |
| `audited-log-table-row--expanded` | Modifier on an expanded row |
| `audited-log-table-detail` | Expanded detail `<tr>` |
| `audited-log-table-detail-context` | Request context section inside detail row |
| `audited-log-table-detail-tags` | Tags section inside detail row |
| `audited-log-table-toggle` | View/Close toggle button |
| `audited-action-badge` | Action badge |
| `audited-causer-badge` | Causer-type badge |
| `audited-platform-badge` | Platform badge |
| `audited-platform-badge--web` | Web platform variant |
| `audited-platform-badge--mobile` | Mobile platform variant |
| `audited-platform-badge--cli` | CLI platform variant |
| `audited-values-diff` | Before/after diff table wrapper |
| `audited-values-diff-field` | Field name cell |
| `audited-values-diff-before` | Old value cell |
| `audited-values-diff-after` | New value cell |

**Example — brand the action badge with your own colours:**

```css
/* In your app.css or a dedicated audited.css */

/* Override the entire badge */
.audited-action-badge {
  border-radius: 4px;
  font-size: 0.7rem;
  letter-spacing: 0.05em;
}

/* Style platform badges */
.audited-platform-badge--web    { background: #e0f2fe; color: #0369a1; }
.audited-platform-badge--mobile { background: #dcfce7; color: #15803d; }
.audited-platform-badge--cli    { background: #fef3c7; color: #92400e; }

/* Style the timeline dot by action type — combine with a data attribute if needed */
.audited-timeline-dot { border-width: 2px; }
```

No view publishing required.

---

### Publishing and fully overriding the views

For deeper structural changes, publish and edit:

```bash
php artisan vendor:publish --tag=audited-views
```

Published views live in `resources/views/vendor/audited/` and are never overwritten by package updates.

```
resources/views/vendor/audited/
├── components/
│   └── timeline.blade.php            ← Blade timeline view
└── livewire/
    ├── audit-timeline.blade.php      ← Livewire timeline view
    └── audit-log-table.blade.php     ← Livewire log table view
```

---

## Vue and Inertia

The package ships two Vue 3 components — `AuditLogTable.vue` and `AuditTimeline.vue` — that mirror the Livewire components exactly. They work in two modes automatically detected at runtime:

| Mode | When to use | How data flows |
|---|---|---|
| **Self-fetch** | Standalone Vue SPA, or any page where you don't want to write a controller | Component fetches JSON from the built-in API routes |
| **Props / Inertia** | Inertia apps — data comes from your controller | Pass a paginator as props; component emits events for filter changes |

### Step 1 — Publish the Vue components

```bash
php artisan vendor:publish --tag=audited-vue
```

Files are copied to `resources/js/vendor/audited/`. Edit them freely — they follow the same `audited-*` CSS class conventions as the Blade/Livewire views.

### Step 2 — Enable the API routes (self-fetch mode only)

Skip this step if you are using Inertia props mode.

```env
# .env
AUDIT_API_ROUTES=true
```

The routes are registered with `['web', 'auth']` middleware by default. Override in `config/audit.php`:

```php
'api_middleware' => ['web', 'auth:sanctum'],
'api_prefix'     => 'admin/api/audit',     // optional custom prefix
```

### Self-fetch mode (standalone Vue / SPA)

Drop a component in and it works — no controller needed:

```vue
<script setup>
import AuditLogTable from '@/vendor/audited/AuditLogTable.vue'
import AuditTimeline from '@/vendor/audited/AuditTimeline.vue'
</script>

<template>
  <!-- Full admin table — fetches /audited/api/logs automatically -->
  <AuditLogTable />

  <!-- Per-model timeline — fetches /audited/api/timeline automatically -->
  <AuditTimeline
    subject-type="App\Models\Invoice"
    :subject-id="invoice.id"
    :show-values="true"
  />
</template>
```

### Props / Inertia mode

Use the `ServesAuditLogs` trait to build the controller with one line:

```php
use Williamug\Audited\Http\Concerns\ServesAuditLogs;

class AuditLogController extends Controller
{
    use ServesAuditLogs;

    public function index(Request $request)
    {
        return Inertia::render('Admin/AuditLog', $this->auditLogProps($request));
    }

    public function invoiceHistory(Request $request, Invoice $invoice)
    {
        return Inertia::render('Invoices/History', $this->auditTimelineProps($request, $invoice));
    }
}
```

The props passed to the page match what the Vue components expect (`logs`, `allActions`, `allModules`, `allLevels`, `filters`).

In the Vue page component, listen to `filter-change` and call Inertia's `router.get()`:

```vue
<!-- resources/js/Pages/Admin/AuditLog.vue -->
<script setup>
import { router } from '@inertiajs/vue3'
import AuditLogTable from '@/vendor/audited/AuditLogTable.vue'

const props = defineProps({
  logs:       Object,
  allActions: Array,
  allModules: Array,
  allLevels:  Array,
  filters:    Object,
})

function onFilterChange(filters) {
  router.get(route('admin.audit'), filters, { preserveState: true, replace: true })
}
</script>

<template>
  <AuditLogTable
    :logs="logs"
    :all-actions="allActions"
    :all-modules="allModules"
    :all-levels="allLevels"
    :filters="filters"
    @filter-change="onFilterChange"
  />
</template>
```

For the timeline in props mode:

```vue
<!-- resources/js/Pages/Invoices/History.vue -->
<script setup>
import AuditTimeline from '@/vendor/audited/AuditTimeline.vue'
defineProps({ logs: Object })
</script>

<template>
  <AuditTimeline :logs="logs" :show-values="true" />
</template>
```

### Component props reference

**AuditLogTable.vue**

| Prop | Type | Default | Description |
|---|---|---|---|
| `logs` | `Object\|null` | `null` | Paginator from `auditLogProps()`. If null → self-fetch mode |
| `allActions` | `Array` | `[]` | Action filter options |
| `allModules` | `Array` | `[]` | Module filter options |
| `allLevels` | `Array` | `[]` | Level filter options |
| `filters` | `Object` | `{}` | Initial filter values (Inertia mode) |
| `endpoint` | `String` | `/audited/api/logs` | API endpoint (self-fetch mode) |

Events: `filter-change(filters)`, `page-change(url)`

**AuditTimeline.vue**

| Prop | Type | Default | Description |
|---|---|---|---|
| `logs` | `Object\|Array\|null` | `null` | Paginator or array from `auditTimelineProps()`. If null → self-fetch mode |
| `subjectType` | `String` | `null` | Eloquent model class (self-fetch mode) |
| `subjectId` | `String\|Number` | `null` | Model primary key (self-fetch mode) |
| `endpoint` | `String` | `/audited/api/timeline` | API endpoint (self-fetch mode) |
| `showValues` | `Boolean` | `false` | Show before/after diff table |
| `perPage` | `Number` | `10` | Entries per page (self-fetch mode) |

Events: `page-change(url)`

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

        // Causer type — distinguishes human actors from system processes
        $table->string('causer_type', 50)->nullable()->after('user_level');
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

## Real-World Example

The following shows a complete integration in a billing module. This is the full picture — model, controller action, background job, and audit history page. The only additions to your existing code are the trait, the causer, and one component tag.

### The model

```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Williamug\Audited\Traits\Auditable;

class Invoice extends Model
{
    use Auditable, SoftDeletes;

    // Module label written to every log entry for this model
    protected string $auditModule = 'Billing';

    // High-frequency columns that are not meaningful audit events
    public array $auditExclude = ['last_viewed_at', 'pdf_cached_at'];

    // Richer label in log descriptions instead of "Invoice #42"
    public function auditLabel(): string
    {
        return "Invoice #{$this->number} — {$this->client->name}";
    }
}
```

Every `create`, `update`, `delete`, `restore`, and `forceDelete` on `Invoice` is now automatically recorded. Nothing else is required for that coverage.

### A controller action

```php
use Williamug\Audited\Enums\AuditAction;
use Williamug\Audited\Services\ActivityLogService;

class InvoiceController extends Controller
{
    public function approve(Invoice $invoice): RedirectResponse
    {
        $invoice->update(['status' => 'approved', 'approved_at' => now()]);

        // The update above already writes an automatic log entry.
        // This additional manual entry records the business action explicitly.
        ActivityLogService::log(
            AuditAction::Approve,
            'Billing',
            "Approved Invoice #{$invoice->number} ({$invoice->client->name}).",
            subject: $invoice,
            tags: ['approved_by_ip' => request()->ip()],
        );

        return redirect()->back()->with('success', 'Invoice approved.');
    }
}
```

### A background job

```php
use Williamug\Audited\Causers\SystemCauser;

class SendOverdueRemindersJob implements ShouldQueue
{
    public function handle(): void
    {
        foreach (Invoice::overdue()->get() as $invoice) {
            Mail::to($invoice->client->email)->send(new OverdueReminderMail($invoice));

            ActivityLogService::log(
                'reminder_sent',
                'Billing',
                "Overdue reminder sent to {$invoice->client->email}.",
                subject: $invoice,
                causer: new SystemCauser('SendOverdueRemindersJob', 'job'),
            );
        }
    }
}
```

### The audit history page

```blade
{{-- resources/views/invoices/show.blade.php --}}

<h2>Invoice #{{ $invoice->number }}</h2>

{{-- ... invoice details ... --}}

<section class="mt-8">
    <h3 class="text-base font-semibold text-gray-900 dark:text-white">
        Audit History
    </h3>
    <p class="mt-1 text-sm text-gray-500">
        Every action taken on this invoice, most recent first.
    </p>

    <div class="mt-4">
        {{-- Static: shows last 25 entries, with before/after diff --}}
        <x-audited::timeline :subject="$invoice" :show-values="true" />

        {{-- Or live-paginated if Livewire is installed --}}
        {{-- <livewire:audited::timeline :subject="$invoice" :per-page="15" :show-values="true" /> --}}
    </div>
</section>
```

That is the entire integration. The controller has no audit-specific wiring beyond the one manual `log()` call. The view has no query, no loop, no formatting logic. The background job identifies itself as a system actor with one line.

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
