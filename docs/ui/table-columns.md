# Table Columns, Filters, Grouping & Tabs

## Table Columns

### StateSelectColumn

An interactive select column that allows changing states directly from the table:

```php
use RoBYCoNTe\FilamentFlow\Tables\Columns\StateSelectColumn;

StateSelectColumn::make('state')
    ->sortable() // Enable custom state-based sorting
    ->ignoreTransitions(); // Allows direct state changes without transitions
```

**Options:**

- `sortable()` — Enable sorting with custom workflow order (see [Custom State Sorting](#custom-state-sorting))
- `ignoreTransitions()` — Allow changing to any state, bypassing transition rules
- Without `ignoreTransitions()` — Only allowed transitions are available

### StateColumn

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
- **Custom Sorting**: Sorts by workflow order instead of alphabetically (see [Custom State Sorting](#custom-state-sorting))
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

### TextColumn with Badge

Alternatively, use the standard Filament `TextColumn` to display states as badges:

```php
use Filament\Tables\Columns\TextColumn;

TextColumn::make('state')
    ->badge()
    ->sortable();
```

### StateExportColumn

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

### Complete Table Example

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

## Custom State Sorting

Filament Flow supports custom sorting for state columns, allowing you to order records by workflow logic instead of alphabetically or by database value.

### Why Custom Sorting?

By default, sorting a state column would order states alphabetically (e.g., "Cancelled", "Delivered", "Pending", "Processing"). With custom sorting, you can define a logical workflow order like: Pending → Processing → Shipped → Delivered → Cancelled.

### Implementation

**Step 1: Add the Trait to Your Base State Class**

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

### How It Works

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

### Best Practices

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

## Table Filters

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

## Table Grouping

### StateGroup

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

> **Note**: The `StateGroup` component is designed to not add extra columns to your table. It uses `getKeyFromRecordUsing()` internally to access state data without displaying an additional column, keeping your table layout intact.

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

## Listing Tabs

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

- `attribute(string $attribute)` — Specify the state attribute (default: first state attribute)
- `badge(bool $badge = true)` — Show/hide record count badges
- `includeAll(bool $include = true)` — Include an "All records" tab
