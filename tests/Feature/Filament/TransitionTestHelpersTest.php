<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Filament;

use Livewire\Livewire;
use RoBYCoNTe\FilamentFlow\Tests\FilamentTestCase;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Resources\OrderResource\Pages\EditOrder;

/**
 * Tests for the TestsFilamentFlow testing macros.
 *
 * Verifies that assertTransitionVisible, assertTransitionHidden,
 * assertTransitionExists, assertTransitionMissing, and callTransition
 * work correctly with workflow transition actions on Filament pages.
 */
class TransitionTestHelpersTest extends FilamentTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createFullWorkflow();
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'helpers-test@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($this->user);
    }

    // ── assertTransitionVisible ──

    public function test_assert_transition_visible_for_valid_transition(): void
    {
        $order = $this->createOrderInState('pending', ['user_id' => $this->user->id]);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertTransitionVisible('start_processing');
    }

    public function test_assert_transition_visible_with_underscores(): void
    {
        // Transition names with underscores should be resolved correctly
        $order = $this->createOrderInState('processing', ['user_id' => $this->user->id]);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertTransitionVisible('ship_order');
    }

    // ── assertTransitionHidden ──

    public function test_assert_transition_hidden_for_invalid_state(): void
    {
        // ship_order is only valid from processing, not from pending
        $order = $this->createOrderInState('pending', ['user_id' => $this->user->id]);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertTransitionHidden('ship_order');
    }

    public function test_assert_transition_hidden_from_final_state(): void
    {
        $order = $this->createOrderInState('delivered', ['user_id' => $this->user->id]);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertTransitionHidden('start_processing');
    }

    // ── assertTransitionExists ──

    public function test_assert_transition_exists(): void
    {
        $order = $this->createOrderInState('pending', ['user_id' => $this->user->id]);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertTransitionExists('start_processing');
    }

    // ── assertTransitionMissing ──

    public function test_assert_transition_missing_from_wrong_state(): void
    {
        $order = $this->createOrderInState('pending', ['user_id' => $this->user->id]);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertTransitionMissing('deliver_order');
    }

    public function test_assert_transition_missing_when_workflow_disabled(): void
    {
        config()->set('filament-flow.enabled', false);

        $order = $this->createOrderInState('pending', ['user_id' => $this->user->id]);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertTransitionMissing('start_processing');
    }

    // ── callTransition ──

    public function test_call_transition_changes_state(): void
    {
        $order = $this->createOrderInState('pending', ['user_id' => $this->user->id]);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->callTransition('start_processing');

        $order->refresh();
        $this->assertEquals('processing', $order->state);
    }

    public function test_call_transition_sequential(): void
    {
        $order = $this->createOrderInState('pending', ['user_id' => $this->user->id]);

        // pending -> processing
        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->callTransition('start_processing');

        $order->refresh();
        $this->assertEquals('processing', $order->state);

        // processing -> shipped
        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->callTransition('ship_order');

        $order->refresh();
        $this->assertEquals('shipped', $order->state);

        // shipped -> delivered
        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->callTransition('deliver_order');

        $order->refresh();
        $this->assertEquals('delivered', $order->state);
    }

    // ── Combined assertions ──

    public function test_transition_visibility_changes_with_state(): void
    {
        $order = $this->createOrderInState('pending', ['user_id' => $this->user->id]);

        // In pending: start_processing visible, ship_order hidden
        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertTransitionVisible('start_processing')
            ->assertTransitionHidden('ship_order')
            ->assertTransitionHidden('deliver_order');

        // Transition to processing
        $order->transitionTo('processing');
        $order->refresh();

        // In processing: ship_order visible, start_processing hidden
        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertTransitionVisible('ship_order')
            ->assertTransitionHidden('start_processing')
            ->assertTransitionHidden('deliver_order');
    }
}
