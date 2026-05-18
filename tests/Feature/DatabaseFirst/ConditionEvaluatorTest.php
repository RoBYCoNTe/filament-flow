<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use RoBYCoNTe\FilamentFlow\Services\ConditionEvaluator;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class ConditionEvaluatorTest extends TestCase
{
    private ConditionEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new ConditionEvaluator;
    }

    public function test_null_conditions_pass(): void
    {
        $order = $this->createOrder();
        $this->assertTrue($this->evaluator->evaluate($order, null));
    }

    public function test_empty_conditions_pass(): void
    {
        $order = $this->createOrder();
        $this->assertTrue($this->evaluator->evaluate($order, []));
    }

    public function test_equals_operator(): void
    {
        $order = $this->createOrder(['state' => 'pending']);

        $this->assertTrue($this->evaluator->evaluate($order, [
            ['field' => 'state', 'operator' => '=', 'value' => 'pending'],
        ]));

        $this->assertFalse($this->evaluator->evaluate($order, [
            ['field' => 'state', 'operator' => '=', 'value' => 'completed'],
        ]));
    }

    public function test_not_equals_operator(): void
    {
        $order = $this->createOrder(['state' => 'pending']);

        $this->assertTrue($this->evaluator->evaluate($order, [
            ['field' => 'state', 'operator' => '!=', 'value' => 'completed'],
        ]));

        $this->assertFalse($this->evaluator->evaluate($order, [
            ['field' => 'state', 'operator' => '!=', 'value' => 'pending'],
        ]));
    }

    public function test_in_operator(): void
    {
        $order = $this->createOrder(['state' => 'processing']);

        $this->assertTrue($this->evaluator->evaluate($order, [
            ['field' => 'state', 'operator' => 'in', 'value' => ['pending', 'processing']],
        ]));

        $this->assertFalse($this->evaluator->evaluate($order, [
            ['field' => 'state', 'operator' => 'in', 'value' => ['completed', 'shipped']],
        ]));
    }

    public function test_not_in_operator(): void
    {
        $order = $this->createOrder(['state' => 'processing']);

        $this->assertTrue($this->evaluator->evaluate($order, [
            ['field' => 'state', 'operator' => 'not_in', 'value' => ['completed', 'shipped']],
        ]));

        $this->assertFalse($this->evaluator->evaluate($order, [
            ['field' => 'state', 'operator' => 'not_in', 'value' => ['pending', 'processing']],
        ]));
    }

    public function test_greater_than_operator(): void
    {
        $order = $this->createOrder(['total_amount' => 100]);

        $this->assertTrue($this->evaluator->evaluate($order, [
            ['field' => 'total_amount', 'operator' => '>', 'value' => 50],
        ]));

        $this->assertFalse($this->evaluator->evaluate($order, [
            ['field' => 'total_amount', 'operator' => '>', 'value' => 100],
        ]));
    }

    public function test_less_than_operator(): void
    {
        $order = $this->createOrder(['total_amount' => 50]);

        $this->assertTrue($this->evaluator->evaluate($order, [
            ['field' => 'total_amount', 'operator' => '<', 'value' => 100],
        ]));

        $this->assertFalse($this->evaluator->evaluate($order, [
            ['field' => 'total_amount', 'operator' => '<', 'value' => 50],
        ]));
    }

    public function test_greater_than_or_equal_operator(): void
    {
        $order = $this->createOrder(['total_amount' => 100]);

        $this->assertTrue($this->evaluator->evaluate($order, [
            ['field' => 'total_amount', 'operator' => '>=', 'value' => 100],
        ]));

        $this->assertTrue($this->evaluator->evaluate($order, [
            ['field' => 'total_amount', 'operator' => '>=', 'value' => 50],
        ]));

        $this->assertFalse($this->evaluator->evaluate($order, [
            ['field' => 'total_amount', 'operator' => '>=', 'value' => 150],
        ]));
    }

    public function test_less_than_or_equal_operator(): void
    {
        $order = $this->createOrder(['total_amount' => 100]);

        $this->assertTrue($this->evaluator->evaluate($order, [
            ['field' => 'total_amount', 'operator' => '<=', 'value' => 100],
        ]));

        $this->assertTrue($this->evaluator->evaluate($order, [
            ['field' => 'total_amount', 'operator' => '<=', 'value' => 150],
        ]));

        $this->assertFalse($this->evaluator->evaluate($order, [
            ['field' => 'total_amount', 'operator' => '<=', 'value' => 50],
        ]));
    }

    public function test_is_null_operator(): void
    {
        $order = $this->createOrder(['notes' => null]);

        $this->assertTrue($this->evaluator->evaluate($order, [
            ['field' => 'notes', 'operator' => 'is_null'],
        ]));

        $order->notes = 'Some note';
        $order->save();

        $this->assertFalse($this->evaluator->evaluate($order, [
            ['field' => 'notes', 'operator' => 'is_null'],
        ]));
    }

    public function test_is_not_null_operator(): void
    {
        $order = $this->createOrder(['notes' => 'Some note']);

        $this->assertTrue($this->evaluator->evaluate($order, [
            ['field' => 'notes', 'operator' => 'is_not_null'],
        ]));

        $order->notes = null;
        $order->save();

        $this->assertFalse($this->evaluator->evaluate($order, [
            ['field' => 'notes', 'operator' => 'is_not_null'],
        ]));
    }

    public function test_contains_operator(): void
    {
        $order = $this->createOrder(['customer_name' => 'John Doe']);

        $this->assertTrue($this->evaluator->evaluate($order, [
            ['field' => 'customer_name', 'operator' => 'contains', 'value' => 'John'],
        ]));

        $this->assertFalse($this->evaluator->evaluate($order, [
            ['field' => 'customer_name', 'operator' => 'contains', 'value' => 'Jane'],
        ]));
    }

    public function test_multiple_conditions_all_must_pass(): void
    {
        $order = $this->createOrder([
            'state' => 'processing',
            'total_amount' => 100,
        ]);

        // Both true
        $this->assertTrue($this->evaluator->evaluate($order, [
            ['field' => 'state', 'operator' => '=', 'value' => 'processing'],
            ['field' => 'total_amount', 'operator' => '>=', 'value' => 50],
        ]));

        // First true, second false
        $this->assertFalse($this->evaluator->evaluate($order, [
            ['field' => 'state', 'operator' => '=', 'value' => 'processing'],
            ['field' => 'total_amount', 'operator' => '>', 'value' => 200],
        ]));
    }

    public function test_dot_notation_resolves_relations(): void
    {
        $user = $this->createTestUser(['name' => 'Admin', 'role' => 'admin']);
        $order = $this->createOrder(['user_id' => $user->id]);

        $this->assertTrue($this->evaluator->evaluate($order, [
            ['field' => 'owner.role', 'operator' => '=', 'value' => 'admin'],
        ]));

        $this->assertFalse($this->evaluator->evaluate($order, [
            ['field' => 'owner.role', 'operator' => '=', 'value' => 'editor'],
        ]));
    }

    public function test_dot_notation_null_relation_returns_null(): void
    {
        $order = $this->createOrder(['user_id' => null]);

        // Null relation → is_null should pass
        $this->assertTrue($this->evaluator->evaluate($order, [
            ['field' => 'owner.name', 'operator' => 'is_null'],
        ]));
    }

    public function test_condition_without_field_passes(): void
    {
        $order = $this->createOrder();

        $this->assertTrue($this->evaluator->evaluate($order, [
            ['operator' => '=', 'value' => 'something'],
        ]));
    }

    public function test_unknown_operator_passes(): void
    {
        $order = $this->createOrder(['state' => 'pending']);

        $this->assertTrue($this->evaluator->evaluate($order, [
            ['field' => 'state', 'operator' => 'unknown_op', 'value' => 'pending'],
        ]));
    }

    public function test_default_operator_is_equals(): void
    {
        $order = $this->createOrder(['state' => 'pending']);

        // No operator specified → defaults to '='
        $this->assertTrue($this->evaluator->evaluate($order, [
            ['field' => 'state', 'value' => 'pending'],
        ]));

        $this->assertFalse($this->evaluator->evaluate($order, [
            ['field' => 'state', 'value' => 'completed'],
        ]));
    }

    public function test_value_key_fallback_to_values(): void
    {
        $order = $this->createOrder(['state' => 'processing']);

        // Uses 'values' key instead of 'value'
        $this->assertTrue($this->evaluator->evaluate($order, [
            ['field' => 'state', 'operator' => 'in', 'values' => ['processing', 'pending']],
        ]));
    }

    private function createOrder(array $data = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-COND-'.uniqid(),
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
        ], $data));
    }
}
