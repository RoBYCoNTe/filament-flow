<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Concerns;

use Livewire\Livewire;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateAccessRule;
use RoBYCoNTe\FilamentFlow\Tests\FilamentTestCase;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Resources\OrderResource\Pages\EditOrder;

class HasWorkflowActionsTest extends FilamentTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test',
            'email' => 'actions-test@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($this->user);
    }

    public function test_edit_page_renders_without_workflow(): void
    {
        // Order without any workflow — should not crash
        $order = Order::create([
            'order_number' => 'ORD-NOWF',
            'customer_name' => 'Test',
            'total_amount' => 100,
            'state' => 'pending',
        ]);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertSuccessful();
    }

    public function test_edit_page_renders_with_workflow_and_transitions(): void
    {
        $workflow = $this->createTestWorkflow();
        $pending = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'is_initial' => true,
        ]);
        $approved = $this->createWorkflowState($workflow, [
            'name' => 'approved',
        ]);

        $this->createWorkflowTransition($workflow, $pending, $approved, [
            'name' => 'approve',
            'label' => 'Approve Order',
        ]);

        WorkflowStateAccessRule::create([
            'state_id' => $pending->id,
            'access_type' => 'view',
            'rule' => '@authenticated',
            'priority' => 0,
            'is_active' => true,
        ]);
        WorkflowStateAccessRule::create([
            'state_id' => $pending->id,
            'access_type' => 'edit',
            'rule' => '@authenticated',
            'priority' => 0,
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-WF',
            'customer_name' => 'Test',
            'total_amount' => 100,
            'state' => 'pending',
        ]);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertSuccessful();
    }

    public function test_disabled_filament_flow_skips_workflow_actions(): void
    {
        config()->set('filament-flow.enabled', false);

        $order = Order::create([
            'order_number' => 'ORD-DISABLED',
            'customer_name' => 'Test',
            'total_amount' => 100,
            'state' => 'pending',
        ]);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertSuccessful();
    }
}
