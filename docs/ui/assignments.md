# Assignment Management

Filament Flow provides a complete system for assigning users to workflow records. Each assignment has a **type** that defines the user's role in the workflow, optional **access overrides** that bypass state-based rules, and a free-form **metadata** field for application-specific data.

## Assignment Types

Three built-in types are available:

| Type | Meaning | Visual |
|---|---|---|
| `primary` | Main responsible user | Full opacity, primary ring color, star icon |
| `secondary` | Supporting collaborator | Reduced opacity (`opacity-75`), warning ring color |
| `viewer` | Read-only observer | Low opacity (`opacity-50`), gray ring color |

Types affect visual rendering in `AssignmentSummaryColumn` and can be used to filter or scope queries in your application logic.

## Model Setup

Add the `HasWorkflowAssignments` trait to any Eloquent model that should support assignments:

```php
use RoBYCoNTe\FilamentFlow\Concerns\HasWorkflowAssignments;

class Order extends Model
{
    use HasWorkflowAssignments;
}
```

This adds a polymorphic `assignments()` relationship and a full set of assignment management methods to the model.

## HasWorkflowAssignments Trait

### Basic Assignment

```php
// Assign a user as primary (default)
$order->assignTo($user);

// Assign with a specific type
$order->assignTo($user, 'secondary');
$order->assignTo($user, 'viewer');

// Check if assigned
$order->isAssignedTo($user);
$order->isAssignedTo($user, 'primary'); // check specific type

// Remove assignment
$order->unassignFrom($user);
$order->unassignFrom($user, 'secondary'); // remove specific type only
```

### Assignment with Access Overrides

Use `assignWithOverrides()` to grant explicit access permissions that bypass the normal state-based access rules:

```php
$order->assignWithOverrides(
    user: $user,
    overrides: [
        'view'       => true,  // can always view, regardless of state rules
        'edit'       => true,  // can always edit
        'transition' => null,  // no override — falls back to state rules
    ],
    type: 'secondary',
    assignedBy: auth()->user(),
    metadata: ['source' => 'manual_assignment'],
);
```

### Querying Assigned Users

```php
// All assigned users (any type)
$order->getAssignedUsers();

// Filter by type(s)
$order->getAssignedUsers(['primary', 'secondary']);

// Convenience methods
$order->getPrimaryAssignedUsers();
$order->getSecondaryAssignedUsers();
$order->getViewerAssignedUsers();

// Get user IDs only
$order->getAssignedUserIds();
$order->getAssignedUserIds(['primary']);

// Get all types for a specific user
$order->getAssignmentTypesForUser($user); // ['primary', 'viewer']
```

### Syncing and Bulk Operations

```php
// Sync a set of users for a given type (adds missing, removes extra)
$order->syncAssignments([1, 2, 3], 'primary');

// Reassign from one user to another
$order->reassign($fromUser, $toUser);
$order->reassign($fromUser, $toUser, 'secondary'); // specific type only

// Remove all assignments (or only a specific type)
$order->clearAssignments();
$order->clearAssignments('viewer');
```

### Changing Assignment Type

```php
// Returns true on success, false if target type already exists for that user
$order->changeAssignmentType($assignmentId, 'primary');
```

### Updating Access Overrides

```php
$order->updateAccessOverrides($user, [
    'view'       => true,
    'edit'       => null,   // remove override
    'transition' => false,
]);
```

## Assignment Metadata

Each `WorkflowAssignment` record has a JSON `metadata` field for storing arbitrary application data alongside the assignment. This is useful for tracking the source of an assignment, contextual notes, or any domain-specific payload.

```php
// Store metadata on creation
$order->assignWithOverrides(
    user: $user,
    overrides: ['view' => true],
    metadata: [
        'source'   => 'diary_entry',
        'entry_id' => $diaryEntry->id,
    ],
);

// Read metadata later
$assignment->metadata;              // ['source' => 'diary_entry', 'entry_id' => 42]
$assignment->getMetadata('source'); // 'diary_entry'
$assignment->getMetadata();         // full array
```

Metadata is automatically passed through to UI components as part of each user's data array, making it available for custom rendering.

## AssignmentSummaryColumn

A Filament table column that renders assigned users as overlapping avatars with initials, colored rings by assignment type, and an optional overflow counter.

```php
use RoBYCoNTe\FilamentFlow\Tables\Columns\AssignmentSummaryColumn;

AssignmentSummaryColumn::make('assignments')
    ->label('Assigned To'),
```

### Options

```php
AssignmentSummaryColumn::make('assignments')
    ->avatarLimit(5)           // max avatars shown (default: 3)
    ->avatarTooltip(false),    // disable hover tooltip (default: true)
```

### Visual Behavior

- **Ring color** indicates assignment type: primary (blue), secondary (amber), viewer (gray)
- **Opacity** decreases by type: primary = full, secondary = 75%, viewer = 50%
- **Z-index** stacks primary on top, secondary below, viewer at the bottom
- **Overflow counter** shows `+N` when assignments exceed the limit

### Avatar Decorator (Extension Point)

Use `avatarDecorator()` to render a small badge overlay on each avatar based on assignment data. The callback receives the full assignment array (including `metadata`) and should return an array with `icon` and `class` keys, or `null` for no badge.

```php
AssignmentSummaryColumn::make('assignments')
    ->avatarDecorator(function (array $assignment): ?array {
        // $assignment keys: name, initials, assignment_type, roles, metadata

        return match (true) {
            ($assignment['metadata']['source'] ?? null) === 'diary_entry' => [
                'icon'  => 'heroicon-m-book-open',
                'class' => 'bg-warning-400',
            ],
            default => null, // no badge
        };
    }),
```

The badge is rendered as a small circle (`h-3.5 w-3.5`) in the bottom-right corner of the avatar with the specified background color and icon.

**Data shape received by the callback:**

```php
[
    'name'            => 'Jane Doe',
    'initials'        => 'JD',
    'assignment_type' => 'secondary',     // primary | secondary | viewer
    'roles'           => 'editor, admin', // comma-separated or empty string
    'metadata'        => ['source' => 'diary_entry', ...], // or null
]
```

## AssignmentManager Livewire Component

An interactive Livewire component that renders a full assignment management UI inside a Filament form or infolist. It allows admins to add/remove users, change assignment types, and toggle per-assignment access overrides.

Embed it in a Filament schema using `Filament\Schemas\Components\Livewire`:

```php
use Filament\Schemas\Components\Livewire;
use RoBYCoNTe\FilamentFlow\Livewire\AssignmentManager;

Livewire::make(AssignmentManager::class)
    ->visible(fn (?Model $record) => $record !== null),
```

The component automatically receives the current `$record` from Filament's schema context.

### Authorization

The "add" and "remove" controls are only visible when `canManageAssignments()` returns `true`. By default this checks for `isAdmin()` or `isSuperAdmin()` on the authenticated user. Override this behavior by extending `AssignmentManager` and replacing the method.

### Access Overrides UI

When adding a new assignment, the form includes checkboxes for `view`, `edit`, and `transition` overrides. At least `view` must be checked — the component throws a validation error if it is not.

When a user already has overrides set, the UI shows a summary badge on their row and allows toggling each override individually.

### Tenant Awareness

In a multi-tenant panel, the user dropdown is automatically scoped to the current tenant using the `tenant_user_relationship` config key (default: `'users'`). Configure it in `config/filament-flow.php`:

```php
'tenant_user_relationship' => 'members',
```

## AssignmentSummaryEntry Infolist Component

A Filament infolist entry that renders a detailed assignment summary for the current record, including per-user permissions (view / edit / transition) derived from the current workflow state, access override indicators, and role-based access rules.

```php
use RoBYCoNTe\FilamentFlow\Infolists\Components\AssignmentSummaryEntry;

AssignmentSummaryEntry::make()
    ->stateColumn('status'), // column used to resolve current workflow state (default: 'state')
```

### Metadata Badges (Extension Point)

Use `metadataBadges()` to render additional context badges next to each user row. The callback receives the full assignment data array and should return an array of badge arrays (each with `label` and optional `color` and `icon`):

```php
AssignmentSummaryEntry::make()
    ->metadataBadges(function (array $assignment): array {
        $badges = [];

        if (($assignment['metadata']['source'] ?? null) === 'diary_entry') {
            $badges[] = [
                'label' => 'From Diary',
                'color' => 'warning',
                'icon'  => 'heroicon-m-book-open',
            ];
        }

        return $badges;
    }),
```

**Data shape received by the callback:**

```php
[
    'user'             => App\Models\User,
    'assignment_type'  => 'primary',
    'can_view'         => true,
    'can_edit'         => false,
    'can_transition'   => true,
    'override_view'    => true,
    'override_edit'    => false,
    'override_transition' => false,
    'has_overrides'    => true,
    'metadata'         => ['source' => 'diary_entry', ...],
    'metadata_badges'  => [],  // populated by this callback
]
```

## WorkflowAssignment Model

The `WorkflowAssignment` model is the underlying database record for each assignment.

| Column | Type | Description |
|---|---|---|
| `assignable_type` | string | Polymorphic model class |
| `assignable_id` | int | Polymorphic model ID |
| `user_id` | int | Assigned user |
| `assignment_type` | string | `primary`, `secondary`, or `viewer` |
| `assigned_by` | int\|null | User who created the assignment |
| `assigned_at` | datetime\|null | Timestamp of assignment |
| `metadata` | json\|null | Free-form application data |
| `override_view` | bool\|null | Explicit view access override |
| `override_edit` | bool\|null | Explicit edit access override |
| `override_transition` | bool\|null | Explicit transition access override |

### Useful Model Methods

```php
$assignment->hasAccessOverride();          // true if any override is set
$assignment->hasOverrideFor('edit');       // check a specific override
$assignment->getMetadata('source');        // read a metadata key
$assignment->getMetadata();                // full metadata array
```
