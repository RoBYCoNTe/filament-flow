# Plugin Registration

Filament Flow provides a complete admin interface for managing workflows directly from your Filament panel. This allows you to:

- Create and edit workflows through a visual interface
- Manage states with drag-and-drop reordering
- Configure transitions between states
- Set up notifications for workflow events

## Registering the Plugin

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

## Disabling the Workflow Resource

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

## Enabling Workflow Resources

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

1. **Workflows** — Create and manage workflow definitions
2. **Workflow States** — Define states for each workflow
3. **Workflow Transitions** — Configure allowed transitions between states

## Creation Order

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
        - **PHP State Class**: Optional — leave empty for database-only states
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
        - **PHP Transition Class**: Optional — leave empty for database-only
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

## Customizing Navigation

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

## Conditional Display

You can conditionally show/hide workflow resources:

```php
FilamentFlowPlugin::make()
    ->workflowResources(
        auth()->user()->hasRole('admin') // Only show to admins
    )
```
