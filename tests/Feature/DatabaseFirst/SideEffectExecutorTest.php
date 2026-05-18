<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionSideEffect;
use RoBYCoNTe\FilamentFlow\Services\SideEffectExecutor;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class SideEffectExecutorTest extends TestCase
{
    private SideEffectExecutor $executor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->executor = new SideEffectExecutor;
    }

    public function test_set_field_effect(): void
    {
        $order = $this->createOrder(['notes' => null]);
        $transition = $this->createTransitionWithSideEffect('set_field', 'notes', 'Auto-generated note');

        $this->executor->execute($order, $transition);

        $order->refresh();
        $this->assertEquals('Auto-generated note', $order->notes);
    }

    public function test_set_field_from_another_field(): void
    {
        $order = $this->createOrder([
            'customer_name' => 'John Doe',
            'notes' => null,
        ]);
        $transition = $this->createTransitionWithSideEffect('set_field', 'notes', 'field:customer_name');

        $this->executor->execute($order, $transition);

        $order->refresh();
        $this->assertEquals('John Doe', $order->notes);
    }

    public function test_set_field_with_null_expression_does_nothing(): void
    {
        $order = $this->createOrder(['notes' => 'Original']);
        $transition = $this->createTransitionWithSideEffect('set_field', 'notes', null);

        $this->executor->execute($order, $transition);

        $order->refresh();
        $this->assertEquals('Original', $order->notes);
    }

    public function test_set_timestamp_with_now(): void
    {
        $order = $this->createOrder(['processed_at' => null]);
        $transition = $this->createTransitionWithSideEffect('set_timestamp', 'processed_at', 'now');

        $this->executor->execute($order, $transition);

        $order->refresh();
        $this->assertNotNull($order->processed_at);
        $this->assertTrue($order->processed_at->isToday());
    }

    public function test_set_timestamp_with_null_expression_uses_now(): void
    {
        $order = $this->createOrder(['shipped_at' => null]);
        $transition = $this->createTransitionWithSideEffect('set_timestamp', 'shipped_at', null);

        $this->executor->execute($order, $transition);

        $order->refresh();
        $this->assertNotNull($order->shipped_at);
    }

    public function test_set_timestamp_with_custom_value(): void
    {
        $order = $this->createOrder(['delivered_at' => null]);
        $transition = $this->createTransitionWithSideEffect('set_timestamp', 'delivered_at', '2026-01-15');

        $this->executor->execute($order, $transition);

        $order->refresh();
        $this->assertEquals('2026-01-15', $order->delivered_at->format('Y-m-d'));
    }

    public function test_clear_field_effect(): void
    {
        $order = $this->createOrder(['notes' => 'Some notes']);
        $transition = $this->createTransitionWithSideEffect('clear_field', 'notes', null);

        $this->executor->execute($order, $transition);

        $order->refresh();
        $this->assertNull($order->notes);
    }

    public function test_increment_effect(): void
    {
        $order = $this->createOrder(['total_amount' => 100]);
        $transition = $this->createTransitionWithSideEffect('increment', 'total_amount', '10');

        $this->executor->execute($order, $transition);

        $order->refresh();
        $this->assertEquals(110, $order->total_amount);
    }

    public function test_increment_defaults_to_one(): void
    {
        $order = $this->createOrder(['total_amount' => 100]);
        $transition = $this->createTransitionWithSideEffect('increment', 'total_amount', null);

        $this->executor->execute($order, $transition);

        $order->refresh();
        $this->assertEquals(101, $order->total_amount);
    }

    public function test_increment_on_zero_field(): void
    {
        $order = $this->createOrder(['total_amount' => 0]);

        $transition = $this->createTransitionWithSideEffect('increment', 'total_amount', '5');

        $this->executor->execute($order, $transition);

        $order->refresh();
        $this->assertEquals(5, $order->total_amount);
    }

    public function test_custom_class_effect(): void
    {
        // Register a custom side effect class
        app()->bind(TestSideEffect::class, fn () => new TestSideEffect);

        $order = $this->createOrder(['notes' => null]);
        $transition = $this->createTransitionWithSideEffect('custom_class', '_unused', TestSideEffect::class);

        $this->executor->execute($order, $transition);

        $order->refresh();
        $this->assertEquals('custom_executed', $order->notes);
    }

    public function test_custom_class_nonexistent_returns_false(): void
    {
        $order = $this->createOrder(['notes' => 'Original']);
        $transition = $this->createTransitionWithSideEffect('custom_class', '_unused', 'NonExistent\\Class');

        $this->executor->execute($order, $transition);

        $order->refresh();
        $this->assertEquals('Original', $order->notes);
    }

    public function test_multiple_side_effects_in_order(): void
    {
        $order = $this->createOrder([
            'notes' => null,
            'processed_at' => null,
        ]);

        $workflow = $this->createTestWorkflow();
        $fromState = $this->createWorkflowState($workflow, ['name' => 'from']);
        $toState = $this->createWorkflowState($workflow, ['name' => 'to']);
        $transition = $this->createWorkflowTransition($workflow, $fromState, $toState);

        WorkflowTransitionSideEffect::create([
            'transition_id' => $transition->id,
            'effect_type' => 'set_field',
            'field_name' => 'notes',
            'value_expression' => 'Completed',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        WorkflowTransitionSideEffect::create([
            'transition_id' => $transition->id,
            'effect_type' => 'set_timestamp',
            'field_name' => 'processed_at',
            'value_expression' => 'now',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->executor->execute($order, $transition);

        $order->refresh();
        $this->assertEquals('Completed', $order->notes);
        $this->assertNotNull($order->processed_at);
    }

    public function test_inactive_side_effects_are_skipped(): void
    {
        $order = $this->createOrder(['notes' => 'Original']);

        $workflow = $this->createTestWorkflow();
        $fromState = $this->createWorkflowState($workflow, ['name' => 'from']);
        $toState = $this->createWorkflowState($workflow, ['name' => 'to']);
        $transition = $this->createWorkflowTransition($workflow, $fromState, $toState);

        WorkflowTransitionSideEffect::create([
            'transition_id' => $transition->id,
            'effect_type' => 'set_field',
            'field_name' => 'notes',
            'value_expression' => 'Should not be set',
            'sort_order' => 0,
            'is_active' => false,
        ]);

        $this->executor->execute($order, $transition);

        $order->refresh();
        $this->assertEquals('Original', $order->notes);
    }

    public function test_no_side_effects_does_nothing(): void
    {
        $order = $this->createOrder(['notes' => 'Original']);

        $workflow = $this->createTestWorkflow();
        $fromState = $this->createWorkflowState($workflow, ['name' => 'from']);
        $toState = $this->createWorkflowState($workflow, ['name' => 'to']);
        $transition = $this->createWorkflowTransition($workflow, $fromState, $toState);

        $this->executor->execute($order, $transition);

        $order->refresh();
        $this->assertEquals('Original', $order->notes);
    }

    public function test_custom_class_without_execute_method_returns_false(): void
    {
        $order = $this->createOrder(['notes' => 'Original']);

        app()->bind(NoExecuteMethodClass::class, fn () => new NoExecuteMethodClass);

        $transition = $this->createTransitionWithSideEffect('custom_class', '_unused', NoExecuteMethodClass::class);

        $this->executor->execute($order, $transition);

        $order->refresh();
        $this->assertEquals('Original', $order->notes);
    }

    private function createOrder(array $data = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-SE-'.uniqid(),
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
        ], $data));
    }

    private function createTransitionWithSideEffect(
        string $effectType,
        ?string $fieldName,
        ?string $valueExpression
    ) {
        $workflow = $this->createTestWorkflow();
        $fromState = $this->createWorkflowState($workflow, ['name' => 'from_'.uniqid()]);
        $toState = $this->createWorkflowState($workflow, ['name' => 'to_'.uniqid()]);
        $transition = $this->createWorkflowTransition($workflow, $fromState, $toState);

        WorkflowTransitionSideEffect::create([
            'transition_id' => $transition->id,
            'effect_type' => $effectType,
            'field_name' => $fieldName,
            'value_expression' => $valueExpression,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        return $transition;
    }
}

class TestSideEffect
{
    public function execute($model): void
    {
        $model->notes = 'custom_executed';
        $model->saveQuietly();
    }
}

class NoExecuteMethodClass
{
    public function handle($model): void
    {
        $model->notes = 'should_not_be_set';
    }
}
