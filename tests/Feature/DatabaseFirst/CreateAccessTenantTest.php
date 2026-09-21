<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateAccessRule;
use RoBYCoNTe\FilamentFlow\Services\WorkflowStateAccessService;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * Create access is resolved on the workflow of an explicit tenant, so hosts that
 * scope one workflow per owner (a bando per company, an order per marketplace)
 * can ask "may this user start a record for that owner?".
 */
class CreateAccessTenantTest extends TestCase
{
    private WorkflowStateAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(WorkflowStateAccessService::class);
    }

    private function workflowFor(int $tenantId, string $createRule): Workflow
    {
        $workflow = Workflow::create([
            'name' => 'Order workflow '.$tenantId,
            'model_type' => Order::class,
            'state_column' => 'state',
            'tenant_id' => $tenantId,
            'is_active' => true,
        ]);

        $state = $this->createWorkflowState($workflow, [
            'name' => 'draft',
            'is_initial' => true,
        ]);

        WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => WorkflowStateAccessRule::ACCESS_TYPE_CREATE,
            'rule' => $createRule,
            'priority' => 0,
            'is_active' => true,
        ]);

        return $workflow;
    }

    public function test_create_rules_follow_the_given_tenant(): void
    {
        $this->workflowFor(1, 'role:admin');
        $this->workflowFor(2, WorkflowStateAccessRule::RULE_ALL);

        $admin = $this->createTestUser(['email' => 'admin@test.com', 'role' => 'admin']);
        $editor = $this->createTestUser(['email' => 'editor@test.com', 'role' => 'editor']);

        // Tenant 1 only allows admins.
        $this->assertTrue($this->service->canCreate(Order::class, $admin, 1));
        $this->assertFalse($this->service->canCreate(Order::class, $editor, 1));

        // Tenant 2 allows everyone.
        $this->assertTrue($this->service->canCreate(Order::class, $editor, 2));
    }

    public function test_the_tenant_argument_is_optional(): void
    {
        $workflow = Workflow::create([
            'name' => 'Global order workflow',
            'model_type' => Order::class,
            'state_column' => 'state',
            'tenant_id' => null,
            'is_active' => true,
        ]);

        $state = $this->createWorkflowState($workflow, ['name' => 'draft', 'is_initial' => true]);

        WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => WorkflowStateAccessRule::ACCESS_TYPE_CREATE,
            'rule' => 'role:admin',
            'priority' => 0,
            'is_active' => true,
        ]);

        $admin = $this->createTestUser(['email' => 'admin@test.com', 'role' => 'admin']);
        $editor = $this->createTestUser(['email' => 'editor@test.com', 'role' => 'editor']);

        $this->assertTrue($this->service->canCreate(Order::class, $admin));
        $this->assertFalse($this->service->canCreate(Order::class, $editor));
    }
}
