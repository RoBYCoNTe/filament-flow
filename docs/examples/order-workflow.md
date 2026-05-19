# Complete Example: Order Workflow

Here's a complete example showing all components working together to build a full order processing workflow.

## 1. States

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

## 2. Model

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

## 3. Filament Resource

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

## 4. List Page with Tabs

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

## What This Creates

This complete setup gives you:

- Visual state display with colors and icons
- State filtering and tabbed navigation
- Interactive state transitions with validation
- Custom forms for collecting transition data
- Bulk actions for processing multiple records
- Automatic action generation based on allowed transitions
