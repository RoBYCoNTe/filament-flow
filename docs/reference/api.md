# API Reference

## Components

| Component | Namespace | Description |
|---|---|---|
| `StateSelect` | `RoBYCoNTe\FilamentFlow\Forms\Components` | Dropdown select for states |
| `StateRadio` | `RoBYCoNTe\FilamentFlow\Forms\Components` | Radio button group for states |
| `StateToggleButtons` | `RoBYCoNTe\FilamentFlow\Forms\Components` | Toggle button group for states |
| `StateSelectColumn` | `RoBYCoNTe\FilamentFlow\Tables\Columns` | Interactive table column (with sorting) |
| `StateColumn` | `RoBYCoNTe\FilamentFlow\Tables\Columns` | Display-only table column (with sorting) |
| `StateExportColumn` | `RoBYCoNTe\FilamentFlow\Tables\Columns` | Export column with state label formatting |
| `StateSelectFilter` | `RoBYCoNTe\FilamentFlow\Tables\Filters` | Table filter for states |
| `StateGroup` | `RoBYCoNTe\FilamentFlow\Tables\Grouping` | Group table records by state |

## Infolist components

| Component | Namespace | Description |
|---|---|---|
| `AssignmentSummaryEntry` | `RoBYCoNTe\FilamentFlow\Infolists\Components` | Who holds the record, with the kind of each assignment |
| `StateBadge` | `RoBYCoNTe\FilamentFlow\Infolists\Components` | The label, colour and icon of the state of a record |
| `TransitionTimeline` | `RoBYCoNTe\FilamentFlow\Infolists\Components` | When the record moved, from which state to which, and by whom |

The three read the workflow, so they need the tenant of the record: without it a workflow scoped
to an owner is not found, and they draw themselves empty.

## Actions

| Action | Namespace | Description |
|---|---|---|
| `StateAction` | `RoBYCoNTe\FilamentFlow\Actions` | Single record state transition |
| `StateActionGroup` | `RoBYCoNTe\FilamentFlow\Actions` | Auto-generated action group |
| `StateBulkAction` | `RoBYCoNTe\FilamentFlow\Actions` | Bulk state transition |
| `StateBulkActionGroup` | `RoBYCoNTe\FilamentFlow\Actions` | The bulk actions of a table, one per transition |

## Utilities

| Utility | Namespace | Description |
|---|---|---|
| `StateTabs` | `RoBYCoNTe\FilamentFlow` | Tabs that group the records by state, with a badge and a query each |
| `FilamentFlow` | `RoBYCoNTe\FilamentFlow` | The entry point of the package: the workflow of a model, and the services, resolved by name |

## Interfaces

| Interface | Methods | Description |
|---|---|---|
| `HasLabel` | `getLabel(): string` | Display name for the state — **Filament's** `Filament\Support\Contracts\HasLabel` |
| `HasIcon` | `getIcon(): string` | Icon for the state — `Filament\Support\Contracts\HasIcon` |
| `HasColor` | `getColor(): string\|array` | Colour for the state — `Filament\Support\Contracts\HasColor` |
| `HasDescription` | `getDescription(): string` | Description of the state — `Filament\Support\Contracts\HasDescription` |
| `HasStateMetadata` | Combines all metadata interfaces | Full state metadata contract |
| `HasStateSortOrder` | `getSortOrder(): int` | Custom sort position for state |
| `HasAccessRules` | `getCreateAccessRules()`, `getViewAccessRules()`, `getEditAccessRules()`, `getTransitionAccessRules()` | Code-First state access rules |
| `RoleResolver` | `getRoles()`, `hasAnyRole()`, `isSuperAdmin()` | Role resolution for access control |
| `PermissionResolver` | `hasPermission()` | Permission resolution for access control |
| `HasStateNotifications` | `onEnterNotifications()`, `onExitNotifications()` | Notifications on state enter/exit |
| `HasTransitionNotifications` | `notifications()` | Notifications on transition |
| `HasStateAction` | `withTransitionClass()`, `getTransitionClass()` | A class that is a state action: the state it works on, the transition it runs |
| `HasStateAttributes` | `getAttribute()`, `attribute()` | Whatever carries a state attribute: which column holds it |
| `HasFieldLabels` | `getFieldLabels()` | Labels a host gives to the fields of a transition |
| `FieldListProviderInterface` | `getFields(): array` | Which fields a formula may name, for a context |
| `FormulaCompletionProvider` | `getCompletions(): CompletionPayload` | The variables and functions of one formula scope |
| `FormulaConditionProvider` | — | Evaluates the formulas of a condition for the host |

## Traits

| Trait | Namespace | Description |
|---|---|---|
| `HasStateMetadata` | `RoBYCoNTe\FilamentFlow\Concerns` | Provides metadata methods for states |
| `HasStateSortOrder` | `RoBYCoNTe\FilamentFlow\Concerns` | Provides sort order mapping for states |
| `HasStateSorting` | `RoBYCoNTe\FilamentFlow\Concerns` | Enables custom sorting in table columns |
| `HasStateOptions` | `RoBYCoNTe\FilamentFlow\Concerns` | Provides state options for form/table fields |
| `HasDatabaseTransitions` | `RoBYCoNTe\FilamentFlow\Concerns` | Enables database-configured transitions |
| `HasFlexibleStates` | `RoBYCoNTe\FilamentFlow\Concerns` | Supports both PHP and database-only states |
| `HasStateAccess` | `RoBYCoNTe\FilamentFlow\Concerns` | Enables state-based access control for models |
| `HasStateActions` | `RoBYCoNTe\FilamentFlow\Concerns` | Common functionality for state actions |
| `HasStateAttributes` | `RoBYCoNTe\FilamentFlow\Concerns` | Manages state attribute mapping |
| `HasTransitionForm` | `RoBYCoNTe\FilamentFlow\Concerns` | Handles transition form generation and validation |
| `HasWorkflowCreation` | `RoBYCoNTe\FilamentFlow\Concerns` | Database workflow creation helpers |
| `HasWorkflowAssignments` | `RoBYCoNTe\FilamentFlow\Concerns` | Workflow assignment management |
| `HasWorkflowForm` | `RoBYCoNTe\FilamentFlow\Concerns` | Form building for workflow configuration |
| `ResolvesActionAttributes` | `RoBYCoNTe\FilamentFlow\Concerns` | Resolves state attributes for actions |
| `ChecksTransitionGuards` | `RoBYCoNTe\FilamentFlow\Concerns` | The guards and the conditions of a transition |
| `ChecksTransitionPermissions` | `RoBYCoNTe\FilamentFlow\Concerns` | Who may take a transition |
| `LogsTransitionHistory` | `RoBYCoNTe\FilamentFlow\Concerns` | The history of the transitions of a record |
| `ParsesStateCast` | `RoBYCoNTe\FilamentFlow\Concerns` | Reading the state out of the model's cast |
| `ResolvesUserModel` | `RoBYCoNTe\FilamentFlow\Concerns` | Which model is the user of this application |
| `ResolvesWorkflowActions` | `RoBYCoNTe\FilamentFlow\Concerns` | The actions a record offers |
| `ResolvesWorkflowStates` | `RoBYCoNTe\FilamentFlow\Concerns` | The states of a record |
| `TriggersTransitionNotifications` | `RoBYCoNTe\FilamentFlow\Concerns` | Notifications raised by a transition |
| `HasRelationManagerForm` | `RoBYCoNTe\FilamentFlow\Concerns` | The form of a relation manager |
| `HasWorkflowTable` | `RoBYCoNTe\FilamentFlow\Concerns` | The table of a workflow resource |

## Models (Database-Driven Workflows)

| Model | Namespace | Description |
|---|---|---|
| `Workflow` | `RoBYCoNTe\FilamentFlow\Models` | Workflow definitions |
| `WorkflowState` | `RoBYCoNTe\FilamentFlow\Models` | State definitions (PHP or database-only) |
| `WorkflowTransition` | `RoBYCoNTe\FilamentFlow\Models` | Transition configurations |
| `WorkflowTransitionField` | `RoBYCoNTe\FilamentFlow\Models` | Fields to show in transition forms |
| `WorkflowStateField` | `RoBYCoNTe\FilamentFlow\Models` | Field permissions per state |
| `WorkflowStateFieldRole` | `RoBYCoNTe\FilamentFlow\Models` | What a role may do on a field of a state |
| `WorkflowStateVisibility` | `RoBYCoNTe\FilamentFlow\Models` | A visibility rule of a state |
| `WorkflowAssignment` | `RoBYCoNTe\FilamentFlow\Models` | User/team assignments to workflows |
| `WorkflowOwnerChange` | `RoBYCoNTe\FilamentFlow\Models` | The handovers of a record: who held it, who holds it now, what the previous holder kept |
| `WorkflowNotification` | `RoBYCoNTe\FilamentFlow\Models` | Notification configurations |
| `WorkflowNotificationRecipient` | `RoBYCoNTe\FilamentFlow\Models` | Notification recipient strategies |
| `WorkflowNotificationChannel` | `RoBYCoNTe\FilamentFlow\Models` | Notification delivery channels |
| `WorkflowNotificationTemplate` | `RoBYCoNTe\FilamentFlow\Models` | Notification message templates |
| `WorkflowNotificationLog` | `RoBYCoNTe\FilamentFlow\Models` | Notification delivery audit logs |
| `WorkflowUserInvolvement` | `RoBYCoNTe\FilamentFlow\Models` | User involvement tracking |
| `WorkflowTransitionSnapshot` | `RoBYCoNTe\FilamentFlow\Models` | Audit trail for transitions |
| `WorkflowStateTransition` | `RoBYCoNTe\FilamentFlow\Models` | The transitions a state takes part in |
| `WorkflowTransitionPermission` | `RoBYCoNTe\FilamentFlow\Models` | Who may take a transition |
| `WorkflowTransitionSideEffect` | `RoBYCoNTe\FilamentFlow\Models` | What a transition writes when it runs |
| `WorkflowTransitionValidationRule` | `RoBYCoNTe\FilamentFlow\Models` | The rules a transition applies, and the fields its dialog asks for |
| `WorkflowScheduledCheck` | `RoBYCoNTe\FilamentFlow\Models` | A check the engine runs on a schedule |
| `WorkflowScheduledCheckLog` | `RoBYCoNTe\FilamentFlow\Models` | When a scheduled check last ran, and what it did |
| `WorkflowTransitionMetadata` | `RoBYCoNTe\FilamentFlow\Models` | Additional metadata for transitions |
| `WorkflowStateAccessRule` | `RoBYCoNTe\FilamentFlow\Models` | State-based access control rules |

## Services

| Service | Namespace | Description |
|---|---|---|
| `StateService` | `RoBYCoNTe\FilamentFlow\Services` | Manages state metadata and options |
| `TransitionFormService` | `RoBYCoNTe\FilamentFlow\Services` | Builds and validates transition forms |
| `WorkflowCreationService` | `RoBYCoNTe\FilamentFlow\Services` | Creates workflows from configuration |
| `WorkflowFieldPermissionsService` | `RoBYCoNTe\FilamentFlow\Services` | Manages field permissions |
| `WorkflowStateAccessService` | `RoBYCoNTe\FilamentFlow\Services` | State-based access control evaluation |
| `NotificationService` | `RoBYCoNTe\FilamentFlow\Services` | Orchestrates workflow notifications |
| `RecipientResolver` | `RoBYCoNTe\FilamentFlow\Services` | Resolves notification recipients |
| `FormBuilderHelper` | `RoBYCoNTe\FilamentFlow\Services` | Advanced form building utilities |
| `ConditionEvaluator` | `RoBYCoNTe\FilamentFlow\Services` | The conditions of a transition, evaluated against a record |
| `ScheduledCheckRunner` | `RoBYCoNTe\FilamentFlow\Services` | The engine of the scheduled checks |
| `SideEffectExecutor` | `RoBYCoNTe\FilamentFlow\Services` | The effects a transition writes |
| `WorkflowValidationService` | `RoBYCoNTe\FilamentFlow\Services` | Validating a transition and the values it is given |
| `EvaluatesAccessRules` | `RoBYCoNTe\FilamentFlow\Services` | Reading the access rules of a state |
| `ScopesAccessibleRecords` | `RoBYCoNTe\FilamentFlow\Services` | Narrowing a query to what a user may see |
| `ReadsFieldPermissions` | `RoBYCoNTe\FilamentFlow\Services` | What a state allows on each field |
| `ReadsCreationAndColumnPermissions` | `RoBYCoNTe\FilamentFlow\Services` | The permissions of creating a record, and of the columns of a list |
| `ResolvesFieldPermissionContext` | `RoBYCoNTe\FilamentFlow\Services` | The state, the user and the tenant a permission is read for |
| `AppliesValidationRules` | `RoBYCoNTe\FilamentFlow\Services` | Applying the rules of a transition to the values |
| `EvaluatesValidationValues` | `RoBYCoNTe\FilamentFlow\Services` | Evaluating a value against the rules of its field |
| `ResolvesValidationContext` | `RoBYCoNTe\FilamentFlow\Services` | The context a rule is evaluated in |
| `FindsNotificationTargets` | `RoBYCoNTe\FilamentFlow\Services` | Who a notification reaches |
| `DeliversNotifications` | `RoBYCoNTe\FilamentFlow\Services` | Sending a notification through its channels |

## Casts

| Cast | Namespace | Description |
|---|---|---|
| `FlexibleStateCast` | `RoBYCoNTe\FilamentFlow\Casts` | Custom cast for PHP + database-only states |

## Jobs and validation

| Class | Namespace | Description |
|---|---|---|
| `SendWorkflowNotification` | `RoBYCoNTe\FilamentFlow\Jobs` | The queued job that sends one notification |
| `ValidationResult` | `RoBYCoNTe\FilamentFlow\Validation` | What a validation pass found: the problems, with the field each one belongs to |
| `ValidationLevel` | `RoBYCoNTe\FilamentFlow\Definition\Enums` | How much a transition validates: nothing, the fields, or everything |

## Support Classes

| Class | Namespace | Description |
|---|---|---|
| `DefaultRoleResolver` | `RoBYCoNTe\FilamentFlow\Support` | Default role resolver (supports Spatie Permission) |
| `DefaultPermissionResolver` | `RoBYCoNTe\FilamentFlow\Support` | Default permission resolver (supports Gates) |
| `AccessRuleEvaluator` | `RoBYCoNTe\FilamentFlow\Support` | Evaluates access rule tokens against users/records |
| `AccessibleStates` | `RoBYCoNTe\FilamentFlow\Support` | The states a user may see, and what it means when there are none |
| `CanonicalJson` | `RoBYCoNTe\FilamentFlow\Support` | The canonical form of a value: the order of the keys does not count, the order of a list does |
| `CompletionPayload` | `RoBYCoNTe\FilamentFlow\Support` | The variables and functions of a formula scope, as the editor reads them |
| `FormulaCompletionRegistry` | `RoBYCoNTe\FilamentFlow\Support` | The scopes of the formula editor, registered by name |
| `FormulaConditionRegistry` | `RoBYCoNTe\FilamentFlow\Support` | The hosts able to evaluate a formula condition |
| `WorkflowFormulaScope` | `RoBYCoNTe\FilamentFlow\Support` | The `workflow` scope of the formula editor |
| `ModelDiscovery` | `RoBYCoNTe\FilamentFlow\Support` | Reading the columns of a model |
| `FieldPermissionApplier` | `RoBYCoNTe\FilamentFlow\Support` | Applying the permissions of a state to a component |
| `ComponentIdentifier` | `RoBYCoNTe\FilamentFlow\Support` | The name a component was made with |
| `RuleOptions` | `RoBYCoNTe\FilamentFlow\Support` | The access-rule tokens and relationships offered where they make sense |
| `UserModel` | `RoBYCoNTe\FilamentFlow\Support` | Which class is the user of the application |
| `AssignmentTypeConfig` | `RoBYCoNTe\FilamentFlow\Support` | The kinds of assignment, with their labels |
| `FieldChanges` | `RoBYCoNTe\FilamentFlow\Support` | The delta of two sets of values: the paths that moved, compared as a person would (`5000` and `5000.00` are the same value) |
| `LocalizedDate` | `RoBYCoNTe\FilamentFlow\Support` | How a date reads when nobody chose: the day before the month where the language belongs |
| `RoleLabel` | `RoBYCoNTe\FilamentFlow\Support` | The words a role reads in: the labels of the host, then the translations, then the name as it is written |

## Presentation

How a field reads in the history: the words of the host, the shape of a value, and the generic
reading the engine falls back to.

| Class | Namespace | Description |
|---|---|---|
| `FieldFormat` | `RoBYCoNTe\FilamentFlow\Presentation` | How a presented value reads: a line of text, labelled pairs, a table, a set of files — or nothing |
| `FieldPresentation` | `RoBYCoNTe\FilamentFlow\Presentation` | A field as a person should read it: its label, the shape of its value, the block it belongs to, whether it belongs to the history |
| `DefaultFieldPresenter` | `RoBYCoNTe\FilamentFlow\Presentation` | How a value reads when nobody claimed it: a map becomes labelled pairs, a list of maps a table, a boolean yes or no |

## Exceptions

All exceptions live in the `RoBYCoNTe\FilamentFlow\Exceptions` namespace.

| Exception | When Thrown | Notable Properties / Methods |
|---|---|---|
| `UnauthorizedTransitionException` | User not allowed to transition to a state | `getRecord()`, `getFromState()`, `getToState()`, `getUser()` |
| `WorkflowNotFoundException` | No active workflow found for the model class | `$modelClass` property |
| `ActionNotFoundException` | Requested action does not exist for the current state | `$actionName` property |
| `ConditionNotMetException` | A transition condition evaluated to false | `$actionName` property |
| `InitialStateNotFoundException` | Workflow exists but has no state marked as initial | — |
| `AuthenticationRequiredException` | An operation requires an authenticated user but none is present | — |
| `StateDeletionException` | Cannot delete a state because transitions reference it | — |
| `InvalidStateException` | The state field value is not a valid State instance | — |
| `InvalidComponentException` | A Filament form component does not support readonly/disabled | `$fieldName` property |
| `UnknownValidationRuleException` | A declaration names a validation rule nobody registered | `$ruleName` property |
| `FormulaConditionFailedException` | A formula condition refused, with the message the host gave it | `getMessage()` |

`UnauthorizedTransitionException` exposes:
- `getMessage()` — Human-readable error message
- `getRecord()` — The model record involved
- `getFromState()` — The source state class/name
- `getToState()` — The target state class/name
- `getUser()` — The user who attempted the transition (`null` if unauthenticated)

## Builders

| Builder | Namespace | Description |
|---|---|---|
| `WorkflowNotificationBuilder` | `RoBYCoNTe\FilamentFlow\Builders` | Fluent builder for code-first notifications |

**WorkflowNotificationBuilder Methods:**

```php
WorkflowNotificationBuilder::make()
    ->name('notification_name')           // Optional name for logging
    ->channel('database', $config)        // Channel: database, mail
    ->recipients(['@owner', 'role:admin']) // Who receives the notification
    ->title('Title with {{variables}}')    // Notification title
    ->body('Body with {{variables}}')      // Notification body
    ->subject('Email subject')             // Email subject (mail channel)
    ->actionUrl('/url', 'Button Text')     // Action button
    ->priority('high')                     // low, medium, high, urgent
    ->templateEngine('plain')              // plain, blade, mustache
    ->immediate()                          // Send immediately (default)
    ->delay(30)                            // Delay by minutes
    ->metadata(['key' => 'value']);        // Additional metadata
```

## Advanced Features

### Custom State Sorting

Define custom sort order for states in tables to match your workflow logic:

```php
// In your state classes
class PendingState extends OrderState implements HasStateSortOrder
{
    public static function getSortOrder(): int
    {
        return 1; // First in the list
    }
}

class ProcessingState extends OrderState implements HasStateSortOrder
{
    public static function getSortOrder(): int
    {
        return 2; // Second in the list
    }
}
```

Then use sortable columns:

```php
StateSelectColumn::make('state')
    ->sortable() // Uses custom sort order automatically
```

## Workflow Model Scopes

`RoBYCoNTe\FilamentFlow\Models\Workflow`

### Static Finder

```php
use RoBYCoNTe\FilamentFlow\Models\Workflow;

// Find the active workflow for a model class (tenant fallback applied automatically)
Workflow::findForModel(Order::class, 'state');

// Find for a specific tenant ID (overrides auto-detection)
Workflow::findForModel(Order::class, 'state', $tenantId);
```

`findForModel` checks for a tenant-specific workflow first, then falls back to a global workflow (`tenant_id = null`). Results are cached using the configured cache store and TTL.

### Query Scopes

```php
// Include both global and current-tenant workflows
Workflow::query()->forCurrentTenant()->get();

// Only workflows for a specific tenant
Workflow::query()->forTenant($tenantId)->get();

// Only global workflows (tenant_id = null)
Workflow::query()->global()->get();
```

### Instance Methods

```php
$workflow->initialState();    // ?WorkflowState — the state marked is_initial = true
$workflow->finalStates();     // HasMany<WorkflowState> — states marked is_final = true
$workflow->isGlobal();        // bool — true when tenant_id is null
$workflow->isTenantSpecific(); // bool — true when tenant_id is set

// Flush all workflow caches (affects entire cache store — use with care)
Workflow::flushCache();
```

## Workflow Definition SDK

| Class | Namespace | Description |
|---|---|---|
| `WorkflowDefinition` | `RoBYCoNTe\FilamentFlow\Definition` | Typed workflow definition (states, transitions, metadata) |
| `State` | `RoBYCoNTe\FilamentFlow\Definition` | A workflow state (initial/final, color, per-state fields) |
| `StateField` | `RoBYCoNTe\FilamentFlow\Definition` | Per-state field visibility/mutability/required |
| `Transition` | `RoBYCoNTe\FilamentFlow\Definition` | State transition or in-state action (guards, effects, rules) |
| `SideEffect` | `RoBYCoNTe\FilamentFlow\Definition` | Transition side effect |
| `ValidationRule` | `RoBYCoNTe\FilamentFlow\Definition` | Transition validation rule |
| `ScheduledCheck` | `RoBYCoNTe\FilamentFlow\Definition` | Typed scheduled check (condition, frequency, action) |
| `AccessRule` | `RoBYCoNTe\FilamentFlow\Definition` | State access rule (view/edit/transition/create) |
| `Notification` | `RoBYCoNTe\FilamentFlow\Definition` | Notification (trigger, channels, template) |
| `Recipient` | `RoBYCoNTe\FilamentFlow\Definition` | Typed notification recipient |

### Enums

| Enum | Values |
|---|---|
| `Visibility` | `visible`, `hidden` |
| `Mutability` | `readonly`, `editable`, `locked` |
| `SideEffectType` | `set_field`, `set_timestamp`, `clear_field`, `increment`, `custom_class`, `create_child_application` |
| `ScheduledCheckCondition` | `date_offset`, `field_compare`, `custom_class` |
| `ScheduledCheckAction` | `notification`, `transition`, `side_effect` |
| `ScheduledCheckFrequency` | `every_minute`, `every_five_minutes`, `hourly`, `daily`, `weekly` |
| `AccessType` | `view`, `edit`, `transition`, `create` |
| `AccessOperator` | `or`, `and` |
| `NotificationTrigger` | `on_transition`, `on_state_enter`, `on_state_exit`, `on_assignment`, `on_field_change` |
| `NotificationTiming` | `immediate`, `delayed` |
| `NotificationPriority` | `low`, `medium`, `high`, `urgent` |
| `NotificationChannel` | `database`, `mail` |
| `RecipientType` | `role`, `user`, `trigger_user`, `assigned_users`, `record_owner`, `state_actors`, `all_involved`, `involvement_type`, `custom_field`, `custom_query`, `custom_class` |
