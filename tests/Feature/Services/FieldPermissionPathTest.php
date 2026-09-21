<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Services;

use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateField;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateFieldRole;
use RoBYCoNTe\FilamentFlow\Services\WorkflowFieldPermissionsService;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * Nested field paths: a rule on `costs` covers `costs.amount`, a rule on
 * `costs.amount` overrides its ancestors attribute by attribute, and role
 * overrides are applied after every base rule of the chain.
 */
class FieldPermissionPathTest extends TestCase
{
    private Workflow $workflow;

    private WorkflowState $state;

    private User $admin;

    private User $editor;

    private WorkflowFieldPermissionsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('filament-flow.state_access.enabled', true);
        config()->set('filament-flow.state_access.owner_field', 'user_id');

        $this->service = new WorkflowFieldPermissionsService;
        $this->workflow = $this->createTestWorkflow();
        $this->state = $this->createWorkflowState($this->workflow, [
            'name' => 'review',
            'is_initial' => true,
        ]);

        $this->admin = $this->createTestUser(['email' => 'admin@test.com', 'role' => 'admin']);
        $this->editor = $this->createTestUser(['email' => 'editor@test.com', 'role' => 'editor']);
    }

    private function rule(string $fieldName, string $visibility = 'visible', string $mutability = 'editable', bool $required = false): WorkflowStateField
    {
        return WorkflowStateField::create([
            'state_id' => $this->state->id,
            'field_name' => $fieldName,
            'visibility' => $visibility,
            'mutability' => $mutability,
            'is_required' => $required,
        ]);
    }

    private function order(): Order
    {
        return Order::create([
            'order_number' => 'ORD-'.uniqid(),
            'customer_name' => 'Test Customer',
            'total_amount' => 100,
            'state' => 'review',
            'user_id' => $this->editor->id,
        ]);
    }

    public function test_exact_rule_applies_to_itself(): void
    {
        $this->rule('notes', 'hidden', 'readonly');

        $permission = $this->service->permissionFor($this->order(), 'notes');

        $this->assertNotNull($permission);
        $this->assertFalse($permission['visible']);
        $this->assertTrue($permission['readonly']);
    }

    public function test_ancestor_rule_applies_to_nested_path(): void
    {
        $this->rule('costs', 'visible', 'locked');

        $permission = $this->service->permissionFor($this->order(), 'costs.amount');

        $this->assertNotNull($permission);
        $this->assertTrue($permission['visible']);
        $this->assertTrue($permission['locked']);
    }

    public function test_hidden_ancestor_hides_nested_path(): void
    {
        $this->rule('documents', 'hidden');

        $permission = $this->service->permissionFor($this->order(), 'documents.contract.file');

        $this->assertNotNull($permission);
        $this->assertFalse($permission['visible']);
    }

    public function test_most_specific_rule_wins_attribute_by_attribute(): void
    {
        $this->rule('costs', 'visible', 'locked', required: true);
        $this->rule('costs.amount', 'visible', 'editable');

        $amount = $this->service->permissionFor($this->order(), 'costs.amount');
        $vat = $this->service->permissionFor($this->order(), 'costs.vat');

        // The specific rule replaces the ancestor config: mutability *and* required.
        $this->assertFalse($amount['locked']);
        $this->assertFalse($amount['readonly']);
        $this->assertFalse($amount['required']);

        // A sibling keeps the ancestor rule.
        $this->assertTrue($vat['locked']);
        $this->assertTrue($vat['required']);
    }

    public function test_unconfigured_path_returns_null(): void
    {
        $this->rule('notes');

        // An unrelated path is unconfigured.
        $this->assertNull($this->service->permissionFor($this->order(), 'applicant.name'));

        // A descendant of a configured rule inherits it.
        $this->assertNotNull($this->service->permissionFor($this->order(), 'notes.extra'));
    }

    public function test_no_rules_at_all_returns_null(): void
    {
        $this->assertNull($this->service->permissionFor($this->order(), 'notes'));
    }

    public function test_role_override_on_ancestor_beats_specific_base_rule(): void
    {
        $parent = $this->rule('costs', 'visible', 'locked');
        $this->rule('costs.amount', 'visible', 'locked');

        WorkflowStateFieldRole::create([
            'state_field_id' => $parent->id,
            'role_name' => 'admin',
            'visibility' => null,
            'mutability' => 'editable',
            'is_required' => null,
        ]);

        $asAdmin = $this->service->permissionFor($this->order(), 'costs.amount', $this->admin);
        $asEditor = $this->service->permissionFor($this->order(), 'costs.amount', $this->editor);

        $this->assertFalse($asAdmin['locked'], 'The role override on the ancestor wins over the nested base rule.');
        $this->assertTrue($asEditor['locked']);
    }

    public function test_role_override_on_the_most_specific_rule_wins(): void
    {
        $parent = $this->rule('costs', 'visible', 'locked');
        $child = $this->rule('costs.amount', 'visible', 'locked');

        WorkflowStateFieldRole::create([
            'state_field_id' => $parent->id,
            'role_name' => 'admin',
            'visibility' => 'hidden',
            'mutability' => 'editable',
            'is_required' => null,
        ]);

        WorkflowStateFieldRole::create([
            'state_field_id' => $child->id,
            'role_name' => 'admin',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => null,
        ]);

        $permission = $this->service->permissionFor($this->order(), 'costs.amount', $this->admin);

        $this->assertTrue($permission['visible']);
        $this->assertFalse($permission['locked']);
    }

    public function test_concern_helpers_support_nested_paths(): void
    {
        $this->rule('costs', 'hidden', 'locked');
        $this->rule('review.score', 'visible', 'readonly');

        $order = $this->order();

        $this->assertFalse($order->isFieldVisible('costs.amount', $this->admin));
        $this->assertFalse($order->isFieldReadonly('costs.amount', $this->admin));
        $this->assertTrue($order->isFieldReadonly('review.score', $this->admin));

        // Unconfigured fields stay visible and editable.
        $this->assertTrue($order->isFieldVisible('customer_name', $this->admin));
        $this->assertFalse($order->isFieldReadonly('customer_name', $this->admin));
    }

    public function test_top_level_rules_are_unaffected(): void
    {
        $this->rule('costs', 'visible', 'locked');

        $permissions = $this->service->getFieldPermissions($this->order());

        $this->assertArrayHasKey('costs', $permissions);
        $this->assertTrue($permissions['costs']['locked']);
        $this->assertArrayNotHasKey('costs.amount', $permissions);
    }
}
