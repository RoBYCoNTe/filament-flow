# Admin Panel Setup

Filament Flow provides a built-in admin interface for managing workflows, states, and transitions visually — no code deployments needed once the plugin is registered.

## Prerequisites

Complete the [installation steps](/guide/installation) and ensure `FilamentFlowPlugin` is available in your application before proceeding.

## Registering the Plugin

Add the plugin to your Filament panel provider:

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

This is the only required step. Once registered, a **Workflows** section appears in the panel navigation.

## What You Get

The plugin registers three Filament resources:

| Resource | Path | Purpose |
|---|---|---|
| Workflows | `/workflows` | Create and manage workflow definitions |
| Workflow States | `/workflow-states` | Define states per workflow |
| Workflow Transitions | `/workflow-transitions` | Configure allowed transitions |

Each resource has full CRUD with validation, relationship management, and real-time feedback.

## Restricting Access

By default the resources are visible to all authenticated panel users. Restrict access in production:

```php
FilamentFlowPlugin::make()
    ->authorizeUsing(fn (User $user): bool => $user->hasRole('super_admin')),
```

## Navigation Customization

Change how the Workflows section appears in your panel navigation:

```php
FilamentFlowPlugin::make()
    ->navigationLabel('Workflows')
    ->navigationGroup('Configuration')
    ->navigationIcon('heroicon-o-cog-6-tooth')
    ->navigationSort(10),
```

To nest it under an existing nav item:

```php
FilamentFlowPlugin::make()
    ->navigationParentItem('Settings'),
```

## Hiding the Admin Interface

If you want to use Filament Flow programmatically without exposing any admin UI:

```php
FilamentFlowPlugin::make()
    ->withoutWorkflowResource(),
```

All services and traits remain functional — only the admin resources are hidden.

## Creating Your First Workflow

Once the panel is set up, create a workflow in this order:

1. **Workflow** — name, target model class, state column
2. **States** — define all possible states with labels, colors, and icons
3. **Transitions** — specify which state-to-state moves are allowed

See [Database-Driven Workflows](/workflows/database-driven) for a full walkthrough with examples.

## Multiple Panels

Register the plugin separately in each panel where you want workflow management to be available. Navigation configuration (group, label, sort) can differ per panel:

```php
// Admin panel
FilamentFlowPlugin::make()
    ->navigationGroup('System')
    ->authorizeUsing(fn (User $user) => $user->hasRole('super_admin')),

// Operations panel
FilamentFlowPlugin::make()
    ->navigationGroup('Tools')
    ->authorizeUsing(fn (User $user) => $user->hasRole('manager')),
```

For multi-tenant setups, see [Multi-Tenancy](/panel/multi-tenancy).
