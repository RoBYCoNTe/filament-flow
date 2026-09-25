# Configuration Options

Publish the configuration file with:

```bash
php artisan vendor:publish --tag="filament-flow-config"
```

This creates `config/filament-flow.php`.

## Global Settings

```php
// Enable or disable the plugin globally
'enabled' => true,
```

## Multi-Tenancy Configuration

```php
/**
 * The tenant model class for multi-tenancy support.
 * Set to null to disable multi-tenancy.
 *
 * Example: App\Models\Company::class, App\Models\Tenant::class
 */
'tenant_model' => null,

/**
 * The foreign key column name for tenant relationship.
 * This will be used in the workflows table.
 */
'tenant_foreign_key' => 'tenant_id',
```

**Use Case:** If your application has multiple tenants (e.g., companies, organizations), you can configure workflows per tenant. Each tenant can have their own workflow definitions in the database.

## User Model Configuration

```php
/**
 * The user model class for assignments and audit trail.
 * Defaults to Laravel's default user model.
 */
'user_model' => null, // Will fallback to config('auth.providers.users.model')
```

**Use Case:** Specify a custom user model if you're not using Laravel's default `App\Models\User`.

## Cache

```php
'cache' => [
    'enabled'    => true,
    'store'      => null,             // null = use the default Laravel cache store
    'ttl'        => 300,              // legacy TTL (prefer safety_ttl for event-driven invalidation)
    'safety_ttl' => 86400,            // 24h safety net — cache lives until invalidated by observers
    'prefix'     => 'filament-flow',  // cache key prefix
],
```

| Option | Default | Description |
|---|---|---|
| `enabled` | `true` | Enable or disable all caching. Set to `false` during local development. |
| `store` | `null` | Cache store name from `config/cache.php`. `null` = default store. |
| `ttl` | `300` | Legacy TTL in seconds. Kept for backward compatibility. |
| `safety_ttl` | `86400` | Safety-net TTL (24 hours). Cache entries are invalidated by observers long before this expires. |
| `prefix` | `filament-flow` | Prefix prepended to all cache keys. |

### Store Support

| Store | Tag Support | Invalidation |
|---|---|---|
| **Redis** (recommended) | Native `Cache::tags()` | Atomic, single operation |
| **Memcached** | Key-registry fallback | Iterates registry, forgets per key |
| **File / Database** | Key-registry fallback | Iterates registry, forgets per key |

Redis is recommended for production because it supports native cache tags, enabling granular and atomic invalidation in a single call.

## Transition History Notes

```php
/**
 * Enable or disable logging of transition notes to history.
 */
'log_transition_notes' => true,

/**
 * Default form field name to use for transition notes.
 * If a transition form contains this field, its value is saved to history.
 * Set to null to disable automatic field detection.
 */
'transition_notes_field' => 'transition_notes',
```

When `log_transition_notes` is `true`, the value of the field named by `transition_notes_field` in a transition form is persisted to `workflow_state_transitions.notes`.

## Field Changes

```php
/**
 * The delta of the values a transition moved, recorded beside the form data so the history
 * can show what changed instead of the whole form.
 *
 * `attribute` names the column (or columns) that hold the values: a map is opened down to its
 * leaves, a list stays whole. `ignore` leaves paths out, with `*` wildcards.
 */
'field_changes' => [
    'enabled' => true,
    'attribute' => 'form_data',
    'payload' => false,
    'ignore' => [],
],
```

With `enabled`, every logged transition computes the difference between the record as it stood
before the transition and the record as it stands after it, and stores it in
`workflow_transition_metadata.field_changes` as `['path' => ['from' => mixed, 'to' => mixed]]`.
The comparison runs on paths: a map is opened to its leaves (`intervention.location.province`),
while a list — the rows of a repeater, the files of a document set — stays whole, because a row
is the unit a person changed. A number written `5000` and `5000.00` is not a change, and neither
is a missing value against an empty one.

Three answers, and they are not the same thing:

| `field_changes` | Meaning |
|---|---|
| `['path' => ['from' => …, 'to' => …]]` | The transition moved these paths. |
| `[]` | The comparison ran, and **nothing moved**: a save that changed nothing says so. |
| `null` | Nobody compared: the row predates this setting, or `enabled` is off. The history can only show the values the transition carried. |

`attribute` may name more than one column (`['form_data', 'settings']`). A column that holds one
scalar counts as a field of its own, named as the column is. Paths listed in `ignore` are left
out of the delta; a transition that moves nothing records no metadata at all.

`payload` decides whether **what a transition was given** counts as the delta it wrote. A host
that writes the values *after* the transition — so a refused transition leaves nothing of the
attempt behind — holds the old values when the log is written, and the payload (keyed by record
path) is then the only place the delta exists: set it to `true`. Leave it `false` when the
payload is keyed by form field name and the engine maps it onto the record through the declared
transition fields.

And a host that writes the values **before** the transition has to say what the record held a
moment earlier: the engine can only compare what it sees, and by then the record already carries
the new values (nor does the payload help — a whole form equals itself).

```php
$application->withFieldValuesBefore(['form_data' => $application->form_data]);

$application->saveFormData($state, $user);
$application->transitionTo($state, $state);
```

The values are consumed by the next logged transition, and forgotten with the rest of the
transition state.

## Form Builder Configuration

```php
/**
 * Use the advanced FormBuilderHelper for building forms.
 * Set to false to use basic form building in HasWorkflowCreation trait.
 */
```

**Use Case:** The `FormBuilderHelper` provides advanced form building capabilities for database-configured workflows. Set to `false` if you want simpler form generation.

## State Access Control

```php
'state_access' => [
    /**
     * Enable or disable state-based access control.
     * When disabled, all access checks return true.
     */
    'enabled' => true,

    /**
     * Automatically enforce access control on transitionTo() calls.
     * When enabled, unauthorized transitions throw UnauthorizedTransitionException.
     * When disabled, you must manually check canBeTransitionedBy() before calling transitionTo().
     */
    'enforce_on_transition' => true,

    /**
     * Default access rules when no state-specific rules are defined.
     * 'create' rules apply to the initial state and control who can create new records.
     */
    'defaults' => [
        'create' => ['@authenticated'],
        'view' => ['@authenticated'],
        'edit' => ['@authenticated'],
        'transition' => ['@authenticated'],
    ],

    /**
     * Roles that bypass all access checks (super admin).
     * Users with any of these roles have full access to all records.
     */
    'super_admin_roles' => ['super_admin'],

    /**
     * Custom role resolver class.
     * Must implement RoBYCoNTe\FilamentFlow\Contracts\RoleResolver.
     */
    'role_resolver' => null,

    /**
     * Custom permission resolver class.
     * Must implement RoBYCoNTe\FilamentFlow\Contracts\PermissionResolver.
     */
    'permission_resolver' => null,

    /**
     * Field name used to identify record ownership.
     * The @owner token checks if this field matches the user's ID.
     */
    'owner_field' => 'user_id',
],
```

## Notifications

```php
'notifications' => [
    /**
     * Enable or disable the notification system globally.
     */
    'enabled' => true,

    /**
     * Default notification channel when none is specified.
     */
    'default_channel' => 'database',

    /**
     * Queue connection for async notifications.
     */
    'queue_connection' => null,

    /**
     * Queue name for notification jobs.
     */
    'queue_name' => null,

    /**
     * Default delay in minutes for delayed notifications.
     */
    'default_delay_minutes' => 0,

    /**
     * Number of retry attempts for failed notification jobs.
     */
    'retry_attempts' => 3,

    /**
     * Backoff time in seconds between retry attempts.
     */
    'retry_backoff' => 60,

    /**
     * Enable logging of all notification dispatches.
     */
    'logging_enabled' => true,

    /**
     * Channel-specific configuration.
     */
    'channels' => [
        'database' => [
            'enabled' => true,
        ],
        'mail' => [
            'enabled' => true,
            'from_address' => null,
            'from_name' => null,
        ],
    ],

    /**
     * Default template rendering engine.
     */
    'default_template_engine' => 'plain',

    /**
     * A custom recipient resolver: the name of a class extending
     * RoBYCoNTe\FilamentFlow\Services\RecipientResolver.
     * Null uses the resolver of the package.
     */
    'recipient_resolver' => null,
],
```

**`logging_enabled`** — When `true`, every notification dispatch attempt (sent, failed, or skipped) is recorded in `workflow_notification_logs`. Disable for high-volume workflows where audit logging is not required.

**`retry_attempts` / `retry_backoff`** — Configure job-level retries for failed async notification jobs. `retry_backoff` is in seconds.

**`channels`** — Enable or disable individual channels globally. Per-notification channels can still be toggled via `workflow_notification_channels.is_active`.

**`recipient_resolver`** — The name of a class extending `RoBYCoNTe\FilamentFlow\Services\RecipientResolver`, to change how recipients are resolved. `null` uses the resolver of the package. A class that does not extend it raises rather than being silently ignored — which is what a setting nobody reads deserves.

## Scheduling

```php
'scheduling' => [
    'enabled'   => env('FILAMENT_FLOW_SCHEDULING_ENABLED', true),
    'frequency' => 'everyFiveMinutes', // everyMinute, everyFiveMinutes, everyTenMinutes,
                                       // everyFifteenMinutes, everyThirtyMinutes, hourly, daily
],
```

Controls the `workflow:process-schedules` Artisan command that evaluates `workflow_scheduled_checks`. When `enabled` is `true`, the command is registered automatically in the Laravel scheduler at the configured frequency.

Set `FILAMENT_FLOW_SCHEDULING_ENABLED=false` in production environments where you prefer to run the command manually or via a dedicated queue worker.
