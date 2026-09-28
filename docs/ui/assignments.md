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

## AssigneeSelect Form Component

`AssigneeSelect` is a Filament form component for assigning users directly from a form schema. It renders as a searchable multi-select with role labels and automatically syncs with the model's assignments.

```php
use RoBYCoNTe\FilamentFlow\Forms\Components\AssigneeSelect;

AssigneeSelect::make('assignees')
    ->label('Assigned Users')
    ->assignmentType('primary'), // primary | secondary | viewer
```

### Options

```php
AssigneeSelect::make('assignees')
    ->assignmentType('secondary')       // assignment type for synced users (default: 'primary')
    ->usersQuery(function (Builder $query): Builder {
        return $query->where('department', 'support');
    }),
```

**Tenant awareness**: in multi-tenant panels, the component automatically filters available users to those belonging to the current tenant, using the `tenant_user_relationship` config key (default: `'users'`).

**User labels**: each user is shown as `"Name (Role1, Role2)"` when Spatie Permission is installed, or just `"Name"` otherwise.

**Syncing**: on form save, the component calls `syncAssignments()` on the record for the configured type, replacing existing assignments of that type with the selected users.

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

## OwnerColumn

A Filament table column that renders **who holds the record** — the column named by
`state_access.owner_field` (`user_id` by default) — and, beside the name, that the record
**changed hands**: from whom, when, and how many times. The handovers are read from
`workflow_owner_changes`, written every time the panel of the assignments hands a record over.

```php
use RoBYCoNTe\FilamentFlow\Tables\Columns\OwnerColumn;

OwnerColumn::make('owner')
    ->label('Held by'),
```

### Options

```php
OwnerColumn::make('owner')
    ->roleLabels(['admin' => 'Amministratore']) // the words of the host, keyed by role name
    ->historyLimit(5)      // how many handovers the tooltip lists (default: 5)
    ->withHistory(false)   // the bare name, without the handovers
    ->inlinesLastChange(false), // the last handover only in the tooltip
```

### Visual Behavior

- the current owner is an avatar with initials and the name, with the roles underneath
- when the record changed hands, the column says **`from <previous holder> · <date>`** under
the name, with a badge carrying how many handovers there were and a tooltip listing them all
(previous holder, date, what they kept, the note)
- a record with nobody is a dash
- the cell behaves like the other cells of the row: clicking it follows the record link of the
table (`->disabledClick()` to leave it inert)

### Reading the owner in your own code

The seam is the same one the column uses:

```php
use RoBYCoNTe\FilamentFlow\Support\RecordOwner;

RecordOwner::field();          // 'user_id', or what the host configured
RecordOwner::id($application); // the key of the holder, or null
RecordOwner::of($application); // the holder, resolved to the user model
```

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

The cell behaves like the other cells of the row: clicking it follows the record link of the
table. A host that wants it inert says `->disabledClick()`.
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
    ->key('assignment-manager.section')
    ->visible(fn (?Model $record) => $record !== null),
```

The component automatically receives the current `$record` from Filament's schema context.

### Explaining itself

The panel carries a short explanation of what it is for — ownership and what the previous
holder keeps, the roles of the assignees, and the three readings of a permission. It is read
once, so it stays **folded for whoever acts** and opens for whoever may not: there the
explanation is the whole content of the panel, and it says why the settings are not theirs to
change. Pass `showExplanation => false` for a bare panel:

```php
Livewire::make(AssignmentManager::class, ['showExplanation' => false])
```

### The handovers it went through

Under the ownership section the panel keeps the history of the handovers — **collapsed by
default** — with who held the record before, who holds it now, when, what the previous holder
kept and who made the change. `showHistory => false` for a panel without it.

## OwnershipHistoryEntry

The same history as a component of its own, for a form, a step or an infolist:

```php
use RoBYCoNTe\FilamentFlow\Infolists\Components\OwnershipHistoryEntry;

OwnershipHistoryEntry::make('ownership_history')
    ->limit(10)          // how many handovers to list (default: all)
    ->timeline(false),   // a plain list instead of a timeline
```

A record that never changed hands says so in one line
(`filament-flow::messages.ownership_history_empty`) instead of showing nothing.

### Handing a record over from your own code

The panel is one caller of the handover; a command, a job or an import is another:

```php
use RoBYCoNTe\FilamentFlow\Services\OwnershipTransfer;

app(OwnershipTransfer::class)->transfer(
    record: $application,
    toUserId: $successor->id,
    retention: OwnershipTransfer::RETENTION_SECONDARY, // none | viewer | secondary
    note: 'Handover to another officer',
    actor: auth()->user(),
);
```

### Reading the history in your own code

```php
use RoBYCoNTe\FilamentFlow\Support\OwnershipHistory;

OwnershipHistory::for($application);         // the handovers, most recent first
OwnershipHistory::for($application, 5);      // the last five
```

The `key()` matters when the same page carries more than one instance of the panel (a section
and the dialog, two sections in two steps): Livewire identifies a nested component by its key,
and a second instance sharing the first one's key comes back as an **empty placeholder**. Give
each placement a key of its own.

### Authorization

The "add" and "remove" controls are only visible when `canManageAssignments()` returns `true`. By default this checks for `isAdmin()` or `isSuperAdmin()` on the authenticated user. Pass `superAdminOnly => true` to keep plain admins on the reading side of the panel:

```php
Livewire::make(AssignmentManager::class, ['superAdminOnly' => true])
```

### Ownership Transfer

When the record has the configured owner column (`state_access.owner_field`, `user_id` by default), the panel shows a section with who holds the record and a **Transfer ownership** form. The handover asks who takes over, what the previous owner keeps — *nothing* (their access ends), *observer* (a `viewer` assignment with the view override granted) or *collaborator* (a `secondary` assignment, no overrides) — and an optional note, written into the assignment metadata. The change fires the `WorkflowOwnerChanged` event, with the previous and next owner, the retention and the note.

### Access Overrides UI

When adding a new assignment, each permission — `view`, `edit`, `transition` — has three readings: **the call decides** (no override stored), **allowed** (`true`) and **shut out** (`false`). Every reading is a legitimate answer: all three on "the call decides" still creates the row, because the type of the assignment matters to the rules that ask for an assignee.

In the list, each override badge cycles through its three readings on click, colored gray (call decides), green (allowed) and red (shut out). A denial holds over every rule of the state, and over a grant of the same kind.

### Tenant Awareness

In a multi-tenant panel, the user dropdown is automatically scoped to the current tenant using the `tenant_user_relationship` config key (default: `'users'`). Configure it in `config/filament-flow.php`:

```php
'tenant_user_relationship' => 'members',
```

### Custom Badge View

The `AssignmentManager` Livewire component accepts an `assignmentBadgesView` property to render a custom Blade view for each assignment row's badge area. Pass it via the Livewire component parameters:

```php
use Filament\Schemas\Components\Livewire;
use RoBYCoNTe\FilamentFlow\Livewire\AssignmentManager;

Livewire::make(AssignmentManager::class, [
    'assignmentBadgesView' => 'my-app::assignment-badges',
])
    ->visible(fn (?Model $record) => $record !== null),
```

The custom view receives the assignment array as `$assignment` (same shape as described in the `metadataBadges` extension point documentation above).

## AccessControlAction

A Filament action that opens the assignment panel **as a dialog** — the same room as `AssignmentManager`, through a door: who holds the record, who works on it, and what each one may do. Mount it wherever a record exists (a table row, a record page, or a page that can answer for the record):

```php
use RoBYCoNTe\FilamentFlow\Actions\AccessControlAction;

AccessControlAction::make()
    ->roleLabels(RoleLabels::map())
    ->superAdminOnly(),
```

When the action does not sit beside the record (a page that keeps it in a property, for example), hand it over with `accessRecord()` — a model, its key, or the answer of a closure — and, when the key is all the host knows, `accessRecordType()`:

```php
AccessControlAction::make()
    ->accessRecord(fn (): Application => $this->application)
    ->superAdminOnly(),
```

The action hides itself from whoever may not manage assignments (or, with `superAdminOnly()`, from everyone but super administrators); the panel inside the dialog keeps its own protection either way.

The component inside the dialog gets a **key of its own** (`{action}.assignment-manager`), so it never shares the identity of another instance of the same class on the page. Remember it when embedding the panel twice — two sections of the same component on one page must each carry a distinct `->key()`, or Livewire answers the second one with an empty placeholder (the dialog opens, and it is empty).

## AssignmentSummaryEntry Infolist Component

A Filament infolist entry that renders a detailed assignment summary for the current record, including per-user permissions (view / edit / transition) derived from the current workflow state, access override indicators, and role-based access rules.

```php
use RoBYCoNTe\FilamentFlow\Infolists\Components\AssignmentSummaryEntry;

AssignmentSummaryEntry::make()
    ->stateColumn('status')            // column used to resolve current workflow state (default: 'state')
    ->roleLabels([                     // the words the host uses for its own roles
        'super_admin' => 'Super Administratore',
    ]),
```

The component carries a translated label by default (`Assignments` / `Assegnazioni`): name it or hide it as any other entry. On top of the people and their permissions, it says **which state** the permissions are read in — they change with it — and, for each person, **when** the case was given and **by whose hand**, when the assignment recorded it.

### Role labels

A role name (`super_admin`, `grant_operator`) is a key, not a sentence: `roleLabels()` takes the words of the host, as a map of names to labels or as a callback answering one name at a time.

```php
AssignmentSummaryEntry::make()
    ->roleLabels(fn (string $role): ?string => Role::tryFrom($role)?->label());
```

What the host does not name is asked of the translations — the name itself (`__('senior_collaborator')`), then the same headlined (`__('Senior Collaborator')`), which is where a host that writes its role labels down keeps them. What remains is the name read as it is written.

The same labels read in the panel that manages the assignments (`AssignmentManager`, which takes a `roleLabels` map), in the assignee select of a transition form (`AssigneeSelect::roleLabels()`) and in the avatars tooltip of the applications list (`AssignmentSummaryColumn::roleLabels()`): a panel that shows `super_admin` to an office reads like a database.

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
    'roles'            => ['Super Amministratore'],   // the labels of the roles, not their names
    'assigned_at'      => Illuminate\Support\Carbon,
    'assigned_by'      => 'Mario Rossi',              // the name of who gave the case, when recorded
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
