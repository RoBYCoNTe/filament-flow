# Programmatic Services

All services are bound in the Laravel service container and can be resolved via `app()` or constructor injection. Use them in controllers, queued jobs, Artisan commands, or anywhere outside Filament's UI layer.

## WorkflowStateAccessService

`RoBYCoNTe\FilamentFlow\Services\WorkflowStateAccessService`

Evaluates state-based access rules against a user. Respects Code-First rules (PHP State classes implementing `HasAccessRules`), Database rules, and falls back to the `state_access.defaults` config.

```php
use RoBYCoNTe\FilamentFlow\Services\WorkflowStateAccessService;

$service = app(WorkflowStateAccessService::class);

// Basic record-level checks (pass null for $user to use auth()->user())
$service->canView($order, $user);       // bool
$service->canEdit($order, $user);       // bool
$service->canTransition($order, $user); // bool — any transition allowed

// Check transition to a specific target state
$service->canTransition($order, $user, ProcessingState::class); // bool

// Check if a user can create a new record (checks the initial state's rules)
$service->canCreate(Order::class, $user); // bool

// Scope a query to only records accessible by user
$query = Order::query();
$service->scopeAccessible($query, $user, 'view');  // scoped Builder
$service->scopeAccessible($query, $user, 'edit');  // scoped Builder

// Check whether access control is active
$service->isEnabled(); // bool
```

`scopeAccessible` builds an efficient single query. It categorises states into "free" (any matching role/rule) and "assigned" (only via `@assigned`/`@owner`), then applies `whereIn` plus `whereHas` conditions as needed. Super admins bypass all filters.

## NotificationService

`RoBYCoNTe\FilamentFlow\Services\NotificationService`

Orchestrates the notification system. Supports both Database-Driven (configured via `WorkflowNotification` model) and Code-First (defined in State/Transition PHP classes) approaches.

```php
use RoBYCoNTe\FilamentFlow\Services\NotificationService;

$service = app(NotificationService::class);

// Trigger all notifications for a completed transition
// Handles: transition class notifications, state enter/exit notifications,
// and database-configured notifications automatically.
$service->triggerForTransition(
    record: $order,
    fromState: PendingState::class,
    toState: ProcessingState::class,
    transitionData: ['reason' => 'Payment confirmed'],
    transitionInstance: $transitionObject, // optional, for HasTransitionNotifications
);

// Trigger for a specific state entry (e.g., from a job)
$service->triggerForStateEntry($order, ProcessingState::class);

// Trigger for an assignment event
$service->triggerForAssignment($order, $user, 'primary');

// Trigger a specific notification config by its database ID
$service->triggerById($notificationId, $order, ['trigger' => 'manual']);

// Trigger for a field change
$service->triggerForFieldChange($order, 'priority', 'normal', 'urgent');

// Dispatch code-first notification builders directly
$service->dispatchCodeFirstNotifications([$builder], $order, $context);
```

`triggerForTransition` is the primary entry point. It fires in this order: transition class notifications, state exit notifications from the from-state, state enter notifications for the to-state, then database-configured transition/state-entry/state-exit notifications.

## WorkflowCreationService

`RoBYCoNTe\FilamentFlow\Services\WorkflowCreationService`

Creates model records with workflow initialization. Handles permission checks, sets the initial state, persists the owner field, and optionally auto-assigns the creator.

```php
use RoBYCoNTe\FilamentFlow\Services\WorkflowCreationService;
use RoBYCoNTe\FilamentFlow\Exceptions\WorkflowNotFoundException;
use RoBYCoNTe\FilamentFlow\Exceptions\InitialStateNotFoundException;
use RoBYCoNTe\FilamentFlow\Exceptions\UnauthorizedTransitionException;

$service = app(WorkflowCreationService::class);

// Check permission before creating
if (! $service->canCreate(Order::class, $user)) {
    abort(403);
}

// Create record with workflow initialization
// - Sets the state column to the initial state
// - Sets the owner field (if fillable and not already provided)
// - Auto-assigns creator if workflow creation_policy requires it
try {
    $order = $service->createRecord(Order::class, [
        'customer_id' => $customerId,
        'amount'      => 500,
    ], $user);
} catch (WorkflowNotFoundException $e) {
    // No active workflow found for Order::class
} catch (InitialStateNotFoundException $e) {
    // Workflow exists but has no state marked as initial
} catch (UnauthorizedTransitionException $e) {
    // User does not pass the initial state's create access rules
}
```

The method wraps the creation in a database transaction and rolls back on failure.

## WorkflowFieldPermissionsService

`RoBYCoNTe\FilamentFlow\Services\WorkflowFieldPermissionsService`

Resolves field-level visibility and mutability for a record's current state. Takes role overrides into account when a `$user` is provided.

```php
use RoBYCoNTe\FilamentFlow\Services\WorkflowFieldPermissionsService;

$service = app(WorkflowFieldPermissionsService::class);

// Full permission map for a record in its current state
$permissions = $service->getFieldPermissions($order, $user);
// Returns:
// [
//     'amount'      => ['visible' => true,  'readonly' => false, 'locked' => false, 'required' => true,  'validation' => [...]],
//     'internal_note' => ['visible' => false, 'readonly' => false, 'locked' => true,  'required' => false, 'validation' => []],
// ]

// Convenience helpers
$readonly = $service->getReadonlyFields($order, $user);  // ['amount', 'reference']
$hidden   = $service->getHiddenFields($order, $user);    // ['internal_note']

// For creation forms (no record yet — uses the initial state's field config)
$createPerms = $service->getCreationFieldPermissions(Order::class, $user);

// For table columns — visible if visible in at least one state for the user's roles
$tablePerms = $service->getTableColumnPermissions(Order::class, $user);
// Returns: ['field_name' => ['visible' => true|false]]
```

Results are cached per workflow + state + user roles hash. The cache TTL is capped at 60 seconds to keep field-permission responses fresh.

## StateService

`RoBYCoNTe\FilamentFlow\Services\StateService`

Retrieves state metadata. Merges PHP State class metadata with database-configured states, with PHP taking precedence.

```php
use RoBYCoNTe\FilamentFlow\Services\StateService;

$service = app(StateService::class);

// All states for a model (PHP + database merged, keyed by state name/class)
$states = $service->getAllStatesForModel(Order::class, 'state');
// Returns: ['App\States\Order\PendingState' => 'Pending', 'db_only_state' => 'Legacy State']

// Metadata for a specific state
$meta = $service->getStateMetadata(Order::class, 'pending', 'state');
// Returns:
// [
//     'label'       => 'Pending',
//     'color'       => 'warning',
//     'icon'        => 'heroicon-o-clock',
//     'description' => 'Awaiting payment.',
//     'is_initial'  => true,
//     'is_final'    => false,
//     'sort_order'  => 1,
// ]

// Initial state name for a model
$initial = $service->getInitialState(Order::class, 'state'); // 'pending'
```

`getAllStatesForModel` only includes database-only states (those without a matching PHP class). States that have a `class_name` pointing to a real PHP class are retrieved through Spatie's `getStatesLabel()` instead, so metadata always comes from the most authoritative source.
