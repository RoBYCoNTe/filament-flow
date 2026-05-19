# Testing

Filament Flow ships a testing mixin that adds workflow-specific assertions and helpers to Livewire's `Testable` class.

## Auto-Registration

The `TestsFilamentFlow` mixin is registered automatically by `FilamentFlowServiceProvider` during the test environment boot. It calls `Livewire::prolong(TestsFilamentFlow::class)`, so no manual setup is required in your test suite. After installing the package, the methods described below are available on any `Livewire::test()` instance.

## Action Naming Convention

Transition actions registered by Filament Flow follow the naming convention:

```
transition-{Str::slug($transitionName)}
```

For example:

| Transition name | Action name |
|---|---|
| `Start Processing` | `transition-start-processing` |
| `ship_order` | `transition-ship-order` |
| `Cancel` | `transition-cancel` |

Pass the raw transition name to the testing helpers — they apply `Str::slug()` internally.

## Testing Methods

### `assertTransitionVisible(string $transitionName)`

Asserts that the transition action button is rendered and visible on the page.

```php
Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
    ->assertTransitionVisible('Start Processing');
```

### `assertTransitionHidden(string $transitionName)`

Asserts that the transition action button is not visible (hidden or absent from the rendered HTML).

```php
Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
    ->assertTransitionHidden('ship_order');
```

### `assertTransitionExists(string $transitionName)`

Asserts that the transition action exists regardless of its visibility state.

```php
Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
    ->assertTransitionExists('Start Processing');
```

### `assertTransitionMissing(string $transitionName)`

Asserts that the transition action does not exist on the page at all.

```php
Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
    ->assertTransitionMissing('Deliver');
```

### `callTransition(string $transitionName, array $data = [])`

Executes the transition action, optionally passing form data.

```php
Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
    ->callTransition('Start Processing')
    ->assertHasNoErrors();

// With transition form data
Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
    ->callTransition('Cancel', ['transition_notes' => 'Customer requested cancellation'])
    ->assertHasNoErrors();
```

## Fallback Behaviour

Each assertion first tries the native Filament assertion method (`assertActionVisible`, `assertActionHidden`, etc.). If that throws — for example when the action is nested inside an `ActionGroup` in the form footer rather than the page header — the helper falls back to inspecting the raw rendered HTML for the action name string. Both header actions (via `HasWorkflowActions`) and form footer actions are covered.

## Complete Test Example

```php
<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\OrderResource\Pages\EditOrder;
use App\Models\Order;
use App\States\Order\PendingState;
use App\States\Order\ProcessingState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_order_shows_start_processing_transition(): void
    {
        $order = Order::factory()->create(['state' => PendingState::class]);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertTransitionVisible('Start Processing')
            ->assertTransitionHidden('Ship Order')
            ->assertTransitionMissing('Deliver');
    }

    public function test_start_processing_transition_changes_state(): void
    {
        $order = Order::factory()->create(['state' => PendingState::class]);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->callTransition('Start Processing')
            ->assertHasNoErrors();

        expect($order->fresh()->state)->toBeInstanceOf(ProcessingState::class);
    }

    public function test_cancel_transition_requires_notes(): void
    {
        $order = Order::factory()->create(['state' => PendingState::class]);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->callTransition('Cancel', [])
            ->assertHasErrors(['transition_notes']);
    }

    public function test_shipped_order_does_not_show_pending_transitions(): void
    {
        $order = Order::factory()->create(['state' => ProcessingState::class]);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertTransitionMissing('Start Processing')
            ->assertTransitionExists('Ship Order');
    }
}
```
