# State Access Control

Filament Flow provides a powerful state-based access control system that lets you define who can view, edit, or transition records based on their current workflow state.

## Access Rule Tokens

Access rules are defined using tokens that specify who has access. Multiple tokens can be combined using AND/OR logic.

| Token | Description | Applicable To |
|---|---|---|
| `*` | Everyone (including guests) | create, view, edit, transition |
| `@authenticated` | Any authenticated user | create, view, edit, transition |
| `@owner` | The owner of the record (uses `owner_field` config) | view, edit, transition* |
| `@assigned` | Any user assigned to the record | view, edit, transition* |
| `@assigned:type` | User assigned with a specific type (e.g., `@assigned:primary`) | view, edit, transition* |
| `role:name` | User with a specific role | create, view, edit, transition |
| `role:name1,name2` | User with any of the specified roles | create, view, edit, transition |
| `permission:name` | User with a specific permission | create, view, edit, transition |

> **Note:** `@owner` and `@assigned` tokens do not apply to **create** operations since the record doesn't exist yet. For create access rules, use `*`, `@authenticated`, `role:`, or `permission:` tokens.

**Examples:**

```php
// Only sales and admin roles can create new records
'create' => ['role:sales,admin']

// Anyone can view
'view' => ['*']

// Only authenticated users can edit
'edit' => ['@authenticated']

// Only the owner or assigned users can transition
'transition' => ['@owner', '@assigned']

// Only managers and admins can edit
'edit' => ['role:manager,admin']

// Only users with 'orders.approve' permission
'transition' => ['permission:orders.approve']

// Owner OR primary assignee (combined with OR logic)
'edit' => ['@owner', '@assigned:primary']
```

## Code-First Access Rules (Recommended)

Define access rules directly in your State PHP classes by implementing the `HasAccessRules` interface:

```php
<?php

namespace App\States\Order;

use RoBYCoNTe\FilamentFlow\Contracts\HasAccessRules;

final class PendingState extends OrderState implements HasAccessRules
{
    // ... existing methods (getLabel, getIcon, etc.)

    /**
     * Who can CREATE new records (only applies to the initial state)
     * Since new records start in the initial state, these rules are checked when creating.
     */
    public static function getCreateAccessRules(): array
    {
        return ['role:sales,admin']; // Only sales and admin can create orders
    }

    /**
     * Who can view records in this state
     */
    public static function getViewAccessRules(): array
    {
        return ['@authenticated']; // Any authenticated user
    }

    /**
     * Who can edit records in this state
     */
    public static function getEditAccessRules(): array
    {
        return ['@owner', '@assigned:primary']; // Owner OR primary assignee
    }

    /**
     * Who can transition records from this state
     */
    public static function getTransitionAccessRules(): array
    {
        return ['role:manager,admin']; // Only managers and admins
    }
}
```

**More restrictive state example:**

```php
final class ProcessingState extends OrderState implements HasAccessRules
{
    public static function getViewAccessRules(): array
    {
        return ['@authenticated'];
    }

    public static function getEditAccessRules(): array
    {
        return ['@assigned']; // Only assigned users
    }

    public static function getTransitionAccessRules(): array
    {
        return ['role:admin']; // Only admins can move to next state
    }
}
```

**Priority Resolution (Hybrid Mode):**

When both Code-First and Database rules exist, the system follows this priority:

1. **Code-First rules** (PHP class implementing `HasAccessRules`) — highest priority
2. **Database rules** (`workflow_state_access_rules` table)
3. **Config defaults** (`filament-flow.state_access.defaults`)

This allows you to:

- Start with Code-First rules in PHP for type safety
- Override or extend rules via database without code changes
- Use Database-First for states that don't have PHP classes

## Using HasStateAccess Trait

Add the `HasStateAccess` trait to your model to enable state-based access control:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Concerns\HasStateAccess;
use RoBYCoNTe\FilamentFlow\Concerns\HasWorkflowAssignments;
use Spatie\ModelStates\HasStates;

class Order extends Model
{
    use HasStates;
    use HasStateAccess;
    use HasWorkflowAssignments; // Required for @assigned tokens

    // ...
}
```

**Checking Access:**

```php
$user = auth()->user();

// Check if user can CREATE new records (static method - no record exists yet)
// This checks the creation access rules on the INITIAL state
if (Order::canBeCreatedBy($user)) {
    // User has permission to create new orders
}

$order = Order::find(1);

// Check if user can view
if ($order->canBeViewedBy($user)) {
    // User has view access
}

// Check if user can edit
if ($order->canBeEditedBy($user)) {
    // User has edit access
}

// Check if user can transition
if ($order->canBeTransitionedBy($user)) {
    // User has transition access
}

// Check transition to a specific state
if ($order->canBeTransitionedBy($user, 'shipped')) {
    // User can transition to shipped state
}
```

**Note on Create Access:**

The `canBeCreatedBy()` method is **static** because the record doesn't exist yet. It checks the creation access rules defined on the **initial state** of the workflow. This is useful for:

- Checking if a user can access the "Create" page
- Conditionally showing/hiding "Create" buttons
- Pre-validating permissions before creating records

```php
// In a Filament Resource
public static function canCreate(): bool
{
    return Order::canBeCreatedBy(auth()->user());
}

// In a Livewire component
@if(Order::canBeCreatedBy(auth()->user()))
    <a href="{{ route('orders.create') }}">Create Order</a>
@endif
```

## Database-Configured Access Rules

Access rules can be configured in the database via the `workflow_state_access_rules` table, providing dynamic access control without code changes.

**Migration (automatically included):**

```php
Schema::create('workflow_state_access_rules', function (Blueprint $table) {
    $table->id();
    $table->foreignId('workflow_state_id')->constrained('workflow_states')->cascadeOnDelete();
    $table->string('access_type'); // 'create', 'view', 'edit', 'transition'
    $table->string('rule'); // The access token
    $table->string('operator')->default('or'); // 'or' or 'and'
    $table->integer('priority')->default(0);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

> **Note:** For the 'create' access type, rules should be added to the **initial state** of the workflow, as this determines who can create new records.

**Creating Access Rules:**

```php
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateAccessRule;

// Create a rule: Only owner can edit pending orders
WorkflowStateAccessRule::create([
    'workflow_state_id' => $pendingState->id,
    'access_type' => 'edit',
    'rule' => '@owner',
    'is_active' => true,
]);

// Create a rule: Managers can edit processing orders
WorkflowStateAccessRule::create([
    'workflow_state_id' => $processingState->id,
    'access_type' => 'edit',
    'rule' => 'role:manager',
    'is_active' => true,
]);

// Create a rule: Only assigned users can transition
WorkflowStateAccessRule::create([
    'workflow_state_id' => $pendingState->id,
    'access_type' => 'transition',
    'rule' => '@assigned',
    'is_active' => true,
]);
```

## Querying Accessible Records

Use query scopes to retrieve only records the user can access:

```php
// Get orders visible to the current user
$visibleOrders = Order::visibleTo(auth()->user())->get();

// Get orders editable by a specific user
$editableOrders = Order::editableBy($user)->get();

// Combine with other queries
$pendingOrders = Order::visibleTo($user)
    ->where('state', 'pending')
    ->orderBy('created_at', 'desc')
    ->get();
```

**Using the Service Directly:**

```php
use RoBYCoNTe\FilamentFlow\Services\WorkflowStateAccessService;

$service = app(WorkflowStateAccessService::class);

// Check access
$canView = $service->canView($order, $user);
$canEdit = $service->canEdit($order, $user);
$canTransition = $service->canTransition($order, $user);

// Scope queries
$query = Order::query();
$service->scopeAccessible($query, $user, 'view');
$accessibleOrders = $query->get();
```

## Configuration

Configure state access control in `config/filament-flow.php`:

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

## Automatic Enforcement

When `enforce_on_transition` is enabled (default), the system automatically checks access permissions before allowing any state transition. If a user doesn't have permission, an `UnauthorizedTransitionException` is thrown.

**Basic Usage with Enforcement:**

```php
use RoBYCoNTe\FilamentFlow\Exceptions\UnauthorizedTransitionException;

$order = Order::find(1);

try {
    // Specify which user is performing the transition
    $order->asUser($user)->transitionTo(ProcessingState::class);
} catch (UnauthorizedTransitionException $e) {
    // Handle unauthorized access
    $message = $e->getMessage();
    $record = $e->getRecord();
    $fromState = $e->getFromState();
    $toState = $e->getToState();
    $user = $e->getUser();
}
```

**Using Current Authenticated User:**

```php
// If no user is specified, the authenticated user is used
$order->transitionTo(ProcessingState::class); // Uses auth()->user()
```

**Bypassing Access Control:**

For system-level operations (scheduled tasks, migrations, etc.), use `forceTransitionTo()`:

```php
// Bypass access control checks entirely
$order->forceTransitionTo(ProcessingState::class);
```

**Handling in Filament Actions:**

```php
use RoBYCoNTe\FilamentFlow\Exceptions\UnauthorizedTransitionException;

StateAction::make('process')
    ->label('Process Order')
    ->transitionTo(ProcessingState::class)
    ->action(function (Order $record) {
        try {
            $record->asUser(auth()->user())->transitionTo(ProcessingState::class);

            Notification::make()
                ->success()
                ->title('Order processed')
                ->send();
        } catch (UnauthorizedTransitionException $e) {
            Notification::make()
                ->danger()
                ->title('Access Denied')
                ->body('You do not have permission to process this order.')
                ->send();
        }
    });
```

**Disabling Enforcement:**

If you prefer to handle access control manually:

```php
// In config/filament-flow.php
'state_access' => [
    'enforce_on_transition' => false,
],

// Then check manually in your code
if ($order->canBeTransitionedBy($user)) {
    $order->transitionTo(ProcessingState::class);
} else {
    // Handle unauthorized access
}
```

## Custom Role and Permission Resolvers

You can create custom resolvers for role and permission checking to integrate with your authentication system.

**Role Resolver:**

```php
<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Contracts\RoleResolver;

class CustomRoleResolver implements RoleResolver
{
    public function getRoles(Model $user): array
    {
        // Return array of role names for the user
        return $user->roles->pluck('name')->toArray();
    }

    public function hasAnyRole(Model $user, array $roles): bool
    {
        return !empty(array_intersect($this->getRoles($user), $roles));
    }

    public function isSuperAdmin(Model $user): bool
    {
        return $this->hasAnyRole($user, config('filament-flow.state_access.super_admin_roles', []));
    }
}
```

**Permission Resolver:**

```php
<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Contracts\PermissionResolver;

class CustomPermissionResolver implements PermissionResolver
{
    public function hasPermission(Model $user, string $permission): bool
    {
        // Check if user has the permission
        return $user->permissions->contains('name', $permission);
    }
}
```

**Register Custom Resolvers:**

```php
// config/filament-flow.php
'state_access' => [
    'role_resolver' => \App\Support\CustomRoleResolver::class,
    'permission_resolver' => \App\Support\CustomPermissionResolver::class,
],
```

**Default Resolvers:**

The package includes default resolvers that support:

- **Spatie Permission** package (if installed)
- Laravel's built-in `Gate` for permissions
- Custom `getRoles()` method on user models
