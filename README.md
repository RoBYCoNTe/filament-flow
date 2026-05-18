# Filament Flow

**A powerful Business Process Manager for Filament to handle model state transitions and workflows with ease.**

Filament Flow seamlessly integrates [Spatie Laravel Model States](https://spatie.be/docs/laravel-model-states) into
your [FilamentPHP](https://filamentphp.com/) admin panel, providing a complete workflow management solution with visual
state transitions, custom forms, and intuitive UI components.

**Key Capabilities:**

- **Code-First Workflows**: Define states and transitions in PHP using Spatie's State pattern
- **Database-Driven Workflows**: Configure workflows entirely through the database without PHP classes
- **Hybrid Approach**: Mix PHP state classes with database-only states for maximum flexibility

Perfect for order processing, publishing workflows, approval systems, and any application requiring well-defined state
management.

---

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Filament Admin Panel Integration](#filament-admin-panel-integration)
    - [Registering the Plugin](#registering-the-plugin)
    - [Multi-Tenant Configuration](#multi-tenant-configuration)
    - [Disabling the Workflow Resource](#disabling-the-workflow-resource)
- [Basic Configuration](#basic-configuration)
    - [Database Setup](#database-setup)
    - [Creating State Classes](#creating-state-classes)
    - [Configuring Your Model](#configuring-your-model)
- [Database-Driven Workflows](#database-driven-workflows-advanced)
    - [FlexibleStateCast](#flexiblestatecast)
    - [HasDatabaseTransitions Trait](#hasdatabasetransitions-trait)
    - [HasFlexibleStates Trait](#hasflexiblestates-trait)
- [State Access Control](#state-access-control)
    - [Access Rule Tokens](#access-rule-tokens)
    - [Code-First Access Rules](#code-first-access-rules-recommended)
    - [Using HasStateAccess Trait](#using-hasstateaccess-trait)
    - [Database-Configured Access Rules](#database-configured-access-rules)
    - [Querying Accessible Records](#querying-accessible-records)
    - [Custom Role and Permission Resolvers](#custom-role-and-permission-resolvers)
- [Workflow Notifications](#workflow-notifications)
    - [Notification Triggers](#notification-triggers)
    - [Notification Channels](#notification-channels)
    - [Recipient Configuration](#recipient-configuration)
    - [Notification Templates](#notification-templates)
    - [Notification Timing](#notification-timing)
    - [Configuration Options](#notification-configuration-options)
    - [Code-First Notifications](#code-first-notifications)
- [Usage Examples](#usage-examples)
    - [Form Components](#form-components)
    - [Table Columns](#table-columns)
    - [Custom State Sorting](#custom-state-sorting)
    - [Table Filters](#table-filters)
    - [Table Grouping](#table-grouping)
    - [Listing Tabs](#listing-tabs)
    - [Actions](#actions)
    - [Custom Transitions](#custom-transitions)
- [Complete Example: Order Workflow](#complete-example-order-workflow)
- [Configuration Options](#configuration-options)
- [API Reference](#api-reference)
- [Contributing](#contributing)
- [License](#license)

---

## Overview

Filament Flow builds on [Spatie Laravel Model States](https://spatie.be/docs/laravel-model-states) to provide:

- **State Classes**: Each state is a separate class with its own behavior and logic
- **State Transitions**: Define which state changes are allowed with validation
- **Transition Classes**: Optional classes for complex transitions that need additional data or logic

**Example**: An order progresses through states like `Pending` → `Processing` → `Shipped` → `Delivered`, with validation
ensuring only valid transitions occur.

---

## Features

✨ **Rich State Management**

- Display model states with colors, icons, and descriptions
- Filter and group records by state
- Transition between states using intuitive UI components
- Bulk state transitions with validation
- **Custom sort order for states in tables**
- Mix PHP state classes with database-only states

🛠 **Developer Experience**

- Out-of-the-box support for Spatie Laravel Model States
- **Database-driven workflow configuration** (states, transitions, permissions)
- Custom transition forms for collecting additional data
- Automatic state validation and transition rules
- Field-level permissions per state
- Workflow assignments and notifications
- Transition history tracking
- Compatible with Filament v4 and dark mode 
- DRY architecture with reusable traits

🎨 **Customizable Interface**

- Custom labels, colors, icons, and descriptions for states
- Custom transition forms and validation
- Flexible attribute mapping for complex models
- Confirmation dialogs for sensitive transitions
- Sortable state columns with workflow-based ordering

🗄️ **Database-Driven Workflows** (Advanced)

- Define states and transitions entirely in the database
- Database-only states (no PHP classes required)
- Dynamic workflow configuration without code changes
- Field permissions per state and role
- Workflow assignments to users/teams
- Notification system for state transitions
- Complete transition history and audit trail

🔒 **State-Based Access Control**

- Define who can view, edit, or transition records based on state
- Flexible access rule tokens (`@authenticated`, `@owner`, `@assigned`, `role:`, `permission:`)
- Support for assignment-based access with type filtering
- Super admin bypass for full access
- Query scopes for retrieving accessible records
- Extensible with custom role and permission resolvers
- Compatible with Spatie Permission package

---

## Requirements

- PHP: ^8.2
- Laravel: ^11.0|^12.0
- Filament: ^4.0
- Spatie Laravel Model States: ^2.12

---

## Installation

Install the package via Composer:

```bash
composer require robyconte/filament-flow
```

The package will automatically register its service provider.

### Publishing Migrations

To publish the migrations so they are visible and executable in your main application, run:

```bash
php artisan vendor:publish --provider="RoBYCoNTe\\FilamentFlow\\FilamentFlowServiceProvider" --tag="filament-flow-migrations"
```

This will copy all plugin migrations to your `database/migrations` directory.

Alternatively, if you want to load migrations automatically without publishing them, the plugin will load them automatically on every migrate command.

**Run migrations** (optional, required only for database-driven workflows):

```bash
php artisan migrate
```

This will create the following tables:

- `workflows` - Workflow definitions
- `workflow_states` - State definitions
- `workflow_transitions` - Transition configurations
- `workflow_field_permissions` - Field-level permissions
- `workflow_assignments` - User/team assignments
- `workflow_notifications` - Notification configurations
- `workflow_transition_history` - Audit trail
- `workflow_state_access_rules` - State-based access control rules

**Publish configuration** (optional):

```bash
php artisan vendor:publish --tag="filament-flow-config"
```

> **New to Spatie Laravel Model States?** Read
> their [introduction](https://spatie.be/docs/laravel-model-states/v2/01-introduction) first to understand states,
> transitions, and the state pattern.

---

## Filament Admin Panel Integration

Filament Flow provides a complete admin interface for managing workflows directly from your Filament panel. This allows you to:

- Create and edit workflows through a visual interface
- Manage states with drag-and-drop reordering
- Configure transitions between states
- Set up notifications for workflow events

### Registering the Plugin

Register the plugin in your Filament panel provider:

```php
use RoBYCoNTe\FilamentFlow\FilamentFlowPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->plugins([
                FilamentFlowPlugin::make(),
            ]);
    }
}
```

Once registered, a new "Workflow" navigation group will appear in your panel with access to the Workflows resource.

**Access URL**: `/your-panel-path/workflows` (or `/your-panel-path/{tenant}/workflows` for multi-tenant panels)

### Multi-Tenant Configuration

Filament Flow supports both **global workflows** (shared across all tenants) and **tenant-specific workflows** (each tenant manages their own).

#### Global Workflows (Default)

By default, workflows are global and not scoped to any tenant. This is ideal when workflows define application-wide business logic:

```php
FilamentFlowPlugin::make()
    ->global() // Explicit, but this is the default
```

#### Tenant-Aware Workflows

Enable tenant-aware mode when each tenant should be able to customize or create their own workflows:

```php
FilamentFlowPlugin::make()
    ->tenantAware()
    ->tenantModel(Company::class)      // Optional: override config
    ->tenantColumn('company_id')       // Optional: override config
```

You can also configure this in the config file:

```php
// config/filament-flow.php
return [
    'tenant_model' => App\Models\Company::class,
    'tenant_foreign_key' => 'tenant_id',
];
```

#### Workflow Resolution with Fallback

When tenant-aware mode is enabled, Filament Flow uses a **fallback strategy** to find workflows:

1. **First**: Look for a tenant-specific workflow (matching the current tenant)
2. **Fallback**: If not found, use the global workflow (`tenant_id = null`)

This allows you to:
- Define global "base" workflows that apply to all tenants
- Let specific tenants override with their own customized workflows

**Example**: A global "Order Processing" workflow applies to all companies, but Company A can create their own version with additional states.

### Disabling the Workflow Resource

If you only want to use Filament Flow programmatically without the admin interface:

```php
FilamentFlowPlugin::make()
    ->withoutWorkflowResource()
```

To re-enable it:

```php
FilamentFlowPlugin::make()
    ->withWorkflowResource()
```

---

## Basic Configuration

### Database Setup

Your database table must have a string column to store the state. Additionally, you may want to add timestamp columns to
track when specific states were reached.

**Example migration:**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->text('customer_address')->nullable();
            $table->decimal('total_amount', 10, 2)->default(0);
            
            // Required: State column
            $table->string('state');
            
            // Optional: Timestamp columns for tracking state changes
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            
            // Optional: Additional state-related data
            $table->text('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
```

**Required columns:**

- `state` (string) - Stores the fully qualified class name of the current state

**Recommended columns:**

- State-specific timestamp columns (e.g., `processed_at`, `shipped_at`)
- Additional data columns for transition information (e.g., `cancellation_reason`)
- A `notes` or `comments` text column for general information

---

### Creating State Classes

#### 1. Create the Abstract State Class

First, create an abstract state class that implements `HasStateMetadata` and uses the `HasStateMetadata` trait:

```php
<?php
// app/States/Order/OrderState.php

namespace App\States\Order;

use RoBYCoNTe\FilamentFlow\Concerns\HasStateMetadata;
use RoBYCoNTe\FilamentFlow\Contracts\HasStateMetadata as HasStateMetadataContract;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class OrderState extends State implements HasStateMetadataContract
{
    use HasStateMetadata;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(PendingState::class)
            ->allowTransition(PendingState::class, ProcessingState::class, ProcessTransition::class)
            ->allowTransition(ProcessingState::class, ShippedState::class, ShipTransition::class)
            ->allowTransition(ShippedState::class, DeliveredState::class)
            ->allowTransition([PendingState::class, ProcessingState::class], CancelledState::class, CancelTransition::class);
    }
}
```

#### 2. Create Concrete State Classes

Each state should implement the UI metadata interfaces for a rich visual experience:

```php
<?php
// app/States/Order/PendingState.php

namespace App\States\Order;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

final class PendingState extends OrderState implements HasLabel, HasIcon, HasColor, HasDescription
{
    public function getLabel(): string
    {
        return __("Pending");
    }

    public function getIcon(): string
    {
        return Heroicon::Clock;
    }

    public function getColor(): string|array
    {
        return Color::Amber;
    }

    public function getDescription(): string
    {
        return __("The order is pending and awaiting processing.");
    }
}
```

**Available interfaces:**

- `HasLabel` - Display name for the state
- `HasIcon` - Icon using Heroicon enum or string
- `HasColor` - Color using Filament's Color helper
- `HasDescription` - Optional description text

---

### Configuring Your Model

Add the `HasStates` trait and cast your state column:

```php
<?php
// app/Models/Order.php

namespace App\Models;

use App\States\Order\OrderState;
use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStates\HasStates;

class Order extends Model
{
    use HasStates;

    protected $fillable = [
        'order_number',
        'customer_name',
        'customer_email',
        'customer_address',
        'total_amount',
        'state',
        'notes',
        'processed_at',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'state' => OrderState::class,
        'total_amount' => 'decimal:2',
        'processed_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];
}
```

---

## Database-Driven Workflows (Advanced)

Filament Flow extends Spatie's code-first approach with powerful database-driven workflow capabilities. This allows you
to:

- Define workflows entirely in the database without PHP classes
- Mix PHP state classes with database-only states
- Configure transitions, permissions, and forms dynamically
- Update workflows without code deployments

### FlexibleStateCast

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

### HasDatabaseTransitions Trait

The `HasDatabaseTransitions` trait enables models to use transitions configured in the database, alongside or instead of
Spatie's code-based transitions.

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

### HasFlexibleStates Trait

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

### Additional Database-Driven Features

When you enable database-driven workflows by running migrations, you gain access to additional advanced features:

**Field Permissions**

- Control which fields are visible, editable, or required based on the current state
- Define permissions per role for fine-grained access control
- Example: Make "cancellation_reason" required only when transitioning to "cancelled" state

**Workflow Assignments**

- Assign workflows to specific users or teams
- Control who can execute specific transitions
- Example: Only managers can transition orders to "approved" state

**Notifications**

- Configure automated notifications for state transitions
- Support for multiple channels (email, database, SMS, etc.)
- Define recipient lists per transition
- Example: Notify customer when order state changes to "shipped"

**Transition History**

- Automatic audit trail of all state transitions
- Track who made the transition, when, and with what data
- Snapshots of model state before/after transitions
- Example: See complete history of an order's state changes

**Database-Configured Transition Forms**

- Define form fields in the database instead of PHP Transition classes
- Configure validation rules dynamically
- Map form fields to model attributes
- Example: Add "tracking_number" field to ship transition without touching code

> **Note:** These advanced features require the database migrations and are designed for applications that need highly
> configurable workflows. For simpler use cases, the PHP-based approach with Spatie States is sufficient.

---

## State Access Control

Filament Flow provides a powerful state-based access control system that lets you define who can view, edit, or
transition records based on their current workflow state.

### Access Rule Tokens

Access rules are defined using tokens that specify who has access. Multiple tokens can be combined using AND/OR logic.

| Token              | Description                                                    | Applicable To                  |
|--------------------|----------------------------------------------------------------|--------------------------------|
| `*`                | Everyone (including guests)                                    | create, view, edit, transition |
| `@authenticated`   | Any authenticated user                                         | create, view, edit, transition |
| `@owner`           | The owner of the record (uses `owner_field` config)            | view, edit, transition*        |
| `@assigned`        | Any user assigned to the record                                | view, edit, transition*        |
| `@assigned:type`   | User assigned with a specific type (e.g., `@assigned:primary`) | view, edit, transition*        |
| `role:name`        | User with a specific role                                      | create, view, edit, transition |
| `role:name1,name2` | User with any of the specified roles                           | create, view, edit, transition |
| `permission:name`  | User with a specific permission                                | create, view, edit, transition |

> **Note:** `@owner` and `@assigned` tokens do not apply to **create** operations since the record doesn't exist yet.
> For create access rules, use `*`, `@authenticated`, `role:`, or `permission:` tokens.

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

### Code-First Access Rules (Recommended)

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

**More restrictives state example:**

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

1. **Code-First rules** (PHP class implementing `HasAccessRules`) - highest priority
2. **Database rules** (`workflow_state_access_rules` table)
3. **Config defaults** (`filament-flow.state_access.defaults`)

This allows you to:

- Start with Code-First rules in PHP for type safety
- Override or extend rules via database without code changes
- Use Database-First for states that don't have PHP classes

### Using HasStateAccess Trait

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

The `canBeCreatedBy()` method is **static** because the record doesn't exist yet. It checks the creation access rules
defined on the **initial state** of the workflow. This is useful for:

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

### Database-Configured Access Rules

Access rules can be configured in the database via the `workflow_state_access_rules` table, providing dynamic access
control without code changes.

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

> **Note:** For the 'create' access type, rules should be added to the **initial state** of the workflow, as this
> determines who can create new records.

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

### Querying Accessible Records

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

### Configuration

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

### Automatic Enforcement

When `enforce_on_transition` is enabled (default), the system automatically checks access permissions before allowing
any state transition. If a user doesn't have permission, an `UnauthorizedTransitionException` is thrown.

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

### Custom Role and Permission Resolvers

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

### Managing Database-Driven Workflows in Filament

Filament Flow provides built-in resources to manage workflows directly from your admin panel.

**Enabling Workflow Resources**

Register the plugin in your Filament Panel provider and enable workflow resources:

```php
<?php
// app/Providers/Filament/AdminPanelProvider.php

use Filament\Panel;
use RoBYCoNTe\FilamentFlow\FilamentFlowPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ... other configuration
        ->plugins([
            FilamentFlowPlugin::make()
                ->workflowResources(), // Enable workflow management UI
        ]);
}
```

This adds three resources to your admin panel:

1. **Workflows** - Create and manage workflow definitions
2. **Workflow States** - Define states for each workflow
3. **Workflow Transitions** - Configure allowed transitions between states

**Creation Order**

Follow this order when creating a new database-driven workflow:

1. **Create a Workflow** (required first)
    - Navigate to "Workflows" in your admin panel
    - Click "Create"
    - Fill in:
        - **Workflow Name**: Descriptive name (e.g., "Order Processing")
        - **Model Class**: Full class name (e.g., `App\Models\Order`)
        - **State Column**: Database column name (default: `state`)
        - **Active**: Whether this workflow is currently active

2. **Add States** (required second)
    - Navigate to "Workflow States"
    - Click "Create"
    - Fill in:
        - **Workflow**: Select the workflow you just created
        - **State Name (Key)**: Unique identifier (e.g., `pending`, `processing`)
        - **Display Label**: Human-readable name (e.g., "Pending", "Processing")
        - **PHP State Class**: Optional - leave empty for database-only states
        - **Color**: Badge color for the UI
        - **Icon**: Optional Heroicon name
        - **Sort Order**: Display order (lower = first)
        - **Initial State**: Check for the default state of new records
        - **Final State**: Check if this is a terminal state
    - Repeat for all states in your workflow

3. **Configure Transitions** (required third)
    - Navigate to "Workflow Transitions"
    - Click "Create"
    - Fill in:
        - **Workflow**: Select your workflow
        - **From State**: Source state
        - **To State**: Target state
        - **Transition Name (Key)**: Unique identifier (e.g., `process`, `cancel`)
        - **Display Label**: Button label (e.g., "Mark as Processing")
        - **PHP Transition Class**: Optional - leave empty for database-only
        - **Requires Confirmation**: Show confirmation dialog
        - **Requires Reason**: Require user to provide a reason
    - Repeat for all allowed transitions

**Example: Creating an Order Workflow**

```
Step 1: Create Workflow
- Name: "Order Processing"
- Model: "App\Models\Order"
- State Column: "state"

Step 2: Create States
- pending (gray, is_initial=true, sort_order=1)
- processing (blue, sort_order=2)
- shipped (info, sort_order=3)
- delivered (success, sort_order=4, is_final=true)
- cancelled (danger, sort_order=100, is_final=true)

Step 3: Create Transitions
- pending → processing (name: "process", label: "Mark as Processing")
- processing → shipped (name: "ship", label: "Ship Order")
- shipped → delivered (name: "deliver", label: "Mark as Delivered")
- pending → cancelled (name: "cancel", label: "Cancel Order", requires_reason=true)
- processing → cancelled (name: "cancel", label: "Cancel Order", requires_reason=true)
```

**Customizing Navigation**

The workflow resource is grouped under "Workflow" by default. You can customize the navigation through the plugin's fluent API:

```php
use Filament\Support\Icons\Heroicon;

FilamentFlowPlugin::make()
    ->navigationGroup('System Configuration')
    ->navigationLabel('Workflows')
    ->navigationIcon(Heroicon::OutlinedCog6Tooth)
    ->navigationSort(10)
    ->navigationParentItem('Settings')
```

All navigation methods are optional — only set the ones you need to customize.

**Conditional Display**

You can conditionally show/hide workflow resources:

```php
FilamentFlowPlugin::make()
    ->workflowResources(
        auth()->user()->hasRole('admin') // Only show to admins
    )
```

---

## Workflow Notifications

Filament Flow includes a powerful notification system that automatically sends notifications when workflow events occur.
Notifications can be triggered on state transitions, state entry/exit, assignments, and field changes.

### Notification Triggers

Notifications can be configured to trigger on various workflow events:

| Trigger Event     | Description                            |
|-------------------|----------------------------------------|
| `on_transition`   | When a specific transition is executed |
| `on_state_enter`  | When a record enters a specific state  |
| `on_state_exit`   | When a record exits a specific state   |
| `on_assignment`   | When a user is assigned to a record    |
| `on_field_change` | When specific fields are modified      |

**Creating a Notification Configuration:**

```php
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotification;

// Notify when an order transitions to process
WorkflowNotification::create([
    'workflow_id' => $workflow->id,
    'transition_id' => $transition->id,  // Optional: specific transition
    'state_id' => $processingState->id,   // Optional: specific state
    'trigger_event' => 'on_transition',
    'name' => 'Order Processing Notification',
    'is_active' => true,
    'timing' => 'immediate',              // or 'delayed'
    'priority' => 'medium',               // low, medium, high, urgent
]);
```

### Notification Channels

Filament Flow supports multiple notification channels:

| Channel    | Description                                             |
|------------|---------------------------------------------------------|
| `database` | Laravel database notifications (Filament notifications) |
| `mail`     | Email notifications                                     |

**Configuring Channels:**

```php
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotificationChannel;

// Add database channel
WorkflowNotificationChannel::create([
    'notification_id' => $notification->id,
    'channel_type' => 'database',
    'is_active' => true,
]);

// Add email channel
WorkflowNotificationChannel::create([
    'notification_id' => $notification->id,
    'channel_type' => 'mail',
    'is_active' => true,
    'channel_config' => [
        'from_address' => 'noreply@example.com',
        'from_name' => 'Order System',
    ],
]);

```

### Recipient Configuration

Define who should receive notifications using various recipient strategies:

| Recipient Type     | Description                                   | Configuration                           |
|--------------------|-----------------------------------------------|-----------------------------------------|
| `user`             | Specific users by ID                          | `['user_ids' => [1, 2, 3]]`             |
| `role`             | Users with specific roles                     | `['roles' => ['admin', 'manager']]`     |
| `record_owner`     | The owner of the record                       | `['owner_field' => 'user_id']`          |
| `assigned_users`   | Users assigned to the record                  | `['types' => ['primary', 'secondary']]` |
| `all_involved`     | All users who have interacted with the record | `[]`                                    |
| `involvement_type` | Users with a specific involvement type        | `['involvement_type' => 'reviewer']`    |
| `custom_field`     | User(s) from a custom record field            | `['field' => 'approver_id']`            |
| `custom_class`     | Custom resolver class                         | `['class' => 'App\\Resolvers\\Custom']` |

**Creating Recipients:**

```php
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotificationRecipient;

// Notify specific users
WorkflowNotificationRecipient::create([
    'notification_id' => $notification->id,
    'recipient_type' => 'user',
    'recipient_config' => ['user_ids' => [1, 2, 3]],
]);

// Notify all admins
WorkflowNotificationRecipient::create([
    'notification_id' => $notification->id,
    'recipient_type' => 'role',
    'recipient_config' => ['roles' => ['admin']],
]);

// Notify the record owner
WorkflowNotificationRecipient::create([
    'notification_id' => $notification->id,
    'recipient_type' => 'record_owner',
    'recipient_config' => [],
]);

// Notify assigned users
WorkflowNotificationRecipient::create([
    'notification_id' => $notification->id,
    'recipient_type' => 'assigned_users',
    'recipient_config' => ['types' => ['primary']],
]);
```

### Notification Templates

Create templates with variable substitution for dynamic content:

```php
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotificationTemplate;

WorkflowNotificationTemplate::create([
    'notification_id' => $notification->id,
    'channel_id' => $channel->id,
    'subject' => 'Order {{order_number}} - Status Update',
    'title' => 'Order Status Changed',
    'body' => 'Order {{order_number}} for {{customer_name}} has been moved from {{from_state_label}} to {{to_state_label}}.',
    'action_text' => 'View Order',
    'action_url' => '{{app_url}}/orders/{{record_id}}',
    'template_engine' => 'plain',  // plain, blade, or mustache
]);
```

**Available Template Variables:**

| Variable               | Description                                |
|------------------------|--------------------------------------------|
| `{{record_id}}`        | The record's primary key                   |
| `{{record_type}}`      | The record's class name (short)            |
| `{{order_number}}`     | Any record field (uses field name)         |
| `{{customer_name}}`    | Any record field (uses field name)         |
| `{{from_state}}`       | The previous state class name              |
| `{{to_state}}`         | The new state class name                   |
| `{{from_state_label}}` | Human-readable label of the previous state |
| `{{to_state_label}}`   | Human-readable label of the new state      |
| `{{trigger}}`          | The trigger event type                     |
| `{{app_name}}`         | Application name from config               |
| `{{app_url}}`          | Application URL from config                |

**Template Engines:**

- `plain` - Simple `{{variable}}` or `{{ variable }}` substitution
- `blade` - Laravel Blade syntax with full Blade features
- `mustache` - Mustache syntax with HTML escaping (`{{var}}` escaped, `{{{var}}}` unescaped)

### Notification Timing

Control when notifications are sent:

| Timing      | Description                            |
|-------------|----------------------------------------|
| `immediate` | Send immediately when the event occurs |
| `delayed`   | Send after a specified delay           |

**Delayed Notifications:**

```php
WorkflowNotification::create([
    'workflow_id' => $workflow->id,
    'trigger_event' => 'on_state_enter',
    'state_id' => $pendingState->id,
    'name' => 'Reminder: Order Still Pending',
    'is_active' => true,
    'timing' => 'delayed',
    'delay_minutes' => 60,  // Send 1 hour after entering state
    'priority' => 'high',
]);
```

### Notification Configuration Options

Configure the notification system in `config/filament-flow.php`:

```php
'notifications' => [
    /**
     * Enable or disable the notification system globally.
     */
    'enabled' => true,

    /**
     * Default notification channel when none is specified.
     */
    'default_channel' => 'database',

    /**
     * Queue connection for async notifications.
     */
    'queue_connection' => null,

    /**
     * Queue name for notification jobs.
     */
    'queue_name' => null,

    /**
     * Default delay in minutes for delayed notifications.
     */
    'default_delay_minutes' => 0,

    /**
     * Number of retry attempts for failed notification jobs.
     */
    'retry_attempts' => 3,

    /**
     * Backoff time in seconds between retry attempts.
     */
    'retry_backoff' => 60,

    /**
     * Enable logging of all notification dispatches.
     */
    'logging_enabled' => true,

    /**
     * Channel-specific configuration.
     */
    'channels' => [
        'database' => [
            'enabled' => true,
        ],
        'mail' => [
            'enabled' => true,
            'from_address' => null,
            'from_name' => null,
        ],
    ],

    /**
     * Default template rendering engine.
     */
    'default_template_engine' => 'plain',
],
```

**Triggering Notifications Programmatically:**

```php
use RoBYCoNTe\FilamentFlow\Services\NotificationService;

$notificationService = app(NotificationService::class);

// Trigger for a transition
$notificationService->triggerForTransition(
    $order,
    $fromState,
    $toState,
    ['additional' => 'data']
);

// Trigger for state entry
$notificationService->triggerForStateEntry($order, $newState);

// Trigger for assignment
$notificationService->triggerForAssignment($order, $userId, 'primary');

// Trigger for field change
$notificationService->triggerForFieldChange($order, 'status', $oldValue, $newValue);
```

**Notification Logging:**

All notifications are logged in the `workflow_notification_logs` table with:

- `notification_id` - The notification configuration
- `user_id` - The recipient user
- `notifiable_type/id` - The record that triggered the notification
- `channel` - The delivery channel used
- `status` - pending, sent, failed, skipped
- `error_message` - Error details if failed
- `payload` - The notification data sent
- `sent_at` - When the notification was sent

### Code-First Notifications

In addition to database-configured notifications, you can define notifications directly in your State and Transition
classes using a fluent builder API.

**In State Classes (HasStateNotifications):**

```php
use RoBYCoNTe\FilamentFlow\Builders\WorkflowNotificationBuilder;
use RoBYCoNTe\FilamentFlow\Contracts\HasStateNotifications;
use Spatie\ModelStates\State;

class ProcessingState extends State implements HasStateNotifications
{
    // Notifications sent when entering this state
    public function onEnterNotifications(): array
    {
        return [
            WorkflowNotificationBuilder::make()
                ->channel('database')
                ->recipients(['@owner'])
                ->title('Order Processing Started')
                ->body('Your order {{order_number}} is now being processed.')
                ->priority('medium'),
        ];
    }

    // Notifications sent when exiting this state
    public function onExitNotifications(): array
    {
        return [
            WorkflowNotificationBuilder::make()
                ->channel('database')
                ->recipients(['@owner'])
                ->title('Processing Complete')
                ->body('Your order {{order_number}} has finished processing.'),
        ];
    }
}
```

**In Transition Classes (HasTransitionNotifications):**

```php
use RoBYCoNTe\FilamentFlow\Builders\WorkflowNotificationBuilder;
use RoBYCoNTe\FilamentFlow\Contracts\HasTransitionNotifications;
use Spatie\ModelStates\Transition;

class ProcessOrderTransition extends Transition implements HasTransitionNotifications
{
    public function notifications(): array
    {
        return [
            WorkflowNotificationBuilder::make()
                ->channel('database')
                ->recipients(['@owner', 'role:admin'])
                ->title('Order Transitioned')
                ->body('Order {{order_number}} has been moved to processing.')
                ->priority('high'),

            WorkflowNotificationBuilder::make()
                ->channel('mail')
                ->recipients(['role:warehouse'])
                ->subject('New Order to Process')
                ->title('Order Ready')
                ->body('Order {{order_number}} ({{customer_name}}) needs processing.')
                ->actionUrl('/orders/{{record_id}}', 'View Order'),
        ];
    }
}
```

**Code-First Recipient Formats:**

| Format                 | Description                                          |
|------------------------|------------------------------------------------------|
| `@owner`               | Record owner (via user_id or configured owner field) |
| `@assigned`            | Assigned users                                       |
| `@all_involved`        | All users involved with the record                   |
| `role:admin`           | Users with specific role                             |
| `role:admin,manager`   | Users with any of the specified roles                |
| `user:1`               | Specific user by ID                                  |
| `user:1,2,3`           | Multiple users by ID                                 |
| `involvement:reviewer` | Users involved as specific type                      |
| `fn($record) => ...`   | Custom callable resolver                             |

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

---

## Usage Examples

### Form Components

Filament Flow provides three form components for state selection:

#### StateSelect

A dropdown select component:

```php
use RoBYCoNTe\FilamentFlow\Forms\Components\StateSelect;

StateSelect::make('state')
    ->label('Order Status')
    ->required();
```

#### StateRadio

Radio buttons with descriptions:

```php
use RoBYCoNTe\FilamentFlow\Forms\Components\StateRadio;

StateRadio::make('state')
    ->label('Order Status')
    ->descriptions(); // Shows state descriptions
```

#### StateToggleButtons

Toggle buttons with colors and icons:

```php
use RoBYCoNTe\FilamentFlow\Forms\Components\StateToggleButtons;

StateToggleButtons::make('state')
    ->label('Order Status')
    ->inline(); // Display inline
```

**Complete form example:**

```php
<?php
// app/Filament/Resources/OrderResource/Schemas/OrderForm.php

namespace App\Filament\Resources\OrderResource\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use RoBYCoNTe\FilamentFlow\Forms\Components\StateToggleButtons;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                TextInput::make('order_number')
                    ->label('Order Number')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->default(fn() => 'ORD-' . strtoupper(uniqid())),

                StateToggleButtons::make('state'),

                TextInput::make('total_amount')
                    ->label('Total Amount')
                    ->required()
                    ->numeric()
                    ->prefix('€')
                    ->default(0.00),

                TextInput::make('customer_name')
                    ->label('Customer Name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('customer_email')
                    ->label('Customer Email')
                    ->email()
                    ->required(),

                Textarea::make('customer_address')
                    ->label('Customer Address')
                    ->rows(3)
                    ->columnSpanFull(),

                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
```

---

### Table Columns

#### StateSelectColumn

An interactive select column that allows changing states directly from the table:

```php
use RoBYCoNTe\FilamentFlow\Tables\Columns\StateSelectColumn;

StateSelectColumn::make('state')
    ->sortable() // Enable custom state-based sorting
    ->ignoreTransitions(); // Allows direct state changes without transitions
```

**Options:**

- `sortable()` - Enable sorting with custom workflow order (see [Custom State Sorting](#custom-state-sorting))
- `ignoreTransitions()` - Allow changing to any state, bypassing transition rules
- Without `ignoreTransitions()` - Only allowed transitions are available

#### StateColumn

A display-only column that shows states with badges, colors, and icons, with support for custom sorting:

```php
use RoBYCoNTe\FilamentFlow\Tables\Columns\StateColumn;

StateColumn::make('state')
    ->label('Status')
    ->sortable(); // Enables custom state-based sorting
```

**Key Features:**

- **Automatic Badge Display**: Shows state as a colored badge
- **Color & Icon Support**: Automatically uses colors and icons from state metadata
- **Custom Sorting**: Sorts by workflow order instead of alphabetically (
  see [Custom State Sorting](#custom-state-sorting))
- **Display Only**: Non-editable, ideal for read-only views

**Basic Usage:**

```php
StateColumn::make('state')
    ->label('Order Status')
    ->sortable()
```

The column will automatically:

- Display the state label (from `HasLabel`)
- Apply the state color (from `HasColor`)
- Show the state icon (from `HasIcon`)
- Sort by custom order if defined (see below)

#### TextColumn with Badge

Alternatively, use the standard Filament `TextColumn` to display states as badges:

```php
use Filament\Tables\Columns\TextColumn;

TextColumn::make('state')
    ->badge()
    ->sortable();
```

**Complete table example:**

```php
<?php
// app/Filament/Resources/OrderResource/Tables/OrdersTable.php

namespace App\Filament\Resources\OrderResource\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use RoBYCoNTe\FilamentFlow\Tables\Columns\StateSelectColumn;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                    
                TextColumn::make('customer_name')
                    ->searchable()
                    ->sortable(),
                    
                TextColumn::make('total_amount')
                    ->money('EUR')
                    ->sortable()
                    ->alignEnd(),
                    
                StateSelectColumn::make('state')
                    ->ignoreTransitions(),
                    
                TextColumn::make('processed_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                TextColumn::make('shipped_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ]);
    }
}
```

#### StateExportColumn

Export model states to Excel or CSV with proper label formatting:

```php
use RoBYCoNTe\FilamentFlow\Tables\Columns\StateExportColumn;

StateExportColumn::make('state')
    ->label('Order Status');
```

**Key Features:**

- **Automatic Label Generation**: Automatically uses state labels from `HasLabel` interface
- **Fallback to Morph Class**: Uses the state's morph class name if no label is defined
- **Based on ExportColumn**: All familiar `ExportColumn` modifiers can be used (e.g., `label()`, `enabledByDefault()`)

**Usage in Exporters:**

```php
<?php
// app/Filament/Exports/OrderExporter.php

namespace App\Filament\Exports;

use App\Models\Order;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use RoBYCoNTe\FilamentFlow\Tables\Columns\StateExportColumn;

class OrderExporter extends Exporter
{
    protected static ?string $model = Order::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('order_number')
                ->label('Order Number'),
            ExportColumn::make('customer_name')
                ->label('Customer'),
            ExportColumn::make('total_amount')
                ->label('Total'),
            
            // Export the state with automatic label formatting
            StateExportColumn::make('state')
                ->label('Status'),
                
            ExportColumn::make('created_at')
                ->label('Created'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your order export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
```

**Customization Options:**

```php
// Use a custom label
StateExportColumn::make('state')
    ->label('Order Status')

// Disable by default in export selection
StateExportColumn::make('state')
    ->enabledByDefault(false)

// Use a different state attribute
StateExportColumn::make('payment_state')
    ->stateAttribute('payment_state')
    ->label('Payment Status')
```

**Custom State Labels:**

To provide custom labels for exported states, implement Filament's `HasLabel` interface:

```php
use Filament\Support\Contracts\HasLabel;

class PendingState extends OrderState implements HasLabel
{
    public function getLabel(): string
    {
        return __("Pending");
    }
}
```

The `StateExportColumn` component will automatically use this method to format the exported value.

---

### Custom State Sorting

Filament Flow supports custom sorting for state columns, allowing you to order records by workflow logic instead of
alphabetically or by database value.

#### Why Custom Sorting?

By default, sorting a state column would order states alphabetically (e.g., "Cancelled", "Delivered", "Pending", "
Processing"). With custom sorting, you can define a logical workflow order like: Pending → Processing → Shipped →
Delivered → Cancelled.

#### Implementation

**Step 1: Add the Trait to Your Base State Class**

First, add the `HasStateSortOrder` trait to your abstract state class:

```php
<?php
// app/States/Order/OrderState.php

namespace App\States\Order;

use RoBYCoNTe\FilamentFlow\Concerns\HasStateMetadata;
use RoBYCoNTe\FilamentFlow\Concerns\HasStateSortOrder;
use RoBYCoNTe\FilamentFlow\Contracts\HasStateMetadata as HasStateMetadataContract;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class OrderState extends State implements HasStateMetadataContract
{
    use HasStateMetadata;
    use HasStateSortOrder; // Add this trait

    public static function config(): StateConfig
    {
        // ...existing configuration...
    }
}
```

**Step 2: Implement Sort Order in Each State**

Add the `HasStateSortOrder` interface and `getSortOrder()` method to each concrete state class:

```php
<?php
// app/States/Order/PendingState.php

namespace App\States\Order;

use RoBYCoNTe\FilamentFlow\Contracts\HasStateSortOrder;
// ...other imports...

final class PendingState extends OrderState implements HasStateSortOrder
{
    public static function getSortOrder(): int
    {
        return 1; // First in the workflow
    }

    // ...existing methods...
}
```

```php
<?php
// app/States/Order/ProcessingState.php

final class ProcessingState extends OrderState implements HasStateSortOrder
{
    public static function getSortOrder(): int
    {
        return 2; // Second in the workflow
    }
    
    // ...existing methods...
}
```

```php
<?php
// app/States/Order/ShippedState.php

final class ShippedState extends OrderState implements HasStateSortOrder
{
    public static function getSortOrder(): int
    {
        return 3; // Third in the workflow
    }
    
    // ...existing methods...
}
```

```php
<?php
// app/States/Order/DeliveredState.php

final class DeliveredState extends OrderState implements HasStateSortOrder
{
    public static function getSortOrder(): int
    {
        return 4; // Fourth in the workflow
    }
    
    // ...existing methods...
}
```

```php
<?php
// app/States/Order/CancelledState.php

final class CancelledState extends OrderState implements HasStateSortOrder
{
    public static function getSortOrder(): int
    {
        return 100; // Last - cancelled orders appear at the end
    }
    
    // ...existing methods...
}
```

**Step 3: Enable Sorting in Your Table**

Use `StateSelectColumn` or `StateColumn` with the `sortable()` method:

```php
use RoBYCoNTe\FilamentFlow\Tables\Columns\StateSelectColumn;
use RoBYCoNTe\FilamentFlow\Tables\Columns\StateColumn;

// Option 1: Interactive select column with sorting
StateSelectColumn::make('state')
    ->label(__('Status'))
    ->sortable() // Custom sorting is automatically applied

// Option 2: Display-only column with sorting
StateColumn::make('state')
    ->label(__('Status'))
    ->sortable() // Custom sorting is automatically applied
```

**Complete Example:**

```php
<?php
// app/Filament/Admin/Resources/Orders/Tables/OrdersTable.php

namespace App\Filament\Admin\Resources\Orders\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use RoBYCoNTe\FilamentFlow\Tables\Columns\StateSelectColumn;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                    
                TextColumn::make('customer_name')
                    ->searchable()
                    ->sortable(),
                    
                TextColumn::make('total_amount')
                    ->money('EUR')
                    ->sortable()
                    ->alignEnd(),
                    
                // State column with custom sorting
                StateSelectColumn::make('state')
                    ->label(__('Status'))
                    ->sortable(), // Orders will sort by workflow order (1, 2, 3, 4, 100)
                    
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('state', 'asc'); // Sort by state workflow order by default
    }
}
```

#### How It Works

When you enable sorting on a state column:

1. **Custom Query**: The column generates a SQL `CASE WHEN` statement based on your sort order values
2. **Performance**: Uses native SQL sorting for optimal performance
3. **Fallback**: States without `getSortOrder()` default to order value `999`
4. **Bidirectional**: Works with both ascending and descending sort

**Generated SQL Example:**

```sql
ORDER BY 
    CASE 
        WHEN `state` = 'App\States\Order\PendingState' THEN 1
        WHEN `state` = 'App\States\Order\ProcessingState' THEN 2
        WHEN `state` = 'App\States\Order\ShippedState' THEN 3
        WHEN `state` = 'App\States\Order\DeliveredState' THEN 4
        WHEN `state` = 'App\States\Order\CancelledState' THEN 100
        ELSE 999
END
ASC
```

#### Best Practices

**1. Use Logical Workflow Numbers**

Order states according to your business workflow:

```php
PendingState::getSortOrder()      // 1 - Start of workflow
ProcessingState::getSortOrder()   // 2 - Next step
ShippedState::getSortOrder()      // 3 - Next step
DeliveredState::getSortOrder()    // 4 - Final state
CancelledState::getSortOrder()    // 100 - Terminal state (always last)
```

**2. Leave Gaps Between Numbers**

Use gaps (10, 20, 30) if you might add states later:

```php
PendingState::getSortOrder()      // 10
ProcessingState::getSortOrder()   // 20
ShippedState::getSortOrder()      // 30
DeliveredState::getSortOrder()    // 40
```

**3. Group Similar States**

Put terminal or exceptional states at the end:

```php
// Normal workflow
PendingState::getSortOrder()      // 1
ProcessingState::getSortOrder()   // 2
ShippedState::getSortOrder()      // 3
DeliveredState::getSortOrder()    // 4

// Terminal states
CancelledState::getSortOrder()    // 100
RefundedState::getSortOrder()     // 101
```

**4. Combine with Default Sorting**

Set the state column as default sort to show the most relevant orders first:

```php
return $table
    ->columns([...])
    ->defaultSort('state', 'asc'); // Show pending orders first
```

#### Benefits

✅ **Logical Order**: Orders sorted by workflow progression, not alphabetically  
✅ **Performance**: Native SQL sorting with no performance penalty  
✅ **Flexible**: Each state defines its own position independently  
✅ **Backward Compatible**: States without `getSortOrder()` still work (default: 999)  
✅ **DRY Code**: Sorting logic is centralized in reusable traits  
✅ **User-Friendly**: Users see records in a meaningful, workflow-based order


---

### Table Filters

Filter records by state using `StateSelectFilter`:

```php
use RoBYCoNTe\FilamentFlow\Tables\Filters\StateSelectFilter;

StateSelectFilter::make('state')
    ->label('Filter by Status');
```

Add to your table:

```php
return $table
    ->columns([...])
    ->filters([
        StateSelectFilter::make('state'),
    ]);
```

---

### Table Grouping

#### StateGroup

Group table records by their state with automatic label generation and no extra columns:

```php
use RoBYCoNTe\FilamentFlow\Tables\Grouping\StateGroup;

StateGroup::make('state')
    ->label('Order Status')
    ->collapsible();
```

**Key Features:**

- **Automatic Grouping**: Automatically groups records by state attribute
- **Automatic Labels**: Generates labels from state classes
- **Custom Labels**: Uses `HasLabel` interface if implemented by the state
- **Standard Group Modifiers**: All familiar Group modifiers work (e.g., `label()`, `collapsible()`)
- **No Extra Columns**: Does not add visible columns to maintain your table layout

**Basic Usage:**

```php
return $table
    ->columns([
        TextColumn::make('order_number')
            ->searchable()
            ->sortable(),
        TextColumn::make('customer_name')
            ->searchable(),
        TextColumn::make('total_amount')
            ->money('EUR')
            ->sortable(),
    ])
    ->groups([
        StateGroup::make('state')
            ->label('Status')
            ->collapsible(),
    ])
    ->defaultGroup('state'); // Apply grouping by default
```

> **Note**: The `StateGroup` component is designed to not add extra columns to your table. It uses
`getKeyFromRecordUsing()` internally to access state data without displaying an additional column, keeping your table
> layout intact.

**Customization Options:**

```php
// Change the group label
StateGroup::make('state')
    ->label('Order Status')

// Make the group collapsible
StateGroup::make('state')
    ->collapsible()

// Use a different state attribute
StateGroup::make('payment_state')
    ->stateAttribute('payment_state')
    ->label('Payment Status')

// Hide the label prefix
StateGroup::make('state')
    ->titlePrefixedWithLabel(false)

// Custom ordering
StateGroup::make('state')
    ->orderQueryUsing(fn ($query, $direction) => 
        $query->orderBy('state', $direction)
    )
```

**Custom State Labels:**

To provide custom labels for your states, implement Filament's `HasLabel` interface:

```php
use Filament\Support\Contracts\HasLabel;
use Spatie\ModelStates\State;

class PendingState extends OrderState implements HasLabel
{
    public function getLabel(): string
    {
        return __("Pending Order");
    }
}
```

The `StateGroup` component will automatically use this method to generate group labels.

**Working with Other Components:**

`StateGroup` works seamlessly with other filament-flow components:

```php
->columns([
    StateSelectColumn::make('state'), // Interactive state column
    TextColumn::make('order_number'),
    // ...other columns
])
->filters([
    StateSelectFilter::make('state'), // State filter
])
->groups([
    StateGroup::make('state')         // Group by state
        ->label(__('Status'))
        ->collapsible(),
])
```

**Multiple State Attributes:**

If your model has multiple state attributes, you can use multiple groups:

```php
->groups([
    StateGroup::make('order_state')
        ->stateAttribute('order_state')
        ->label('Order Status'),
    StateGroup::make('payment_state')
        ->stateAttribute('payment_state')
        ->label('Payment Status'),
])
```

---

### Listing Tabs

Create tabs for each state on your listing page:

```php
<?php
// app/Filament/Resources/OrderResource/Pages/ListOrders.php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Models\Order;
use Filament\Resources\Pages\ListRecords;
use RoBYCoNTe\FilamentFlow\StateTabs;

class ListOrders extends ListRecords
{
    public function getTabs(): array
    {
        return StateTabs::make(Order::class)
            ->attribute('state')        // State attribute name
            ->badge()                   // Show record count badges
            ->includeAll()              // Include an "All" tab
            ->toArray();
    }
}
```

**Options:**

- `attribute(string $attribute)` - Specify the state attribute (default: first state attribute)
- `badge(bool $badge = true)` - Show/hide record count badges
- `includeAll(bool $include = true)` - Include an "All records" tab

---

### Actions

#### StateAction

Trigger a single state transition:

```php
use RoBYCoNTe\FilamentFlow\Actions\StateAction;
use App\States\Order\ProcessingState;

StateAction::make('process')
    ->label('Mark as Processing')
    ->transitionTo(ProcessingState::class)
    ->button(); // Display as button instead of link
```

#### StateActionGroup

Automatically generate actions for all possible transitions:

```php
use RoBYCoNTe\FilamentFlow\Actions\StateActionGroup;
use App\States\Order\OrderState;

StateActionGroup::generate('state', OrderState::class)
```

This creates a dropdown menu with all available transitions based on the current state.

#### StateBulkAction

Apply state transitions to multiple records:

```php
use RoBYCoNTe\FilamentFlow\Actions\StateBulkAction;
use App\States\Order\PendingState;
use App\States\Order\ProcessingState;

StateBulkAction::make('bulk_process')
    ->label('Mark as Processing')
    ->transition(PendingState::class, ProcessingState::class);
```

**Complete actions example:**

```php
return $table
    ->columns([...])
    ->recordActions([
        EditAction::make(),
        
        // Automatic action group for all transitions
        StateActionGroup::generate('state', OrderState::class),
        
        // Custom action for a specific transition
        StateAction::make('process')
            ->label(__('Mark as Processing'))
            ->transitionTo(ProcessingState::class)
            ->button(),
    ])
    ->toolbarActions([
        BulkActionGroup::make([
            StateBulkAction::make('bulk_process')
                ->label('Mark as Processing')
                ->transition(PendingState::class, ProcessingState::class),
            DeleteBulkAction::make(),
        ]),
    ]);
```

---

### Custom Transitions

Create transition classes to handle complex logic and collect additional data:

#### Basic Transition

```php
<?php
// app/States/Order/Transitions/ProcessTransition.php

namespace App\States\Order\Transitions;

use App\Models\Order;
use App\States\Order\ProcessingState;
use Filament\Forms\Components\Textarea;
use Spatie\ModelStates\Transition;

final class ProcessTransition extends Transition
{
    public function __construct(
        private readonly Order $order,
        private readonly ?array $data = null
    ) {}

    public function handle(): Order
    {
        $this->order->state = new ProcessingState($this->order);
        $this->order->processed_at = now();

        if ($this->data && isset($this->data['processing_notes'])) {
            $this->order->notes = $this->data['processing_notes'];
        }

        $this->order->save();
        
        // You can also send notifications, dispatch jobs, etc.
        
        return $this->order;
    }

    public function form(): array
    {
        return [
            Textarea::make('processing_notes')
                ->label('Processing Notes')
                ->rows(2)
                ->maxLength(255)
                ->placeholder('Optional notes about processing...')
                ->helperText('Add any relevant information.'),
        ];
    }

    public function requiresConfirmation(): bool
    {
        return false;
    }
}
```

#### Transition with Required Form Data

```php
<?php
// app/States/Order/Transitions/ShipTransition.php

namespace App\States\Order\Transitions;

use App\Models\Order;
use App\States\Order\ShippedState;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Spatie\ModelStates\Transition;

final class ShipTransition extends Transition
{
    public function __construct(
        private readonly Order $order,
        private readonly ?array $data = null
    ) {}

    public function handle(): Order
    {
        $this->order->state = new ShippedState($this->order);
        $this->order->shipped_at = now();

        if ($this->data) {
            $trackingNumber = $this->data['tracking_number'] ?? null;
            $carrier = $this->data['carrier'] ?? null;
            $this->order->notes = "Carrier: $carrier, Tracking: $trackingNumber";
        }

        $this->order->save();

        return $this->order;
    }

    public function form(): array
    {
        return [
            Select::make('carrier')
                ->label('Carrier')
                ->required()
                ->options([
                    'dhl' => 'DHL',
                    'ups' => 'UPS',
                    'fedex' => 'FedEx',
                    'usps' => 'USPS',
                ])
                ->searchable()
                ->native(false),

            TextInput::make('tracking_number')
                ->label('Tracking Number')
                ->required()
                ->maxLength(255)
                ->helperText('Enter the carrier tracking number.'),
        ];
    }

    public function requiresConfirmation(): bool
    {
        return false;
    }
}
```

#### Transition with Confirmation

```php
<?php
// app/States/Order/Transitions/CancelTransition.php

namespace App\States\Order\Transitions;

use App\Models\Order;
use App\States\Order\CancelledState;
use Filament\Forms\Components\Textarea;
use Spatie\ModelStates\Transition;

final class CancelTransition extends Transition
{
    public function __construct(
        private readonly Order $order,
        private readonly ?array $data = null
    ) {}

    public function handle(): Order
    {
        $this->order->state = new CancelledState($this->order);
        $this->order->cancelled_at = now();
        
        if ($this->data && isset($this->data['reason'])) {
            $this->order->cancellation_reason = $this->data['reason'];
        }
        
        $this->order->save();

        // Notify customer, refund payment, etc.

        return $this->order;
    }

    public function form(): array
    {
        return [
            Textarea::make('reason')
                ->label('Cancellation Reason')
                ->required()
                ->rows(3)
                ->maxLength(500)
                ->helperText('Specify why you are cancelling this order.'),
        ];
    }

    public function requiresConfirmation(): bool
    {
        return true; // Show confirmation dialog
    }
}
```

**Transition Methods:**

- `handle()` - Required. Performs the state transition
- `form()` - Optional. Returns form fields to collect data
- `requiresConfirmation()` - Optional. Whether to show a confirmation dialog

---

## Complete Example: Order Workflow

Here's a complete example showing all components working together:

### 1. States

```php
// Abstract state
abstract class OrderState extends State implements HasStateMetadataContract
{
    use HasStateMetadata;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(PendingState::class)
            ->allowTransition(PendingState::class, ProcessingState::class, ProcessTransition::class)
            ->allowTransition(ProcessingState::class, ShippedState::class, ShipTransition::class)
            ->allowTransition(ShippedState::class, DeliveredState::class)
            ->allowTransition([PendingState::class, ProcessingState::class], CancelledState::class, CancelTransition::class);
    }
}

// Concrete states: PendingState, ProcessingState, ShippedState, DeliveredState, CancelledState
// Each implementing HasLabel, HasIcon, HasColor, HasDescription
```

### 2. Model

```php
class Order extends Model
{
    use HasStates;

    protected $casts = [
        'state' => OrderState::class,
        // ... other casts
    ];
}
```

### 3. Filament Resource

```php
class OrderResource extends Resource
{
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')->searchable()->sortable(),
                TextColumn::make('customer_name')->searchable(),
                TextColumn::make('total_amount')->money('EUR'),
                StateSelectColumn::make('state')->ignoreTransitions(),
            ])
            ->filters([
                StateSelectFilter::make('state'),
            ])
            ->recordActions([
                EditAction::make(),
                StateActionGroup::generate('state', OrderState::class),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    StateBulkAction::make('bulk_process')
                        ->label('Mark as Processing')
                        ->transition(PendingState::class, ProcessingState::class),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
```

### 4. List Page with Tabs

```php
class ListOrders extends ListRecords
{
    public function getTabs(): array
    {
        return StateTabs::make(Order::class)
            ->attribute('state')
            ->badge()
            ->includeAll()
            ->toArray();
    }
}
```

This creates a complete workflow management system with:

- Visual state display with colors and icons
- State filtering and tabbed navigation
- Interactive state transitions with validation
- Custom forms for collecting transition data
- Bulk actions for processing multiple records
- Automatic action generation based on allowed transitions

---

## Configuration Options

You can publish and customize the configuration file:

```bash
php artisan vendor:publish --tag="filament-flow-config"
```

This creates `config/filament-flow.php` with the following options:

### Global Settings

```php
// Enable or disable the plugin globally
'enabled' => true,
```

### Multi-Tenancy Configuration

```php
/**
 * The tenant model class for multi-tenancy support.
 * Set to null to disable multi-tenancy.
 *
 * Example: App\Models\Company::class, App\Models\Tenant::class
 */
'tenant_model' => null,

/**
 * The foreign key column name for tenant relationship.
 * This will be used in the workflows table.
 */
'tenant_foreign_key' => 'tenant_id',
```

**Use Case:** If your application has multiple tenants (e.g., companies, organizations), you can configure workflows per
tenant. Each tenant can have their own workflow definitions in the database.

### User Model Configuration

```php
/**
 * The user model class for assignments and audit trail.
 * Defaults to Laravel's default user model.
 */
'user_model' => null, // Will fallback to config('auth.providers.users.model')
```

**Use Case:** Specify a custom user model if you're not using Laravel's default `App\Models\User`.

### Form Builder Configuration

```php
/**
 * Use the advanced FormBuilderHelper for building forms.
 * Set to false to use basic form building in HasWorkflowCreation trait.
 */
'use_form_builder_helper' => true,
```

**Use Case:** The `FormBuilderHelper` provides advanced form building capabilities for database-configured workflows.
Set to `false` if you want simpler form generation.

---

## API Reference

### Components

| Component            | Description                              |
|----------------------|------------------------------------------|
| `StateSelect`        | Dropdown select for states               |
| `StateRadio`         | Radio button group for states            |
| `StateToggleButtons` | Toggle button group for states           |
| `StateSelectColumn`  | Interactive table column (with sorting)  |
| `StateColumn`        | Display-only table column (with sorting) |
| `StateSelectFilter`  | Table filter for states                  |
| `StateGroup`         | Group table records by state             |

### Actions

| Action             | Description                    |
|--------------------|--------------------------------|
| `StateAction`      | Single record state transition |
| `StateActionGroup` | Auto-generated action group    |
| `StateBulkAction`  | Bulk state transition          |

### Utilities

| Utility     | Description                     |
|-------------|---------------------------------|
| `StateTabs` | Generate tabs for listing pages |

### Interfaces

| Interface            | Methods                                                        |
|----------------------|----------------------------------------------------------------|
| `HasLabel`           | `getLabel(): string`                                           |
| `HasIcon`            | `getIcon(): string`                                            |
| `HasColor`           | `getColor(): string\|array`                                    |
| `HasDescription`     | `getDescription(): string`                                     |
| `HasStateMetadata`   | Combines all metadata interfaces                               |
| `HasStateSortOrder`  | `getSortOrder(): int`                                          |
| `HasAccessRules`     | Code-First state access rules (create, view, edit, transition) |
| `RoleResolver`       | Role resolution for access control                             |
| `PermissionResolver` | Permission resolution for access control                       |

### Traits

| Trait                      | Description                                       |
|----------------------------|---------------------------------------------------|
| `HasStateMetadata`         | Provides metadata methods for states              |
| `HasStateSortOrder`        | Provides sort order mapping for states            |
| `HasStateSorting`          | Enables custom sorting in table columns           |
| `HasStateOptions`          | Provides state options for form/table fields      |
| `HasDatabaseTransitions`   | Enables database-configured transitions           |
| `HasFlexibleStates`        | Supports both PHP and database-only states        |
| `HasStateAccess`           | Enables state-based access control for models     |
| `HasStateActions`          | Common functionality for state actions            |
| `HasStateAttributes`       | Manages state attribute mapping                   |
| `HasTransitionForm`        | Handles transition form generation and validation |
| `HasWorkflowCreation`      | Database workflow creation helpers                |
| `HasWorkflowAssignments`   | Workflow assignment management                    |
| `HasWorkflowForm`          | Form building for workflow configuration          |
| `ResolvesActionAttributes` | Resolves state attributes for actions             |

### Models (Database-Driven Workflows)

| Model                           | Description                              |
|---------------------------------|------------------------------------------|
| `Workflow`                      | Workflow definitions                     |
| `WorkflowState`                 | State definitions (PHP or database-only) |
| `WorkflowTransition`            | Transition configurations                |
| `WorkflowTransitionField`       | Fields to show in transition forms       |
| `WorkflowStateField`            | Field permissions per state              |
| `WorkflowFieldPermission`       | Field-level permissions                  |
| `WorkflowAssignment`            | User/team assignments to workflows       |
| `WorkflowNotification`          | Notification configurations              |
| `WorkflowNotificationRecipient` | Notification recipient strategies        |
| `WorkflowNotificationChannel`   | Notification delivery channels           |
| `WorkflowNotificationTemplate`  | Notification message templates           |
| `WorkflowNotificationLog`       | Notification delivery audit logs         |
| `WorkflowUserInvolvement`       | User involvement tracking                |
| `WorkflowTransitionSnapshot`    | Audit trail for transitions              |
| `WorkflowTransitionMetadata`    | Additional metadata for transitions      |
| `WorkflowStateAccessRule`       | State-based access control rules         |

### Services

| Service                           | Description                           |
|-----------------------------------|---------------------------------------|
| `StateService`                    | Manages state metadata and options    |
| `TransitionFormService`           | Builds and validates transition forms |
| `WorkflowCreationService`         | Creates workflows from configuration  |
| `WorkflowFieldPermissionsService` | Manages field permissions             |
| `WorkflowStateAccessService`      | State-based access control evaluation |
| `NotificationService`             | Orchestrates workflow notifications   |
| `RecipientResolver`               | Resolves notification recipients      |
| `FormBuilderHelper`               | Advanced form building utilities      |

### Casts

| Cast                | Description                                |
|---------------------|--------------------------------------------|
| `FlexibleStateCast` | Custom cast for PHP + database-only states |

### Support Classes

| Class                       | Description                                        |
|-----------------------------|----------------------------------------------------|
| `DefaultRoleResolver`       | Default role resolver (supports Spatie Permission) |
| `DefaultPermissionResolver` | Default permission resolver (supports Gates)       |
| `AccessRuleEvaluator`       | Evaluates access rule tokens against users/records |

### Exceptions

| Exception                         | Description                                             |
|-----------------------------------|---------------------------------------------------------|
| `UnauthorizedTransitionException` | Thrown when user attempts unauthorized state transition |

### Advanced Features

#### Custom State Sorting

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

---

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

---

## Relation Manager Permissions

Filament Flow can discover **RelationManagers** from your Filament Resources and treat them as fields for permission purposes. This allows you to control visibility of RM sections and individual actions (create, delete, edit) per state and per role — all configured in the database.

### How RelationManagers Are Discovered

When you open the field permissions dropdown in the workflow admin UI, `ModelDiscovery` automatically discovers:

1. **The RM itself** (e.g., `claimAttachments`) — controls section visibility
2. **Sub-fields for each action** (e.g., `claimAttachments.create`, `claimAttachments.delete`) — controls individual action buttons

Discovery works via reflection:
- The `$relationship` property is read from each RelationManager class
- Action classes (`CreateAction`, `DeleteAction`, `EditAction`) are detected in the RM source

### Using Permissions in RelationManagers

Use `isFieldVisible()` from the `HasStateAccess` trait on the owner record:

```php
// In your RelationManager's table() method:
CreateAction::make()
    ->visible(fn () => $this->getOwnerRecord()->isFieldVisible('claimAttachments.create')),

DeleteAction::make()
    ->visible(fn () => $this->getOwnerRecord()->isFieldVisible('claimAttachments.delete')),
```

### Sub-Field Naming Convention

Sub-fields follow the pattern `{relationshipName}.{action}`:

| Sub-field | Controls |
|-----------|----------|
| `attachments` | Section/tab visibility |
| `attachments.create` | Create button in header |
| `attachments.delete` | Delete button per record |
| `attachments.edit` | Edit button per record |

### Custom Role Resolver for Tenant-Aware Roles

When using multi-tenancy with per-company roles (e.g., a `collaborator` role stored in the `company_user` pivot), extend `DefaultRoleResolver` to include the pivot role:

```php
use RoBYCoNTe\FilamentFlow\Support\DefaultRoleResolver;

class TenantAwareRoleResolver extends DefaultRoleResolver
{
    public function getRoles(Model $user): array
    {
        $roles = parent::getRoles($user);

        $company = $user->currentCompany;
        if ($company) {
            $pivotRole = $user->employeeships()
                ->where('company_id', $company->id)
                ->value('role');
            if ($pivotRole) {
                $roles[] = $pivotRole;
            }
        }

        return array_unique($roles);
    }
}
```

Register it in config:

```php
// config/filament-flow.php
'state_access' => [
    'role_resolver' => \App\Support\TenantAwareRoleResolver::class,
],
```

This resolver is used by **both** `AccessRuleEvaluator` (for state access rules) and `WorkflowFieldPermissionsService` (for field permissions), ensuring consistent role resolution across the entire workflow system.

---

## License

This package is proprietary software. All rights reserved.

---

## Credits

- [Roberto Conte Rosito](mailto:roberto.conterosito@gmail.com)
- Built on [Spatie Laravel Model States](https://spatie.be/docs/laravel-model-states)
- Powered by [FilamentPHP](https://filamentphp.com/)
