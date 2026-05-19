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
