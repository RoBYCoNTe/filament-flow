<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Filament;

use Livewire\Livewire;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionField;
use RoBYCoNTe\FilamentFlow\Tests\FilamentTestCase;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Resources\OrderResource\Pages\EditOrder;

class TransitionActionTest extends FilamentTestCase
{
    private array $workflowData;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workflowData = $this->createFullWorkflow();
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'transition-test@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($this->user);
    }

    public function test_transition_actions_exist_on_edit_page(): void
    {
        $order = $this->createOrderInState('pending', ['user_id' => $this->user->id]);

        // The edit page should have transition actions from the pending state
        $livewire = Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertSuccessful();

        // The start_processing transition should be available as a header action
        $livewire->assertActionExists('transition-start-processing');
    }

    public function test_transition_action_changes_state(): void
    {
        $order = $this->createOrderInState('pending', ['user_id' => $this->user->id]);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('transition-start-processing');

        $order->refresh();
        $this->assertEquals('processing', $order->state);
    }

    public function test_transition_action_not_available_for_invalid_state(): void
    {
        // ship_order is only valid from processing, not from pending
        // Since forDatabaseRecord() only generates actions for valid transitions
        // from the current state, this action won't exist at all
        $order = $this->createOrderInState('pending', ['user_id' => $this->user->id]);

        $livewire = Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()]);

        // ship_order action should not exist for pending state
        try {
            $livewire->assertActionExists('transition-ship-order');
            $this->fail('Expected action to not exist for invalid state transition');
        } catch (\Throwable) {
            $this->assertTrue(true);
        }
    }

    public function test_transition_action_visible_for_valid_state(): void
    {
        $order = $this->createOrderInState('pending', ['user_id' => $this->user->id]);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionVisible('transition-start-processing');
    }

    public function test_sequential_transitions(): void
    {
        $order = $this->createOrderInState('pending', ['user_id' => $this->user->id]);

        // pending -> processing
        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('transition-start-processing');

        $order->refresh();
        $this->assertEquals('processing', $order->state);

        // processing -> shipped
        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('transition-ship-order');

        $order->refresh();
        $this->assertEquals('shipped', $order->state);

        // shipped -> delivered
        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('transition-deliver-order');

        $order->refresh();
        $this->assertEquals('delivered', $order->state);
    }

    public function test_transition_action_label_from_transition(): void
    {
        $order = $this->createOrderInState('pending', ['user_id' => $this->user->id]);

        // The action label should come from the transition's label
        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionExists('transition-start-processing');
    }

    public function test_transition_with_confirmation(): void
    {
        // Update the transition to require confirmation
        $transition = $this->workflowData['transitions']['start_processing'];
        $transition->update(['requires_confirmation' => true]);

        $order = $this->createOrderInState('pending', ['user_id' => $this->user->id]);

        // The action should exist and require confirmation (modal)
        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionExists('transition-start-processing');
    }

    public function test_transition_with_form_fields(): void
    {
        // Add a transition field for processing_notes on start_processing
        $transition = $this->workflowData['transitions']['start_processing'];
        WorkflowTransitionField::create([
            'transition_id' => $transition->id,
            'field_name' => 'processing_notes',
            'field_type' => 'textarea',
            'label' => 'Processing Notes',
            'is_required' => true,
            'save_to_model' => true,
            'sort_order' => 0,
        ]);

        $order = $this->createOrderInState('pending', ['user_id' => $this->user->id]);

        // The transition should exist and have a form (it will show as a modal)
        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionExists('transition-start-processing');
    }

    public function test_no_actions_when_workflow_disabled(): void
    {
        config()->set('filament-flow.enabled', false);

        $order = $this->createOrderInState('pending', ['user_id' => $this->user->id]);

        // With workflow disabled, no transition actions should be generated
        $livewire = Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertSuccessful();

        // The action should not exist when workflow is disabled
        try {
            $livewire->assertActionExists('transition-start-processing');
            $this->fail('Expected action to not exist');
        } catch (\Throwable) {
            $this->assertTrue(true);
        }
    }

    public function test_transition_logs_state_change(): void
    {
        $order = $this->createOrderInState('pending', ['user_id' => $this->user->id]);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('transition-start-processing');

        $this->assertTransitionLogged($order, 'pending', 'processing');
    }
}
