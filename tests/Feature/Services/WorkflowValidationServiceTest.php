<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Services;

use Closure;
use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Contracts\FieldRuleSource;
use RoBYCoNTe\FilamentFlow\Contracts\FormulaConditionProvider;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateField;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionValidationRule;
use RoBYCoNTe\FilamentFlow\Services\WorkflowValidationService;
use RoBYCoNTe\FilamentFlow\Support\FormulaConditionRegistry;
use RoBYCoNTe\FilamentFlow\Support\ValidationRuleRegistry;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * One validation pass for every source of truth of a state: field permissions,
 * transition rules, host field rules and expression rules.
 */
class WorkflowValidationServiceTest extends TestCase
{
    public function test_a_required_field_of_the_state_is_reported_when_missing(): void
    {
        [$order, $transition] = $this->workflow();

        $result = $this->service()->validate($order, null, ['customer_name' => 'Ada'], $transition);

        $this->assertTrue($result->has('order_number'));
        $this->assertFalse($result->has('customer_name'));
        $this->assertSame(1, $result->count());
    }

    public function test_a_hidden_field_is_neither_required_nor_validated(): void
    {
        [$order, $transition] = $this->workflow(hiddenRequired: true);

        $result = $this->service()->validate($order, null, [], $transition);

        $this->assertFalse($result->has('order_number'), 'A hidden field cannot be missing.');
    }

    public function test_transition_rules_use_their_custom_message(): void
    {
        [$order, $transition] = $this->workflow();

        WorkflowTransitionValidationRule::create([
            'transition_id' => $transition->id,
            'field_name' => 'total_amount',
            'rules' => ['gte:60'],
            'custom_message' => 'The review score must be at least 60.',
            'sort_order' => 0,
        ]);

        $result = $this->service()->validate($order, null, ['order_number' => 'A-1', 'customer_name' => 'Ada', 'total_amount' => 10], $transition);

        $this->assertTrue($result->has('total_amount'));
        $this->assertSame(['The review score must be at least 60.'], $result->messagesFor('total_amount'));
    }

    public function test_a_conditional_rule_is_skipped_when_its_condition_is_false(): void
    {
        [$order, $transition] = $this->workflow();

        WorkflowTransitionValidationRule::create([
            'transition_id' => $transition->id,
            'field_name' => 'total_amount',
            'rules' => ['gte:60'],
            'condition' => 'form.requires_review == true',
            'sort_order' => 0,
        ]);

        $registry = app(FormulaConditionRegistry::class);
        $registry->clear();
        $registry->register($this->formProvider());

        $data = ['order_number' => 'A-1', 'customer_name' => 'Ada', 'total_amount' => 10, 'requires_review' => false];

        $this->assertFalse(
            $this->service()->validate($order, null, $data, $transition)->has('total_amount'),
            'The condition is false: the rule does not apply.'
        );

        $data['requires_review'] = true;

        $this->assertTrue(
            $this->service()->validate($order, null, $data, $transition)->has('total_amount'),
            'The condition is true: the rule applies.'
        );
    }

    public function test_an_expression_rule_reads_the_live_form_state(): void
    {
        [$order, $transition] = $this->workflow();

        WorkflowTransitionValidationRule::create([
            'transition_id' => $transition->id,
            'field_name' => 'total_amount',
            'rules' => ['form.total_amount <= form.budget'],
            'rule_type' => 'expression',
            'custom_message' => 'Over budget: {{ form.total_amount }} of {{ form.budget }}.',
            'sort_order' => 0,
        ]);

        $registry = app(FormulaConditionRegistry::class);
        $registry->clear();
        $registry->register($this->formProvider());

        $result = $this->service()->validate($order, null, [
            'order_number' => 'A-1',
            'customer_name' => 'Ada',
            'total_amount' => 150,
            'budget' => 100,
        ], $transition);

        $this->assertTrue($result->has('total_amount'));
        $this->assertSame(['Over budget: 150 of 100.'], $result->messagesFor('total_amount'));
    }

    public function test_host_field_rules_are_run(): void
    {
        [$order, $transition] = $this->workflow();

        config()->set('filament-flow.validation.rule_sources', [HostRuleSource::class]);
        $this->app->bind(HostRuleSource::class, fn () => new HostRuleSource);

        $result = $this->service()->validate($order, null, [
            'order_number' => 'A-1',
            'customer_name' => 'Ada',
            'customer_email' => 'not-an-email',
        ], $transition);

        $this->assertTrue($result->has('customer_email'));
    }

    public function test_named_registry_rules_are_resolved(): void
    {
        [$order, $transition] = $this->workflow();

        app(ValidationRuleRegistry::class)->register('always_fails', static function (string $attribute, mixed $value, Closure $fail): void {
            $fail('Nope.');
        });

        WorkflowTransitionValidationRule::create([
            'transition_id' => $transition->id,
            'field_name' => 'order_number',
            'rules' => ['always_fails'],
            'sort_order' => 0,
        ]);

        $result = $this->service()->validate($order, null, ['order_number' => 'A-1', 'customer_name' => 'Ada'], $transition);

        $this->assertSame(['Nope.'], $result->messagesFor('order_number'));
    }

    public function test_the_target_state_decides_which_fields_are_required(): void
    {
        [$order, $transition] = $this->workflow(requiredOnTargetState: true);

        $inCurrentState = $this->service()->validate($order, null, ['order_number' => 'A-1'], $transition);
        $this->assertFalse($inCurrentState->has('tracking_number'));

        $onTargetState = $this->service()->validate($order, null, ['order_number' => 'A-1'], $transition, 'shipped');
        $this->assertTrue($onTargetState->has('tracking_number'));
    }

    public function test_values_are_read_from_the_record_when_no_form_state_is_passed(): void
    {
        [$order, $transition] = $this->workflow();

        // No data at all: the engine reads the persisted attributes, so the
        // required fields that are filled on the record produce no error.
        $result = $this->service()->validate($order, null, [], $transition);

        $this->assertTrue($result->isEmpty(), 'Persisted values satisfy the required rules: '.json_encode($result->errors()));
    }

    public function test_the_messages_use_the_labels_of_the_record(): void
    {
        [$order, $transition] = $this->workflow(trackingRequiredInSource: true);

        $result = $this->service()->validate($order->fresh(), null, [], $transition);

        // The message names the field with the label the record declares, so a
        // person reads "Customer name" and not "customer_name".
        $this->assertStringContainsString('Tracking number', implode(' ', $result->messagesFor('tracking_number')));
    }

    public function test_a_named_rule_receives_its_parameters(): void
    {
        [$order, $transition] = $this->workflow();

        app(ValidationRuleRegistry::class)->register(
            'needs_the_label',
            fn (string $attribute, mixed $value, Closure $fail, ?string $parameters = null): mixed => $fail('manca: '.$parameters),
        );

        WorkflowTransitionValidationRule::create([
            'transition_id' => $transition->id,
            'field_name' => 'tracking_number',
            'rules' => ['needs_the_label:DHL|Corriere'],
            'sort_order' => 0,
        ]);

        $result = $this->service()->validate($order, null, ['tracking_number' => 'X'], $transition);

        $this->assertSame(['manca: DHL|Corriere'], $result->messagesFor('tracking_number'));
    }

    public function test_the_translations_of_the_package_are_loaded(): void
    {
        app()->setLocale('it');

        $this->assertSame(
            'Il valore di Allegati non è valido.',
            __('The value of :field is not valid.', ['field' => 'Allegati']),
        );
    }

    // ── fixtures ─────────────────────────────────────────────────────────────

    /**
     * @return array{0: Order, 1: WorkflowTransition}
     */
    private function workflow(
        bool $hiddenRequired = false,
        bool $requiredOnTargetState = false,
        bool $trackingRequiredInSource = false,
    ): array {
        $workflow = $this->createTestWorkflow();

        $pending = $this->createWorkflowState($workflow, ['name' => 'pending', 'is_initial' => true]);
        $shipped = $this->createWorkflowState($workflow, ['name' => 'shipped']);

        $transition = $this->createWorkflowTransition($workflow, $pending, $shipped, ['name' => 'ship']);

        WorkflowStateField::create([
            'state_id' => $pending->id,
            'field_name' => 'order_number',
            'visibility' => $hiddenRequired ? 'hidden' : 'visible',
            'mutability' => 'editable',
            'is_required' => true,
        ]);

        WorkflowStateField::create([
            'state_id' => $pending->id,
            'field_name' => 'customer_name',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => true,
        ]);

        if ($trackingRequiredInSource) {
            WorkflowStateField::create([
                'state_id' => $pending->id,
                'field_name' => 'tracking_number',
                'visibility' => 'visible',
                'mutability' => 'editable',
                'is_required' => true,
            ]);
        }

        if ($requiredOnTargetState) {
            WorkflowStateField::create([
                'state_id' => $shipped->id,
                'field_name' => 'tracking_number',
                'visibility' => 'visible',
                'mutability' => 'editable',
                'is_required' => true,
            ]);
        }

        $order = Order::create([
            'order_number' => 'ORD-1',
            'customer_name' => 'Ada Lovelace',
            'customer_email' => 'ada@example.com',
            'total_amount' => 100.0,
            'tracking_number' => ($requiredOnTargetState || $trackingRequiredInSource) ? null : 'TRACK-1',
            'state' => 'pending',
        ]);

        return [$order, $transition];
    }

    private function service(): WorkflowValidationService
    {
        return app(WorkflowValidationService::class);
    }

    /**
     * Minimal provider: `form.x` reads the live data, everything else is false.
     * Stands in for the host expression engine.
     */
    private function formProvider(): FormulaConditionProvider
    {
        return new class implements FormulaConditionProvider
        {
            public function evaluate(string $expression, Model $model, array $data = []): bool
            {
                $expression = trim($expression);

                if (! preg_match('/^form\\.([a-z_]+)\\s*(<=|>=|==|!=|<|>)\\s*(.+)$/i', $expression, $matches)) {
                    return false;
                }

                [, $path, $operator, $right] = $matches;
                $left = $data[$path] ?? null;

                if (preg_match('/^form\\.([a-z_]+)$/i', trim($right), $rightMatches)) {
                    $right = $data[$rightMatches[1]] ?? null;
                } else {
                    $right = is_numeric($right) ? (float) $right : ($right === 'true' ? true : ($right === 'false' ? false : trim($right, "'\"")));
                }

                return match ($operator) {
                    '<=' => $left <= $right,
                    '>=' => $left >= $right,
                    '==' => $left == $right,
                    '!=' => $left != $right,
                    '<' => $left < $right,
                    '>' => $left > $right,
                };
            }

            public function interpolate(string $template, Model $model, array $data = []): string
            {
                return (string) preg_replace_callback(
                    '/\\{\\{\\s*form\\.([a-z_]+)\\s*\\}\\}/i',
                    static fn (array $m): string => (string) ($data[$m[1]] ?? ''),
                    $template,
                );
            }
        };
    }
}

/**
 * Host field rule source used by the tests: it declares a rule on a field the
 * workflow does not know about.
 */
class HostRuleSource implements FieldRuleSource
{
    /** @return array<string, list<string>> */
    public function rulesFor(Model $record, ?Model $user): array
    {
        return ['customer_email' => ['email']];
    }
}
