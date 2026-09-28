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

// Scope a query to only records accessible by user, for one owner of the workflow
$query = Order::query();
$service->scopeAccessible($query, $user, 'view');           // scoped Builder
$service->scopeAccessible($query, $user, 'edit', $tenantId); // scoped Builder

// The states behind that scope, and what their emptiness means
$states = $service->categorizedAccessibleStates(Order::class, $user, 'view', $tenantId);
$states->unrestricted;  // the platform administrator: nothing is filtered
$states->isNone();      // no workflow to read: the caller is left with their own rows
$states->free;          // states a role opens: everyone may see them
$states->assigned;      // states that ask to be the owner or an assignee

// Check whether access control is active
$service->isEnabled(); // bool
```

`scopeAccessible` builds an efficient single query. It categorises states into "free" (any matching role/rule) and "assigned" (only via `@assigned`/`@owner`), then applies `whereIn` plus `whereHas` conditions as needed, and it narrows by **state**: the ownership of a single record is still decided per row, by the model's own check.

**The tenant is part of every question.** When a host keeps one workflow per owner — one per scheme, for instance — the lookup finds the workflow only if the caller says which owner: without it the answer is `null` or an empty set, in silence. `categorizedAccessibleStates()` returns an `AccessibleStates`, which says **what it means** when there are no states: the administrator is not filtered at all, while a user with no workflow is left with their own rows.

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

## The other services

| Service | What it answers |
|---|---|
| `TransitionFormService` | The transition between two states, the schema of the fields it asks for, and the values written back onto the record |
| `ConditionEvaluator` | Whether the conditions of a transition hold for a record |
| `ScheduledCheckRunner` | Which checks are due, run over the records of their model |
| `SideEffectExecutor` | What a transition writes: a field, a timestamp, an increment, a cleared value |
| `WorkflowValidationService` | Whether a transition may run, and whether the values it was given respect its rules |
| `RecipientResolver` | Who a notification reaches: a role, a person, the owner of the record, the people assigned, a query, or a class of the host |
| `OwnershipTransfer` | Handing a record over: the new owner, what the previous holder keeps, the record of the handover, the event. See below. |

### OwnershipTransfer

`RoBYCoNTe\FilamentFlow\Services\OwnershipTransfer`

Gives a record to another person. The panel of the assignments is one caller; a console command, a
job or an import can be another, and none of them has to know how the handover is written down.

```php
use RoBYCoNTe\FilamentFlow\Services\OwnershipTransfer;

app(OwnershipTransfer::class)->transfer(
    record: $application,
    toUserId: $successor->id,
    retention: OwnershipTransfer::RETENTION_VIEWER, // none | viewer | secondary
    note: 'Handover to another officer',
    actor: auth()->user(),
);
```

The handover moves the owner column (as configured by `state_access.owner_field`), gives the
previous holder a row of their own when a retention asks for it (`viewer` = still sees it,
`secondary` = stays on the work), records the change in `workflow_owner_changes` and raises
`WorkflowOwnerChanged`. Handing a record to the person who already holds it — or naming a retention
nobody knows — throws `InvalidArgumentException`.

## Behind these doors

The services above are what a host calls. Underneath, the work is split into pieces that answer one
question each: they are public because the engine asks them directly, and they are written down here
so that a reader can follow a rule from the declaration to the answer.

| Seam | The question it answers |
|---|---|
| `EvaluatesAccessRules` | Which rules a state declares, and whether one of them holds |
| `ScopesAccessibleRecords` | How to narrow a query to the records a user may see |
| `AccessibleStatesScope` | The same clauses, on their own: the states a role opens, the ones an assignment opens, the ones an override grants, all held under a refusal. The engine and a host's own list both call it, so the two cannot drift apart |
| `OwnershipHistory`, `RecordOwner`, `UserSummary` | The handovers of a record; the column its owner lives in; a person as the panels and the columns show them |
| `ReadsFieldPermissions` | What a state allows on one field |
| `ReadsCreationAndColumnPermissions` | The permissions of creating a record, and the columns of a list |
| `ResolvesFieldPermissionContext` | The state, the user and the tenant a permission is read for |
| `AppliesValidationRules` | Applying the rules of a transition to the values it was given |
| `EvaluatesValidationValues` | Whether a value respects the rules of its field |
| `ResolvesValidationContext` | The context a rule is evaluated in |
| `FindsNotificationTargets` | Who a notification reaches, before it is sent |
| `DeliversNotifications` | Sending it through the channels it declares |
| `DiffsWorkflowDefinition` | What changed between the stored workflow and the declaration |
| `ProjectsWorkflowRows` | Writing the declaration into the rows, once the plan says what to do |

The same is true of the traits a model uses: `HasDatabaseTransitions` is the door, and the questions
behind it — which states, which guards, which permissions, which history, which notifications — are
answered by `ResolvesWorkflowStates`, `ChecksTransitionGuards`, `ChecksTransitionPermissions`,
`ResolvesWorkflowActions`, `ParsesStateCast`, `LogsTransitionHistory`,
`TriggersTransitionNotifications` and the rest. None of them is meant to be called twice: they each
exist because two callers asked the same question and the answer had to be written once.

## Workflow Definition SDK

`RoBYCoNTe\FilamentFlow\Definition\*` — typed, read-only definition and planning layer (no database access).

| Class | Namespace | Purpose |
|---|---|---|
| `WorkflowDefinition` | `RoBYCoNTe\FilamentFlow\Definition` | Typed workflow definition (states, transitions, metadata) |
| `State` / `StateField` | `RoBYCoNTe\FilamentFlow\Definition` | States and per-state field visibility/mutability |
| `Transition` | `RoBYCoNTe\FilamentFlow\Definition` | State transition or in-state action (guards, effects, rules) |
| `SideEffect` / `ValidationRule` | `RoBYCoNTe\FilamentFlow\Definition` | Transition side effects and validation rules |
| `WorkflowPlanner` | `RoBYCoNTe\FilamentFlow\Definition\Planning` | Diffs a definition against the persisted workflow |
| `WorkflowChangePlan` / `WorkflowChange` | `RoBYCoNTe\FilamentFlow\Definition\Planning` | Plan result and single change |
| `PlanOptions` | `RoBYCoNTe\FilamentFlow\Definition\Planning` | `force()` / `migrate()` / `userId()` opt-ins |
| `MutationClass` | `RoBYCoNTe\FilamentFlow\Definition\Enums` | `Safe` / `Additive` / `Breaking` |
| `WorkflowConflictException` | `RoBYCoNTe\FilamentFlow\Exceptions` | Thrown when a plan has blocking conflicts |
| `WorkflowApplier` | `RoBYCoNTe\FilamentFlow\Definition` | Reconciles a definition with the database (model-agnostic, transactional) |
| `WorkflowSnapshotService` | `RoBYCoNTe\FilamentFlow\Revision` | Stores a versioned workflow snapshot and bumps `schema_version` |

```php
use RoBYCoNTe\FilamentFlow\Definition\Planning\WorkflowPlanner;

$plan = (new WorkflowPlanner)->plan($definition, $existingWorkflow);

if (! $plan->isApplicable()) {
    // $plan->conflicts: list<array{code, message, key}>
}
```
