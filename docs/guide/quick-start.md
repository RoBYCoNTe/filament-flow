# Quick Start

This guide covers the basic configuration needed to start using Filament Flow: setting up your database, creating state classes, and configuring your model.

## Database Setup

Your database table must have a string column to store the state. Additionally, you may want to add timestamp columns to track when specific states were reached.

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

- `state` (string) — Stores the fully qualified class name of the current state

**Recommended columns:**

- State-specific timestamp columns (e.g., `processed_at`, `shipped_at`)
- Additional data columns for transition information (e.g., `cancellation_reason`)
- A `notes` or `comments` text column for general information

## Creating State Classes

### 1. Create the Abstract State Class

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

### 2. Create Concrete State Classes

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

| Interface | Description |
|---|---|
| `HasLabel` | Display name for the state |
| `HasIcon` | Icon using Heroicon enum or string |
| `HasColor` | Color using Filament's Color helper |
| `HasDescription` | Optional description text |

## Configuring Your Model

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

## Next Steps

- [Register the plugin in your Filament panel](/panel/integration)
- [Explore database-driven workflows](/workflows/database-driven)
- [Add UI components to your resource](/ui/form-components)
- [See a complete order workflow example](/examples/order-workflow)
