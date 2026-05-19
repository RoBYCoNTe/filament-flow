# Core Concepts

This page explains the mental model behind Filament Flow: what states and transitions are, which configuration approach to choose, and how the package's key mechanisms work under the hood.

## Two Approaches

Filament Flow supports two complementary ways to define workflow states and transitions.

### Code-First (PHP State Classes via Spatie)

You define states as PHP classes that extend a Spatie `State` base class. Transitions are declared in the `config()` method on the abstract state class.

**Best suited for:**

- Complex business logic that lives in PHP (guards, side-effects, computed properties)
- Type safety and IDE autocompletion
- Testable state machines with PHPUnit
- Scenarios where states are known at development time and rarely change

```php
abstract class OrderState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(PendingState::class)
            ->allowTransition(PendingState::class, ProcessingState::class, ProcessTransition::class)
            ->allowTransition(ProcessingState::class, ShippedState::class, ShipTransition::class);
    }
}
```

### Database-Driven (States and Transitions in DB)

You define states and transitions entirely through the Filament admin UI or database seeding, without writing PHP classes for each state.

**Best suited for:**

- Dynamic configuration that changes without a deployment
- Non-developer users managing their own workflow stages
- Multi-tenant applications where each tenant needs a custom workflow
- Rapid prototyping or configurable approval chains

### Hybrid (Recommended)

Use code-first state classes for the state machine structure and type safety, then layer database records on top for display metadata, access rules, notifications, and transitions. The `SyncStatesCommand` bridges the two: it reads your PHP classes and creates `WorkflowState` records automatically.

**This is the recommended approach for most projects.** You get IDE support and testable transitions while keeping runtime configurability for everything that does not require code.

## Which Approach Should I Use?

```
Do states change without a deployment?
  Yes → Database-Driven (or Hybrid)
  No  → Code-First (or Hybrid)

Do non-developers need to manage the workflow?
  Yes → Database-Driven (or Hybrid)
  No  → Code-First (or Hybrid)

Is multi-tenant per-tenant customization required?
  Yes → Database-Driven with tenant_id on the workflow record
  No  → Code-First or Hybrid

Do you need complex PHP guards, side-effects, or typed transition classes?
  Yes → Code-First or Hybrid
  No  → Database-Driven is sufficient

Default recommendation: Hybrid
  Start with PHP state classes for structure, add DB records via sync-states,
  then configure access rules, notifications, and display metadata in the UI.
```

## State Machine Basics

A **state** represents a discrete condition a record can be in — for example `Pending`, `Processing`, or `Shipped`. Only one state is active at a time.

A **transition** is the allowed movement from one state to another — for example `Pending → Processing`. Transitions may carry a form (for collecting extra data), guards (conditions that must pass), and side effects.

Filament Flow connects Spatie's state machine library to Filament by:

1. Reading the active workflow for a model from the database.
2. Evaluating which transitions are available given the current state and the authenticated user.
3. Rendering those transitions as Filament `Action` buttons in the header or form footer.
4. Executing the transition (PHP or database-driven) on confirmation, recording the history entry.

## FlexibleStateCast

`FlexibleStateCast` is the bridge between PHP State classes and database-only state values. Use it when a model's state column might contain either a PHP morph class name (code-first) or a plain string (database-only).

**Usage:**

```php
protected $casts = [
    'state' => FlexibleStateCast::class.':'.OrderState::class,
];
```

The format is `FlexibleStateCast::class.':'.YourBaseState::class`.

**Behaviour:**

- When the stored value resolves to a known PHP class, it returns a `State` instance exactly as Spatie's built-in cast would.
- When the stored value does not correspond to any known PHP class (a database-only state), it returns the raw string instead of throwing an exception.

This makes `FlexibleStateCast` safe to use in hybrid setups where some states have PHP classes and some do not.

## Access Control Model

Filament Flow uses two independent layers of access control that are evaluated together.

### State-Based Rules

Each `WorkflowState` record can have `WorkflowStateAccessRule` entries that specify who may `view`, `edit`, or `transition` a record while it is in that state. Rules use a token syntax:

| Token | Meaning |
|---|---|
| `*` | Everyone (including guests) |
| `@authenticated` | Any authenticated user |
| `@assigned` | Any user assigned to the record |
| `@assigned:type` | User assigned with a specific assignment type |
| `@owner` | User whose ID matches the `owner_field` column |
| `role:name` | User with a specific role |
| `permission:name` | User with a specific permission |

When no state-specific rules are defined the defaults from `config('filament-flow.state_access.defaults')` apply.

### Assignment-Based Overrides

A `WorkflowAssignment` record links a user to a record with an optional nullable boolean `access_override`:

- `null` — follow the state-based rules above (default behaviour)
- `true` — explicitly grant access regardless of state rules
- `false` — explicitly deny access regardless of state rules

Assignment overrides take precedence over state rules but are still subject to the super admin bypass.

### Super Admin Bypass

Any user whose role appears in `config('filament-flow.state_access.super_admin_roles')` bypasses all access checks. The default value is `['super_admin']`.

## Workflow Resolution with Tenant Fallback

When the package needs a workflow for a model it calls `Workflow::findForModel()`, which follows this priority order:

1. **Tenant-specific workflow** — if multi-tenancy is enabled and a tenant is active, look for a workflow with a matching `tenant_id`.
2. **Global workflow** — fall back to a workflow with `tenant_id = null`.

This allows you to define a global default workflow and then override it for specific tenants without duplicating configuration. The resolved workflow is cached using the key `{prefix}:workflow:{modelClass}:{stateColumn}:{tenantId}` for the duration configured in `cache.ttl`.
