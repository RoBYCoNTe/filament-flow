# Database-Driven Workflows

Filament Flow extends Spatie's code-first approach with powerful database-driven workflow capabilities. This allows you to:

- Define workflows entirely in the database without PHP classes
- Mix PHP state classes with database-only states
- Configure transitions, permissions, and forms dynamically
- Update workflows without code deployments

## FlexibleStateCast

The `FlexibleStateCast` allows models to handle both PHP State classes and database-only states (strings).

**Usage:**

```php
<?php

namespace App\Models;

use App\States\Order\OrderState;
use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Casts\FlexibleStateCast;
use Spatie\ModelStates\HasStates;

class Order extends Model
{
    use HasStates;

    protected $casts = [
        // Use FlexibleStateCast with the base state class
        'state' => FlexibleStateCast::class . ':' . OrderState::class,
    ];
}
```

**How it works:**

- When retrieving a state value from the database:
    - If it matches a PHP State class, returns a State instance
    - If it doesn't match any PHP class, returns the string as-is
- This enables you to have some states defined in PHP and others only in the database

## HasDatabaseTransitions Trait

The `HasDatabaseTransitions` trait enables models to use transitions configured in the database, alongside or instead of Spatie's code-based transitions.

**Usage:**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Concerns\HasDatabaseTransitions;
use Spatie\ModelStates\HasStates;

class Order extends Model
{
    use HasStates;
    use HasDatabaseTransitions;
}
```

**Features:**

- **Hybrid Transitions**: Checks Spatie's PHP config first, then falls back to database config
- **Database-Only States**: Supports transitions to/from states that don't have PHP classes
- **Transition Data**: Applies form data to model fields during transitions
- **Validation**: Automatically validates transitions based on database configuration

**Methods:**

```php
// Check if a transition is allowed (checks both PHP and database config)
$order->canTransitionTo('shipped'); // true/false

// Execute a transition (tries PHP config first, then database)
$order->transitionTo('shipped', ['tracking_number' => 'ABC123']);

// Database-specific methods (used internally)
$order->canTransitionToFromDatabase($fromState, $toState, 'state');
$order->canTransitionToFromDatabaseString('pending', 'shipped', 'state');
```

## Auto-Initial State on Creation

When `HasDatabaseTransitions` is booted, it automatically sets the model's state field to the initial state on `creating` if no state is already set. This means you do not need to manually set the state when creating records — the workflow handles it automatically.

```php
// State is set automatically — no need for 'state' => PendingState::class
$order = Order::create([
    'customer_id' => 1,
    'amount' => 250.00,
]);

// $order->state is already the initial state
```

The initial state is determined by the `WorkflowState` with `is_initial = true` for the model's workflow. If the state field already has a value (e.g. set by a factory or seeder), the automatic assignment is skipped.

## Querying Available Transitions

Use these methods to discover what the current user can do from the record's current state:

```php
// Get all state-changing transitions available in current state
$order->getAvailableTransitions(); // Collection<WorkflowTransition>

// Get all in-state actions available in current state (to_state_id = null)
$order->getAvailableActions(); // Collection<WorkflowTransition>
```

Both methods filter by the record's current state, evaluate transition conditions, and check transition-level permissions for the current user (or the user set via `asUser()`). See [Transition Conditions & In-State Actions](./conditions.md) for details.

## Workflow Creation Policy

Workflows support a `creation_policy` JSON field that controls automatic behavior on record creation.

```json
{
    "auto_assign_creator": true
}
```

When `auto_assign_creator` is `true`, the authenticated user is automatically assigned as `primary` assignee when a new record is created via `WorkflowCreationService`. This pairs with the `HasWorkflowAssignments` trait and enables `@assigned` access rule tokens to work immediately after creation.

## HasFlexibleStates Trait

The `HasFlexibleStates` trait overrides Laravel's attribute casting to support database-only states.

**Usage:**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Concerns\HasFlexibleStates;
use Spatie\ModelStates\HasStates;

class Order extends Model
{
    use HasStates;
    use HasFlexibleStates;

    // Specify which fields should support flexible states
    protected array $flexibleStateFields = ['state', 'payment_state'];
}
```

**How it works:**

- Intercepts attribute getting/setting before Spatie's casting
- If a state value doesn't resolve to a PHP class, returns/stores it as a string
- Prevents errors when working with database-only states

**Use Cases:**

- Workflows that need to be configurable without code changes
- Multi-tenant applications where each tenant has different states
- Rapid prototyping before creating formal State classes

## Additional Database-Driven Features

When you enable database-driven workflows by running migrations, you gain access to additional advanced features:

### Field Permissions

- Control which fields are visible, editable, or required based on the current state
- Define permissions per role for fine-grained access control
- Example: Make "cancellation_reason" required only when transitioning to "cancelled" state

### Workflow Assignments

- Assign workflows to specific users or teams
- Control who can execute specific transitions
- Example: Only managers can transition orders to "approved" state

### Notifications

- Configure automated notifications for state transitions
- Support for multiple channels (email, database, SMS, etc.)
- Define recipient lists per transition
- Example: Notify customer when order state changes to "shipped"

### Transition History

- Automatic audit trail of all state transitions
- Track who made the transition, when, and with what data
- Snapshots of model state before/after transitions
- Example: See complete history of an order's state changes

### Database-Configured Transition Forms

- Define form fields in the database instead of PHP Transition classes
- Configure validation rules dynamically
- Map form fields to model attributes
- Example: Add "tracking_number" field to ship transition without touching code

> **Note:** These advanced features require the database migrations and are designed for applications that need highly configurable workflows. For simpler use cases, the PHP-based approach with Spatie States is sufficient.
