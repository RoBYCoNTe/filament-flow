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
    'enabled' => true,
    'store'   => null,           // null = use the default Laravel cache store
    'ttl'     => 300,            // seconds to cache workflow lookups and access rules
    'prefix'  => 'filament-flow', // cache key prefix
],
```

Caching covers workflow lookups, access rule evaluation, and field permission maps. Set `enabled` to `false` to disable all caching (useful during development). Set `store` to any named cache driver from your `config/cache.php` (e.g. `'redis'`).

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

## Form Builder Configuration

```php
/**
 * Use the advanced FormBuilderHelper for building forms.
 * Set to false to use basic form building in HasWorkflowCreation trait.
 */
'use_form_builder_helper' => true,
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
     * Custom recipient resolver class.
     * Must implement RoBYCoNTe\FilamentFlow\Contracts\RecipientResolverInterface.
     * Set to null to use the default resolver.
     */
    'recipient_resolver' => null,
],
```

**`logging_enabled`** — When `true`, every notification dispatch attempt (sent, failed, or skipped) is recorded in `workflow_notification_logs`. Disable for high-volume workflows where audit logging is not required.

**`retry_attempts` / `retry_backoff`** — Configure job-level retries for failed async notification jobs. `retry_backoff` is in seconds.

**`channels`** — Enable or disable individual channels globally. Per-notification channels can still be toggled via `workflow_notification_channels.is_active`.

**`recipient_resolver`** — Provide a custom class to override how recipients are resolved. Useful when your user model or recipient logic differs from the defaults.

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
