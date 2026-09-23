<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Contracts\FormulaConditionProvider;
use RoBYCoNTe\FilamentFlow\Exceptions\ConditionNotMetException;
use RoBYCoNTe\FilamentFlow\Exceptions\FormulaConditionFailedException;
use RoBYCoNTe\FilamentFlow\Services\ConditionEvaluator;
use RoBYCoNTe\FilamentFlow\Support\FormulaConditionRegistry;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * The formula of a condition, delegated to the host: it passes when the provider says yes and
 * refuses when it says no, it does not block a host that registered no provider, the message is
 * interpolated through the provider, and field conditions and a formula all have to hold
 * together.
 */
class FormulaConditionProviderTest extends TestCase
{
    private FormulaConditionRegistry $registry;

    private ConditionEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = app(FormulaConditionRegistry::class);
        $this->registry->clear();
        $this->evaluator = new ConditionEvaluator;
    }

    protected function tearDown(): void
    {
        $this->registry->clear();
        parent::tearDown();
    }

    // ──────────────────────────────────────────────────────────────
    // ConditionEvaluator — formula branch
    // ──────────────────────────────────────────────────────────────

    public function test_formula_condition_passes_when_provider_returns_true(): void
    {
        $this->registry->register($this->makeProvider(true));
        $order = $this->createOrder();

        $result = $this->evaluator->evaluate($order, [
            ['type' => 'formula', 'expression' => 'total_amount > 0', 'message_template' => 'Amount must be positive.'],
        ]);

        $this->assertTrue($result);
    }

    public function test_formula_condition_throws_when_provider_returns_false(): void
    {
        $this->registry->register($this->makeProvider(false, 'Amount is too low.'));
        $order = $this->createOrder();

        $this->expectException(FormulaConditionFailedException::class);
        $this->expectExceptionMessage('Amount is too low.');

        $this->evaluator->evaluate($order, [
            ['type' => 'formula', 'expression' => 'total_amount > 9999', 'message_template' => 'Amount is too low.'],
        ]);
    }

    public function test_formula_condition_passes_when_no_provider_registered(): void
    {
        // No provider → fail-open: condition passes
        $order = $this->createOrder();

        $result = $this->evaluator->evaluate($order, [
            ['type' => 'formula', 'expression' => 'false', 'message_template' => 'Should not matter.'],
        ]);

        $this->assertTrue($result);
    }

    public function test_formula_condition_interpolates_message_via_provider(): void
    {
        $this->registry->register($this->makeProvider(false, 'Totale: € 100,00'));
        $order = $this->createOrder(['total_amount' => 100.0]);

        try {
            $this->evaluator->evaluate($order, [
                ['type' => 'formula', 'expression' => 'false', 'message_template' => 'Totale: {{ currency(total_amount) }}'],
            ]);
            $this->fail('Expected FormulaConditionFailedException');
        } catch (FormulaConditionFailedException $e) {
            $this->assertStringContainsString('€ 100,00', $e->getMessage());
        }
    }

    public function test_mixed_field_and_formula_conditions_all_must_pass(): void
    {
        $this->registry->register($this->makeProvider(true));
        $order = $this->createOrder(['total_amount' => 100.0, 'state' => 'pending']);

        // Both conditions pass
        $result = $this->evaluator->evaluate($order, [
            ['field' => 'state', 'operator' => '=', 'value' => 'pending'],
            ['type' => 'formula', 'expression' => 'total_amount > 0', 'message_template' => 'Err'],
        ]);

        $this->assertTrue($result);
    }

    public function test_field_condition_fails_before_formula_is_evaluated(): void
    {
        // Provider returns true, but field condition fails first → evaluate() returns false (no
        // exception)
        $this->registry->register($this->makeProvider(true));
        $order = $this->createOrder(['state' => 'processing']);

        $result = $this->evaluator->evaluate($order, [
            ['field' => 'state', 'operator' => '=', 'value' => 'pending'],
            ['type' => 'formula', 'expression' => 'true', 'message_template' => 'Err'],
        ]);

        $this->assertFalse($result);
    }

    public function test_message_key_alias_for_message_template(): void
    {
        $this->registry->register($this->makeProvider(false, 'Error from alias.'));
        $order = $this->createOrder();

        try {
            $this->evaluator->evaluate($order, [
                ['type' => 'formula', 'expression' => 'false', 'message' => 'Error from alias.'],
            ]);
            $this->fail('Expected FormulaConditionFailedException');
        } catch (FormulaConditionFailedException $e) {
            $this->assertSame('Error from alias.', $e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────────────────
    // HasDatabaseTransitions integration
    // ──────────────────────────────────────────────────────────────

    public function test_can_transition_to_returns_false_when_formula_condition_fails(): void
    {
        $this->registry->register($this->makeProvider(false, 'Not allowed.'));

        [$order, $pending, $processing] = $this->createOrderWorkflowWithFormulaCondition();

        $this->assertFalse($order->canTransitionTo('processing'));
    }

    public function test_can_transition_to_returns_true_when_formula_condition_passes(): void
    {
        $this->registry->register($this->makeProvider(true));

        [$order, $pending, $processing] = $this->createOrderWorkflowWithFormulaCondition();

        $this->assertTrue($order->canTransitionTo('processing'));
    }

    public function test_get_available_transitions_excludes_transitions_with_failing_formula(): void
    {
        $this->registry->register($this->makeProvider(false, 'Not allowed.'));

        [$order] = $this->createOrderWorkflowWithFormulaCondition();

        $available = $order->getAvailableTransitions();

        $this->assertCount(0, $available);
    }

    public function test_get_available_transitions_includes_transitions_with_passing_formula(): void
    {
        $this->registry->register($this->makeProvider(true));

        [$order] = $this->createOrderWorkflowWithFormulaCondition();

        $available = $order->getAvailableTransitions();

        $this->assertCount(1, $available);
        $this->assertSame('pending_to_processing', $available->first()->name);
    }

    public function test_transition_to_throws_formula_exception_when_condition_fails(): void
    {
        $this->registry->register($this->makeProvider(false, 'Budget exceeded.'));

        [$order] = $this->createOrderWorkflowWithFormulaCondition();

        $this->expectException(FormulaConditionFailedException::class);
        $this->expectExceptionMessage('Budget exceeded.');

        $order->transitionTo('processing');
    }

    public function test_execute_action_throws_condition_not_met_exception_with_formula_message(): void
    {
        $this->registry->register($this->makeProvider(false, 'Score too low.'));

        $workflow = $this->createTestWorkflow(['model_type' => Order::class]);
        $pending = $this->createWorkflowState($workflow, ['name' => 'pending', 'is_initial' => true]);
        $this->createWorkflowTransition($workflow, $pending, $pending, [
            'name' => 'score_check',
            'label' => 'Score Check',
            'to_state_id' => null, // action: no state change
            'conditions' => [
                ['type' => 'formula', 'expression' => 'score >= 60', 'message_template' => 'Score too low.'],
            ],
        ]);

        $order = Order::create([
            'order_number' => 'ORD-ACT-001',
            'customer_name' => 'Test',
            'total_amount' => 100.0,
            'state' => 'pending',
        ]);

        try {
            $order->executeAction('score_check');
            $this->fail('Expected ConditionNotMetException');
        } catch (ConditionNotMetException $e) {
            $this->assertSame('score_check', $e->actionName);
            $this->assertStringContainsString('Score too low.', $e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────────────────
    // FormulaConditionRegistry
    // ──────────────────────────────────────────────────────────────

    public function test_registry_has_returns_false_when_empty(): void
    {
        $this->assertFalse($this->registry->has());
    }

    public function test_registry_has_returns_true_after_registration(): void
    {
        $this->registry->register($this->makeProvider(true));
        $this->assertTrue($this->registry->has());
    }

    public function test_registry_clear_removes_provider(): void
    {
        $this->registry->register($this->makeProvider(true));
        $this->registry->clear();
        $this->assertFalse($this->registry->has());
    }

    public function test_registry_get_throws_when_no_provider(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->registry->get();
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    private function makeProvider(bool $result, string $interpolated = ''): FormulaConditionProvider
    {
        return new class($result, $interpolated) implements FormulaConditionProvider
        {
            public function __construct(
                private readonly bool $result,
                private readonly string $interpolated,
            ) {}

            public function evaluate(string $expression, Model $model, array $data = []): bool
            {
                return $this->result;
            }

            public function interpolate(string $template, Model $model, array $data = []): string
            {
                return $this->interpolated !== '' ? $this->interpolated : $template;
            }
        };
    }

    private function createOrder(array $data = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-'.uniqid(),
            'customer_name' => 'Test Customer',
            'total_amount' => 100.0,
            'state' => 'pending',
        ], $data));
    }

    /** @return array{Order, mixed, mixed} */
    private function createOrderWorkflowWithFormulaCondition(): array
    {
        $workflow = $this->createTestWorkflow(['model_type' => Order::class]);
        $pending = $this->createWorkflowState($workflow, ['name' => 'pending', 'is_initial' => true]);
        $processing = $this->createWorkflowState($workflow, ['name' => 'processing']);

        $this->createWorkflowTransition($workflow, $pending, $processing, [
            'name' => 'pending_to_processing',
            'label' => 'Process',
            'conditions' => [
                ['type' => 'formula', 'expression' => 'total_amount > 0', 'message_template' => 'Amount invalid.'],
            ],
        ]);

        $order = Order::create([
            'order_number' => 'ORD-FORMULA-'.uniqid(),
            'customer_name' => 'Test',
            'total_amount' => 100.0,
            'state' => 'pending',
        ]);

        return [$order, $pending, $processing];
    }
}
