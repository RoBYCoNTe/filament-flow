<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Filament Flow Configuration
    |--------------------------------------------------------------------------
    |
    | Here you can configure the default behavior of Filament Flow plugin.
    |
    */

    /**
     * Enable or disable the plugin globally.
     */
    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching for workflow lookups, access rules, and field permissions.
    | Uses Laravel's cache system. Set 'enabled' to false to disable all caching.
    |
    */

    'cache' => [
        'enabled' => true,
        'store' => null, // null = default cache store
        'ttl' => 300, // seconds
        'prefix' => 'filament-flow',
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the tenant model for multi-tenancy support.
    | Set to null to disable multi-tenancy.
    |
    */

    /**
     * The tenant model class.
     * Example: App\Models\Company::class, App\Models\Tenant::class
     * Set to null if you don't use multi-tenancy.
     */
    'tenant_model' => null,

    /**
     * The foreign key column name for tenant relationship.
     * This will be used in the workflows table.
     */
    'tenant_foreign_key' => 'tenant_id',

    /*
    |--------------------------------------------------------------------------
    | User Model Configuration
    |--------------------------------------------------------------------------
    */

    /**
     * The user model class for assignments and audit trail.
     * Defaults to Laravel's default user model.
     */
    'user_model' => null, // Will fallback to config('auth.providers.users.model')

    /**
     * The relationship method on the tenant model to retrieve its users.
     * Used by AssigneeSelect to scope available users to the current tenant.
     */
    'tenant_user_relationship' => 'users',

    /*
    |--------------------------------------------------------------------------
    | Form Builder Configuration
    |--------------------------------------------------------------------------
    */

    /**
     * Use the advanced FormBuilderHelper for building forms.
     * Set to false to use basic form building in HasWorkflowCreation trait.
     */
    'use_form_builder_helper' => true,

    /*
    |--------------------------------------------------------------------------
    | Transition History Notes Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how transition notes are captured and stored in history.
    |
    */

    /**
     * Enable or disable logging of transition notes to history.
     * When enabled, notes can be captured from:
     * 1. Transition class getHistoryNotes() method (highest priority)
     * 2. Form field named 'transition_notes' (convention)
     */
    'log_transition_notes' => true,

    /**
     * Default form field name to use for transition notes.
     * If a transition form contains this field, its value will be saved to history.
     * Set to null to disable automatic field detection.
     */
    'transition_notes_field' => 'transition_notes',

    /*
    |--------------------------------------------------------------------------
    | State Access Control Configuration
    |--------------------------------------------------------------------------
    |
    | Configure state-based access control for your workflows.
    | This allows you to restrict who can view, edit, or transition records
    | based on their current state.
    |
    */

    'state_access' => [
        /**
         * Enable or disable state-based access control.
         * When disabled, all access checks will return true.
         */
        'enabled' => true,

        /**
         * Automatically enforce access control on transitionTo() calls.
         * When enabled, unauthorized transitions will throw UnauthorizedTransitionException.
         * When disabled, you must manually check canBeTransitionedBy() before calling transitionTo().
         *
         * Use forceTransitionTo() to bypass this check for system-level operations.
         */
        'enforce_on_transition' => true,

        /**
         * Default access rules when no state-specific rules are defined.
         *
         * Available tokens:
         * - '*'                    : Everyone (including guests)
         * - '@authenticated'       : Any authenticated user
         * - '@assigned'            : Any user assigned to the record
         * - '@assigned:type'       : User assigned with specific type (e.g., @assigned:primary)
         * - '@owner'               : Record owner (uses owner_field config)
         * - 'role:name'            : User with specific role
         * - 'role:name1,name2'     : User with any of the specified roles
         * - 'permission:name'      : User with specific permission
         *
         * Note: 'create' rules only apply to the initial state and determine
         * who can create new records. @owner and @assigned don't apply to create
         * since the record doesn't exist yet.
         */
        'defaults' => [
            'create' => ['@authenticated'],
            'view' => ['@authenticated'],
            'edit' => ['@authenticated'],
            'transition' => ['@authenticated'],
        ],

        /**
         * Roles that bypass all access checks (super admin).
         * Users with any of these roles will have full access to all records.
         */
        'super_admin_roles' => ['super_admin'],

        /**
         * Custom role resolver class.
         * Must implement RoBYCoNTe\FilamentFlow\Contracts\RoleResolver.
         * Set to null to use the default resolver (supports Spatie Permission).
         */
        'role_resolver' => null,

        /**
         * Custom permission resolver class.
         * Must implement RoBYCoNTe\FilamentFlow\Contracts\PermissionResolver.
         * Set to null to use the default resolver (supports Spatie Permission & Laravel Gates).
         */
        'permission_resolver' => null,

        /**
         * Field name used to identify record ownership.
         * The @owner token will check if this field matches the user's ID.
         */
        'owner_field' => 'user_id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Workflow Notifications Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the notification system for workflow events.
    | Notifications can be triggered on state transitions, state entry/exit,
    | assignments, and field changes.
    |
    */

    'notifications' => [
        /**
         * Enable or disable the notification system globally.
         * When disabled, no notifications will be dispatched.
         */
        'enabled' => true,

        /**
         * Default notification channel when none is specified.
         * Available: database, mail
         */
        'default_channel' => 'database',

        /**
         * Queue connection to use for async notifications.
         * Set to null to use the default queue connection.
         */
        'queue_connection' => null,

        /**
         * Queue name for notification jobs.
         * Set to null to use the default queue.
         */
        'queue_name' => null,

        /**
         * Default delay in minutes for delayed notifications.
         * Can be overridden per notification configuration.
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
         * Logs are stored in the workflow_notification_logs table.
         */
        'logging_enabled' => true,

        /**
         * Channels configuration.
         * Each channel can have its own settings.
         */
        'channels' => [
            'database' => [
                'enabled' => true,
            ],

            'mail' => [
                'enabled' => true,
                // Default from address (uses config('mail.from') if null)
                'from_address' => null,
                'from_name' => null,
            ],
        ],

        /**
         * Template rendering engine.
         * Available: plain ({{variable}}), blade, mustache
         */
        'default_template_engine' => 'plain',

        /**
         * Custom recipient resolver class.
         * Must implement RoBYCoNTe\FilamentFlow\Contracts\RecipientResolverInterface.
         * Set to null to use the default resolver.
         */
        'recipient_resolver' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduled Checks Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the scheduling engine for time-based workflow checks.
    | The command workflow:process-schedules is auto-registered in the
    | Laravel scheduler.
    |
    */

    'scheduling' => [
        /**
         * Enable or disable the scheduled checks system.
         * When disabled, the command will not be registered in the scheduler.
         */
        'enabled' => true,

        /**
         * How often to run the scheduled checks command.
         * Uses Laravel scheduler method names: everyMinute, everyFiveMinutes,
         * everyTenMinutes, everyFifteenMinutes, everyThirtyMinutes, hourly, daily.
         */
        'frequency' => 'everyFiveMinutes',
    ],
];
