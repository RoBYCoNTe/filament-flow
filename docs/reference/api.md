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

## Actions

| Action | Namespace | Description |
|---|---|---|
| `StateAction` | `RoBYCoNTe\FilamentFlow\Actions` | Single record state transition |
| `StateActionGroup` | `RoBYCoNTe\FilamentFlow\Actions` | Auto-generated action group |
| `StateBulkAction` | `RoBYCoNTe\FilamentFlow\Actions` | Bulk state transition |

## Utilities

| Utility | Namespace | Description |
|---|---|---|
| `StateTabs` | `RoBYCoNTe\FilamentFlow` | Generate tabs for listing pages |

## Interfaces

| Interface | Methods | Description |
|---|---|---|
| `HasLabel` | `getLabel(): string` | Display name for the state |
| `HasIcon` | `getIcon(): string` | Icon for the state |
| `HasColor` | `getColor(): string\|array` | Color for the state |
| `HasDescription` | `getDescription(): string` | Description text for the state |
| `HasStateMetadata` | Combines all metadata interfaces | Full state metadata contract |
| `HasStateSortOrder` | `getSortOrder(): int` | Custom sort position for state |
| `HasAccessRules` | `getCreateAccessRules()`, `getViewAccessRules()`, `getEditAccessRules()`, `getTransitionAccessRules()` | Code-First state access rules |
| `RoleResolver` | `getRoles()`, `hasAnyRole()`, `isSuperAdmin()` | Role resolution for access control |
| `PermissionResolver` | `hasPermission()` | Permission resolution for access control |
| `HasStateNotifications` | `onEnterNotifications()`, `onExitNotifications()` | Notifications on state enter/exit |
| `HasTransitionNotifications` | `notifications()` | Notifications on transition |

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

## Models (Database-Driven Workflows)

| Model | Namespace | Description |
|---|---|---|
| `Workflow` | `RoBYCoNTe\FilamentFlow\Models` | Workflow definitions |
| `WorkflowState` | `RoBYCoNTe\FilamentFlow\Models` | State definitions (PHP or database-only) |
| `WorkflowTransition` | `RoBYCoNTe\FilamentFlow\Models` | Transition configurations |
| `WorkflowTransitionField` | `RoBYCoNTe\FilamentFlow\Models` | Fields to show in transition forms |
| `WorkflowStateField` | `RoBYCoNTe\FilamentFlow\Models` | Field permissions per state |
| `WorkflowFieldPermission` | `RoBYCoNTe\FilamentFlow\Models` | Field-level permissions |
| `WorkflowAssignment` | `RoBYCoNTe\FilamentFlow\Models` | User/team assignments to workflows |
| `WorkflowNotification` | `RoBYCoNTe\FilamentFlow\Models` | Notification configurations |
| `WorkflowNotificationRecipient` | `RoBYCoNTe\FilamentFlow\Models` | Notification recipient strategies |
| `WorkflowNotificationChannel` | `RoBYCoNTe\FilamentFlow\Models` | Notification delivery channels |
| `WorkflowNotificationTemplate` | `RoBYCoNTe\FilamentFlow\Models` | Notification message templates |
| `WorkflowNotificationLog` | `RoBYCoNTe\FilamentFlow\Models` | Notification delivery audit logs |
| `WorkflowUserInvolvement` | `RoBYCoNTe\FilamentFlow\Models` | User involvement tracking |
| `WorkflowTransitionSnapshot` | `RoBYCoNTe\FilamentFlow\Models` | Audit trail for transitions |
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

## Casts

| Cast | Namespace | Description |
|---|---|---|
| `FlexibleStateCast` | `RoBYCoNTe\FilamentFlow\Casts` | Custom cast for PHP + database-only states |

## Support Classes

| Class | Namespace | Description |
|---|---|---|
| `DefaultRoleResolver` | `RoBYCoNTe\FilamentFlow\Support` | Default role resolver (supports Spatie Permission) |
| `DefaultPermissionResolver` | `RoBYCoNTe\FilamentFlow\Support` | Default permission resolver (supports Gates) |
| `AccessRuleEvaluator` | `RoBYCoNTe\FilamentFlow\Support` | Evaluates access rule tokens against users/records |

## Exceptions

| Exception | Namespace | Description |
|---|---|---|
| `UnauthorizedTransitionException` | `RoBYCoNTe\FilamentFlow\Exceptions` | Thrown when user attempts unauthorized state transition |

The exception exposes:
- `getMessage()` — Human-readable error message
- `getRecord()` — The model record involved
- `getFromState()` — The state being transitioned from
- `getToState()` — The state being transitioned to
- `getUser()` — The user who attempted the transition

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
