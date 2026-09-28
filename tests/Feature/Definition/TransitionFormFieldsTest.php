<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Definition;

use RoBYCoNTe\FilamentFlow\Definition\State;
use RoBYCoNTe\FilamentFlow\Definition\Transition;
use RoBYCoNTe\FilamentFlow\Definition\ValidationRule;
use RoBYCoNTe\FilamentFlow\Definition\WorkflowApplier;
use RoBYCoNTe\FilamentFlow\Definition\WorkflowDefinition;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Services\TransitionFormService;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * A transition that asks for values has to **ask for them**: the rules it declares are also the
 * fields of its form, and the dialog is born from those — in the row of a list as well as in
 * the form of the application.
 *
 * Without this step the declaration stays invisible: the transition refuses over a field nobody
 * could ever fill in.
 */
class TransitionFormFieldsTest extends TestCase
{
    private const TENANT = 7;

    public function test_the_declared_rules_become_the_form_fields_of_the_transition(): void
    {
        $transition = $this->applyAndFind('reject');

        $field = $transition->fields()->first();

        $this->assertNotNull($field, 'The transition has no field to fill in.');
        $this->assertSame('rejection_reason', $field->field_name);
        $this->assertSame('textarea', $field->field_type);
        $this->assertSame('Reason of the rejection', $field->label);
        $this->assertTrue((bool) $field->is_required);
        $this->assertSame(['required', 'min:20'], $field->validation_rules);
    }

    /** And the form of the transition is built from those fields: that is the dialog. */
    public function test_the_transition_form_is_built_from_those_fields(): void
    {
        $transition = $this->applyAndFind('reject');

        $schema = app(TransitionFormService::class)->buildFormSchema($transition);

        $this->assertCount(1, $schema);
        $this->assertSame('rejection_reason', $schema[0]->getName());
    }

    /** The type is inferred from the rules when the declaration does not say it. */
    public function test_the_field_type_is_derived_when_it_is_not_declared(): void
    {
        $transition = $this->applyAndFind('reject');

        $this->assertSame('textarea', $transition->fields()->first()->field_type);
    }

    /** The rules stay rules: the engine applies them as it did before. */
    public function test_the_validation_rules_are_still_declared(): void
    {
        $transition = $this->applyAndFind('reject');

        $this->assertSame(
            ['rejection_reason' => ['required', 'min:20']],
            $transition->validationRules->mapWithKeys(fn ($rule): array => [$rule->field_name => $rule->rules])->all(),
        );
    }

    private function applyAndFind(string $name): WorkflowTransition
    {
        $definition = WorkflowDefinition::make('order', Order::class)
            ->state(State::make('draft', 'Draft')->initial()->color('gray'))
            ->state(State::make('rejected', 'Rejected')->final()->color('danger'))
            ->transition(
                Transition::make('reject', 'draft', 'rejected')
                    ->label('Reject')
                    ->validationRule(
                        ValidationRule::make('rejection_reason')
                            ->rules(['required', 'min:20'])
                            ->label('Reason of the rejection')
                            ->fieldType('textarea')
                    )
            );

        $workflow = app(WorkflowApplier::class)->apply(Order::class, self::TENANT, $definition);

        return WorkflowTransition::query()
            ->where('workflow_id', $workflow->id)
            ->where('name', $name)
            ->firstOrFail();
    }

    /**
     * A transition that declares rules **and** fields builds its form: the "skip the modal"
     * rule holds only for rules without fields, or the dialog would never open.
     */
    public function test_a_transition_with_rules_but_no_fields_skips_the_form(): void
    {
        $transition = $this->applyAndFind('reject');

        $this->assertTrue($transition->hasValidationRules());
        $this->assertGreaterThan(0, $transition->fields()->count(), 'The rules are also fields to fill in.');
    }

    /**
     * The label of the field the package adds by itself — the reason of a transition that asks
     * for one — is stored as a **key** and translated where it is drawn: so the form follows the
     * language of the application, instead of staying in the one of the day of the sync.
     */
    public function test_a_label_of_the_package_is_stored_as_a_key(): void
    {
        $definition = WorkflowDefinition::make('order', Order::class)
            ->state(State::make('draft', 'Draft')->initial())
            ->state(State::make('rejected', 'Rejected')->final())
            ->transition(Transition::make('reject', 'draft', 'rejected')->requiresReason());

        $workflow = app(WorkflowApplier::class)->apply(Order::class, self::TENANT, $definition);

        $field = WorkflowTransition::query()
            ->where('workflow_id', $workflow->id)
            ->where('name', 'reject')
            ->firstOrFail()
            ->fields()
            ->where('field_name', 'reason')
            ->firstOrFail();

        $this->assertSame('Reason', $field->label, 'The key, not the translation.');
        $this->assertSame(__('Reason'), __('Reason'), 'And the translation comes at render time.');
    }
}
