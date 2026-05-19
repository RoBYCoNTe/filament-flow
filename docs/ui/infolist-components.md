# Infolist Components

Filament Flow provides two infolist entry components for displaying workflow-related information inside Filament infolists, plus a workflow diagram view for the admin panel.

## TransitionTimeline

`TransitionTimeline` renders a chronological audit trail of state transitions for a workflow record. Each entry shows what changed, who triggered it, and when — giving viewers a clear history of the record's lifecycle.

### Adding to an Infolist

```php
use RoBYCoNTe\FilamentFlow\Infolists\Components\TransitionTimeline;

public function infolist(Infolist $infolist): Infolist
{
    return $infolist
        ->schema([
            TransitionTimeline::make(),
        ]);
}
```

### Options

```php
TransitionTimeline::make()
    ->limit(5)
    ->showAllForAdmins()
    ->filterByAccess(),
```

| Method | Default | Description |
|---|---|---|
| `limit(int $limit)` | `10` | Maximum number of transitions to display. `getTotalCount()` always returns the full count, so a "N more entries" notice is shown when the total exceeds the limit. |
| `showAllForAdmins(bool $show = true)` | `true` | When enabled, users with a super-admin role see all transitions regardless of visibility flags. Non-admins only see transitions where `is_visible = true`. |
| `filterByAccess(bool $filter = true)` | `true` | When enabled, non-admin users are further filtered to only transitions they have access to view. Disable this to show all visible transitions to everyone. |

### What Each Entry Shows

Each timeline entry displays:

- **Transition label** — for state-changing transitions this is `from_state → to_state` using human-readable labels when available, falling back to the PHP class basename. For self-transitions (same from/to state), the transition's own label is used instead.
- **Timestamp** — shown as a relative time string (e.g. "3 hours ago") with the ISO 8601 datetime accessible via the `datetime` attribute.
- **User name** — the name of the user who triggered the transition, if recorded.
- **Notes** — any reason or notes recorded at the time of the transition, truncated to 120 characters.

A filled primary-color dot marks state-changing transitions; a gray dot marks same-state actions.

### Admin Detection

The component determines whether the current user is an admin by checking if they hold any role listed in the `state_access.super_admin_roles` config key (default: `['super_admin']`). This uses Spatie Permission's `hasAnyRole()` method when available.

```php
// config/filament-flow.php
'state_access' => [
    'super_admin_roles' => ['super_admin', 'admin'],
],
```

### Programmatic Access

If you need to work with the transition records directly (e.g. in a custom view or notification), call `getTimeline()` on the component instance:

```php
$entries = $timelineComponent->getTimeline(); // Collection of WorkflowStateTransition
$total   = $timelineComponent->getTotalCount(); // int — full count, unaffected by limit
```

## AssignmentSummaryEntry

`AssignmentSummaryEntry` renders a detailed assignment card for a record inside an infolist. It shows each assigned user alongside their effective `view`, `edit`, and `transition` permissions for the record's current state, access override indicators, and role-based access rules derived from the active workflow configuration.

For full documentation on the assignment system, including the data model, the `HasWorkflowAssignments` trait, and related UI components, see [Assignment Management](assignments.md).

### Quick Example

```php
use RoBYCoNTe\FilamentFlow\Infolists\Components\AssignmentSummaryEntry;

AssignmentSummaryEntry::make()
    ->stateColumn('status'),
```

The `stateColumn` option tells the component which model column holds the current workflow state (default: `'state'`). This is used to resolve the active state and compute effective permissions for each assigned user.

For the `metadataBadges()` extension point and the full data shape passed to callbacks, see the [AssignmentSummaryEntry section in Assignment Management](assignments.md#assignmentsummaryentry-infolist-component).

## WorkflowDiagram

The package includes a `filament-flow::infolists.workflow-diagram` Blade view that renders a visual representation of a workflow's states and the allowed transitions between them. It is used automatically when viewing a Workflow record inside the Filament Flow admin panel and does not need to be wired up manually for that use case.

If you want to embed it in a custom infolist, reference it as a view component:

```php
use Filament\Infolists\Components\View;

View::make('filament-flow::infolists.workflow-diagram'),
```

The view expects the `$record` to be a `Workflow` model instance with its `states` and `transitions` relationships loaded.
