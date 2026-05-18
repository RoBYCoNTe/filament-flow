<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionField;
use RoBYCoNTe\FilamentFlow\Services\TransitionFormService;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class TransitionFormServiceTest extends TestCase
{
    private TransitionFormService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TransitionFormService;
    }

    // ── getTransitionConfig ────────────────────────────────────────────

    public function test_get_transition_config_finds_by_state_names(): void
    {
        $workflow = $this->createTestWorkflow();
        $from = $this->createWorkflowState($workflow, ['name' => 'pending']);
        $to = $this->createWorkflowState($workflow, ['name' => 'processing']);
        $transition = $this->createWorkflowTransition($workflow, $from, $to, ['name' => 'start']);

        $result = $this->service->getTransitionConfig(Order::class, 'pending', 'processing');

        $this->assertNotNull($result);
        $this->assertEquals($transition->id, $result->id);
    }

    public function test_get_transition_config_returns_null_without_workflow(): void
    {
        $result = $this->service->getTransitionConfig('NonExistent\\Model', 'a', 'b');
        $this->assertNull($result);
    }

    public function test_get_transition_config_returns_null_for_unknown_states(): void
    {
        $this->createTestWorkflow();
        $result = $this->service->getTransitionConfig(Order::class, 'unknown', 'nope');
        $this->assertNull($result);
    }

    // ── buildFormSchema ────────────────────────────────────────────────

    public function test_build_form_schema_text_input(): void
    {
        $transition = $this->createTransitionWithField('text', 'customer_name', 'Customer Name');
        $schema = $this->service->buildFormSchema($transition);

        $this->assertCount(1, $schema);
        $this->assertInstanceOf(TextInput::class, $schema[0]);
    }

    public function test_build_form_schema_email_input(): void
    {
        $transition = $this->createTransitionWithField('email', 'email', 'Email');
        $schema = $this->service->buildFormSchema($transition);

        $this->assertCount(1, $schema);
        $this->assertInstanceOf(TextInput::class, $schema[0]);
    }

    public function test_build_form_schema_number_input(): void
    {
        $transition = $this->createTransitionWithField('number', 'amount', 'Amount');
        $schema = $this->service->buildFormSchema($transition);

        $this->assertCount(1, $schema);
        $this->assertInstanceOf(TextInput::class, $schema[0]);
    }

    public function test_build_form_schema_textarea(): void
    {
        $transition = $this->createTransitionWithField('textarea', 'notes', 'Notes');
        $schema = $this->service->buildFormSchema($transition);

        $this->assertCount(1, $schema);
        $this->assertInstanceOf(Textarea::class, $schema[0]);
    }

    public function test_build_form_schema_select(): void
    {
        $transition = $this->createTransitionWithField('select', 'status', 'Status');
        $schema = $this->service->buildFormSchema($transition);

        $this->assertCount(1, $schema);
        $this->assertInstanceOf(Select::class, $schema[0]);
    }

    public function test_build_form_schema_date(): void
    {
        $transition = $this->createTransitionWithField('date', 'due_date', 'Due Date');
        $schema = $this->service->buildFormSchema($transition);

        $this->assertCount(1, $schema);
        $this->assertInstanceOf(DatePicker::class, $schema[0]);
    }

    public function test_build_form_schema_datetime(): void
    {
        $transition = $this->createTransitionWithField('datetime', 'processed_at', 'Processed At');
        $schema = $this->service->buildFormSchema($transition);

        $this->assertCount(1, $schema);
        $this->assertInstanceOf(DateTimePicker::class, $schema[0]);
    }

    public function test_build_form_schema_toggle(): void
    {
        $transition = $this->createTransitionWithField('toggle', 'is_active', 'Active');
        $schema = $this->service->buildFormSchema($transition);

        $this->assertCount(1, $schema);
        $this->assertInstanceOf(Toggle::class, $schema[0]);
    }

    public function test_build_form_schema_skips_fields_without_name(): void
    {
        $workflow = $this->createTestWorkflow();
        $from = $this->createWorkflowState($workflow, ['name' => 's1']);
        $to = $this->createWorkflowState($workflow, ['name' => 's2']);
        $transition = $this->createWorkflowTransition($workflow, $from, $to);

        WorkflowTransitionField::create([
            'transition_id' => $transition->id,
            'field_name' => '',
            'field_type' => 'text',
            'label' => 'Empty name',
            'sort_order' => 0,
        ]);

        $schema = $this->service->buildFormSchema($transition);
        $this->assertCount(0, $schema);
    }

    public function test_build_form_schema_skips_fields_without_type(): void
    {
        $workflow = $this->createTestWorkflow();
        $from = $this->createWorkflowState($workflow, ['name' => 's1']);
        $to = $this->createWorkflowState($workflow, ['name' => 's2']);
        $transition = $this->createWorkflowTransition($workflow, $from, $to);

        WorkflowTransitionField::create([
            'transition_id' => $transition->id,
            'field_name' => 'valid_name',
            'field_type' => '',
            'label' => 'No type',
            'sort_order' => 0,
        ]);

        $schema = $this->service->buildFormSchema($transition);
        $this->assertCount(0, $schema);
    }

    public function test_build_form_schema_required_field(): void
    {
        $transition = $this->createTransitionWithField('text', 'name', 'Name', [
            'is_required' => true,
        ]);

        $schema = $this->service->buildFormSchema($transition);
        $this->assertCount(1, $schema);
        $this->assertTrue($schema[0]->isRequired());
    }

    public function test_build_form_schema_with_field_config(): void
    {
        $transition = $this->createTransitionWithField('textarea', 'notes', 'Notes', [
            'field_config' => ['rows' => 5, 'placeholder' => 'Enter notes...'],
        ]);

        $schema = $this->service->buildFormSchema($transition);
        $this->assertCount(1, $schema);
        // Component was built without error — config was applied
        $this->assertInstanceOf(Textarea::class, $schema[0]);
    }

    public function test_build_form_schema_multiple_fields_sorted(): void
    {
        $workflow = $this->createTestWorkflow();
        $from = $this->createWorkflowState($workflow, ['name' => 's1']);
        $to = $this->createWorkflowState($workflow, ['name' => 's2']);
        $transition = $this->createWorkflowTransition($workflow, $from, $to);

        WorkflowTransitionField::create([
            'transition_id' => $transition->id,
            'field_name' => 'second_field',
            'field_type' => 'text',
            'label' => 'Second',
            'sort_order' => 2,
        ]);

        WorkflowTransitionField::create([
            'transition_id' => $transition->id,
            'field_name' => 'first_field',
            'field_type' => 'text',
            'label' => 'First',
            'sort_order' => 1,
        ]);

        $schema = $this->service->buildFormSchema($transition);
        $this->assertCount(2, $schema);
        $this->assertEquals('first_field', $schema[0]->getName());
        $this->assertEquals('second_field', $schema[1]->getName());
    }

    // ── applyTransitionDataToModel ─────────────────────────────────────

    public function test_apply_direct_mapping(): void
    {
        $order = $this->createOrder(['notes' => null]);
        $transition = $this->createTransitionWithField('textarea', 'transition_notes', 'Notes', [
            'mapping_type' => 'direct',
            'model_attribute' => 'notes',
            'save_to_model' => true,
        ]);

        $this->service->applyTransitionDataToModel($order, $transition, [
            'transition_notes' => 'Applied via mapping',
        ]);

        $this->assertEquals('Applied via mapping', $order->notes);
    }

    public function test_apply_ignore_mapping(): void
    {
        $order = $this->createOrder(['notes' => 'Original']);
        $transition = $this->createTransitionWithField('text', 'internal_note', 'Internal', [
            'mapping_type' => 'ignore',
            'save_to_model' => true,
        ]);

        $this->service->applyTransitionDataToModel($order, $transition, [
            'internal_note' => 'Should be ignored',
        ]);

        $this->assertEquals('Original', $order->notes);
    }

    public function test_skip_field_not_saved_to_model(): void
    {
        $order = $this->createOrder(['notes' => 'Original']);
        $transition = $this->createTransitionWithField('textarea', 'notes', 'Notes', [
            'mapping_type' => 'direct',
            'model_attribute' => 'notes',
            'save_to_model' => false,
        ]);

        $this->service->applyTransitionDataToModel($order, $transition, [
            'notes' => 'Should not be applied',
        ]);

        $this->assertEquals('Original', $order->notes);
    }

    public function test_skip_field_not_in_data(): void
    {
        $order = $this->createOrder(['notes' => 'Original']);
        $transition = $this->createTransitionWithField('textarea', 'missing_field', 'Missing', [
            'mapping_type' => 'direct',
            'model_attribute' => 'notes',
            'save_to_model' => true,
        ]);

        $this->service->applyTransitionDataToModel($order, $transition, []);

        $this->assertEquals('Original', $order->notes);
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function createOrder(array $data = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-TFS-'.uniqid(),
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
        ], $data));
    }

    private function createTransitionWithField(
        string $fieldType,
        string $fieldName,
        string $label,
        array $extra = []
    ) {
        $workflow = $this->createTestWorkflow();
        $from = $this->createWorkflowState($workflow, ['name' => 'from_'.uniqid()]);
        $to = $this->createWorkflowState($workflow, ['name' => 'to_'.uniqid()]);
        $transition = $this->createWorkflowTransition($workflow, $from, $to);

        WorkflowTransitionField::create(array_merge([
            'transition_id' => $transition->id,
            'field_name' => $fieldName,
            'field_type' => $fieldType,
            'label' => $label,
            'mapping_type' => 'direct',
            'sort_order' => 0,
            'is_required' => false,
            'save_to_model' => true,
        ], $extra));

        $transition->load('fields');

        return $transition;
    }
}
