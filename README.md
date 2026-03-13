# Audited

[![Latest Version on Packagist](https://img.shields.io/packagist/v/williamug/audited.svg?style=flat-square)](https://packagist.org/packages/williamug/audited)
[![tests](https://github.com/williamug/audited/actions/workflows/run-tests.yml/badge.svg)](https://github.com/williamug/audited/actions/workflows/run-tests.yml)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/williamug/audited/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/williamug/audited/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/williamug/audited.svg?style=flat-square)](https://packagist.org/packages/williamug/audited)


A simple, robust audit logging package for Laravel applications. Drop one trait onto a model and every create, update, and delete is automatically recorded. Authentication events, manual logging, scheduled pruning, and a configurable schema are included out of the box.

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
- [Authentication Event Logging](#authentication-event-logging)
- [The AuditAction Enum](#the-auditaction-enum)
- [Querying Audit Logs](#querying-audit-logs)
- [Extending the AuditLog Model](#extending-the-auditlog-model)
- [Multitenancy](#multitenancy)
  - [Stamping Tenant Context on Every Log Entry](#stamping-tenant-context-on-every-log-entry)
  - [Scoping Queries per Tenant](#scoping-queries-per-tenant)
  - [Branch-level Isolation](#branch-level-isolation)
  - [Full Example](#full-example)
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
| Laravel | `^10.0`, `^11.0`, or `^12.0` |

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

This publishes `config/audit.php` and copies a timestamped migration into `database/migrations/`.

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

#### Using a custom action string.

The `$action` parameter accepts both the `AuditAction` enum and a plain string. Use a plain string for any domain-specific action that is not in the enum — there is no need to extend it.

```php
ActivityLogService::log(
  'transfer',
  'Ministers',
  'Transferred Rev. John to St. Peters Parish'
);

ActivityLogService::log(
  'ordination',
  'Ministers',
  'Rev. James ordained as Deacon.'
);

ActivityLogService::log(
  'suspension',
  'Staff',
  'Staff member suspended pending investigation.'
);

ActivityLogService::log(
  'reconcile',
  'Accounts',
  'Monthly accounts reconciled.'
);
```

Custom action strings are stored verbatim in the `action` column. Because they are not cases of `AuditAction`, calling `AuditAction::tryFrom($log->action)` returns `null` for them. Handle this in your UI layer so badge rendering degrades gracefully:

```blade
@php
  $action = \Williamug\Audited\Enums\AuditAction::tryFrom($log->action);
@endphp

{{-- Falls back to a neutral badge and a humanised label for custom actions --}}
<span class="px-2 py-1 rounded text-xs font-medium
    {{ $action?->badgeColor() ?? 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400' }}">
    {{ $action?->label() ?? ucfirst(str_replace('_', ' ', $log->action)) }}
</span>
```

You can also retrieve all distinct custom actions from the database to build dynamic filter dropdowns:

```php
// All actions that exist in the log but are not in the AuditAction enum
$standardValues = array_column(AuditAction::cases(), 'value');

$customActions = AuditLog::query()
    ->select('action')
    ->distinct()
    ->whereNotIn('action', $standardValues)
    ->pluck('action');
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

## Authentication Event Logging

When `log_auth_events` is `true` in the config (the default), the package automatically listens for Laravel's `Login`, `Logout`, and `Failed` auth events and writes a log entry for each. No observer registration or extra code is required.

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

## The AuditAction Enum

`Williamug\Audited\Enums\AuditAction` provides a standard set of actions that covers most applications. Each case has a `label()` method for display text and a `badgeColor()` method for Tailwind CSS badge classes.

```php
use Williamug\Audited\Enums\AuditAction;

// All available cases
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

// Human-readable label
AuditAction::PasswordChange->label();     // 'Password Change'

// Tailwind CSS badge classes (with dark mode)
AuditAction::Delete->badgeColor();
// 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
```

Use these directly in your Blade views to render consistent action badges:

```blade
@foreach ($logs as $log)
    @php
      $action = Williamug\Audited\Enums\AuditAction::tryFrom($log->action);
    @endphp

    <span class="px-2 py-1 rounded text-xs font-medium {{ $action?->badgeColor() }}">
        {{ $action?->label() ?? $log->action }}
    </span>
@endforeach
```

---

## Querying Audit Logs

Query the `AuditLog` model directly. The `old_values` and `new_values` columns are automatically cast to arrays.

```php
use Williamug\Audited\Models\AuditLog;

// All logs for a given module, most recent first
AuditLog::query()
    ->where('module', 'Billing')
    ->latest()
    ->paginate(20);

// All delete actions in the past 30 days
AuditLog::query()
    ->where('action', 'delete')
    ->where('created_at', '>=', now()->subDays(30))
    ->get();

// All actions performed by a specific user
AuditLog::query()
    ->where('user_id', $user->id)
    ->latest()
    ->get();

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

The package supports single-database multitenancy with multiple branches out of the box. The column names are entirely up to your application — `company_id`, `tenant_id`, `business_id`, `facility_id`, `branch_id`, `company_branch_id` — whatever your data model uses.

There are two sides to multitenancy: **writing** (stamping the tenant onto each log entry) and **reading** (ensuring each tenant only queries their own logs). Both are handled on your custom model.

### Stamping Tenant Context on Every Log Entry

Override `extraColumns()` on your custom model. The package calls this method on every write and merges the returned array into the log entry automatically. No call sites need to change.

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

```bash
php artisan make:migration add_tenant_columns_to_audit_logs_table
```

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

Add a global scope to your custom model. Laravel applies it automatically to every query, so tenant A can never read tenant B's logs.

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

Now this query:

```php
AuditLog::where('module', 'Billing')->latest()->paginate(20);
```

automatically becomes:

```sql
SELECT * FROM audit_logs WHERE company_id = 5 AND module = 'Billing' ORDER BY created_at DESC
```

### Branch-level Isolation

If branches are also isolated from each other, add the branch constraint to the scope. You can make it conditional — for example, head-office users see all branches while branch users only see their own:

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

A complete custom model for a multi-branch company:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Williamug\Audited\Models\AuditLog as BaseAuditLog;

class AuditLog extends BaseAuditLog
{
    // READ — scope every query to the current tenant and branch
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

    // WRITE — stamp every log entry with tenant and branch
    protected static function extraColumns(): array
    {
        return [
            'company_id' => auth()->user()?->company_id,
            'branch_id'  => auth()->user()?->branch_id,
        ];
    }

    // Optional relationships
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

Point the config to this model and the package handles the rest:

```php
// config/audit.php
'model' => \App\Models\AuditLog::class,
```

---

## Pruning Old Logs

The `audit:prune` command deletes log entries older than the configured retention period.

```bash
# Uses prune_after_months from config (default: 3)
php artisan audit:prune

# Override the retention period for a one-off run
php artisan audit:prune --months=6
```

The command is **automatically scheduled quarterly** by the package's service provider. You do not need to add it to your application's schedule.

To disable automatic pruning, set `prune_after_months` to `null` in the config:

```php
// config/audit.php
'prune_after_months' => null,
```

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
    'api_key',          // add your own
    'stripe_secret',    // add your own
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
            'action' => 'create',
            'module' => 'Billing',
            'user_id' => $user->id,
        ]);
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

To suppress audit logging during unrelated tests that create models, you can temporarily disable the `Auditable` observer:

```php
// Disable for a specific test
Model::withoutEvents(function () {
    Invoice::factory()->count(10)->create();
});
```

---

## License

The MIT License. See [LICENSE](LICENSE) for details.
