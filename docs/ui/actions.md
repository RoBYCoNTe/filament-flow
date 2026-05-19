# Actions

## StateAction

Trigger a single state transition:

```php
use RoBYCoNTe\FilamentFlow\Actions\StateAction;
use App\States\Order\ProcessingState;

StateAction::make('process')
    ->label('Mark as Processing')
    ->transitionTo(ProcessingState::class)
    ->button(); // Display as button instead of link
```

## StateActionGroup

Automatically generate actions for all possible transitions:

```php
use RoBYCoNTe\FilamentFlow\Actions\StateActionGroup;
use App\States\Order\OrderState;

StateActionGroup::generate('state', OrderState::class)
```

This creates a dropdown menu with all available transitions based on the current state.

## StateBulkAction

Apply state transitions to multiple records:

```php
use RoBYCoNTe\FilamentFlow\Actions\StateBulkAction;
use App\States\Order\PendingState;
use App\States\Order\ProcessingState;

StateBulkAction::make('bulk_process')
    ->label('Mark as Processing')
    ->transition(PendingState::class, ProcessingState::class);
```

## Complete Actions Example

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

## Custom Transitions

Create transition classes to handle complex logic and collect additional data.

### Basic Transition

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

### Transition with Required Form Data

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

### Transition with Confirmation

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

| Method | Required | Description |
|---|---|---|
| `handle()` | Yes | Performs the state transition |
| `form()` | No | Returns form fields to collect data |
| `requiresConfirmation()` | No | Whether to show a confirmation dialog |
