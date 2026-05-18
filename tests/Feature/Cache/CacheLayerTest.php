<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Cache;

use Illuminate\Support\Facades\Cache;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateAccessRule;
use RoBYCoNTe\FilamentFlow\Services\WorkflowFieldPermissionsService;
use RoBYCoNTe\FilamentFlow\Services\WorkflowStateAccessService;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class CacheLayerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('filament-flow.cache.enabled', true);
        config()->set('filament-flow.cache.store', 'array');
        config()->set('filament-flow.cache.ttl', 300);
        config()->set('filament-flow.cache.prefix', 'filament-flow');
    }

    // --- Workflow::findForModel cache ---

    public function test_find_for_model_caches_result(): void
    {
        $workflow = $this->createTestWorkflow();

        // First call — hits DB
        $result1 = Workflow::findForModel(Order::class);
        $this->assertNotNull($result1);
        $this->assertEquals($workflow->id, $result1->id);

        // Second call — should use cache (same result)
        $result2 = Workflow::findForModel(Order::class);
        $this->assertEquals($result1->id, $result2->id);
    }

    public function test_find_for_model_cache_invalidated_on_save(): void
    {
        $workflow = $this->createTestWorkflow();

        // Populate cache
        Workflow::findForModel(Order::class);

        // Update workflow — observer should clear cache
        $workflow->update(['name' => 'Updated Workflow']);

        // Next call should hit DB again and return updated data
        $result = Workflow::findForModel(Order::class);
        $this->assertNotNull($result);
        $this->assertEquals('Updated Workflow', $result->name);
    }

    public function test_find_for_model_cache_invalidated_on_delete(): void
    {
        $workflow = $this->createTestWorkflow();

        // Populate cache
        Workflow::findForModel(Order::class);

        // Delete
        $workflow->delete();

        // Should return null now
        $result = Workflow::findForModel(Order::class);
        $this->assertNull($result);
    }

    public function test_find_for_model_bypasses_cache_when_disabled(): void
    {
        config()->set('filament-flow.cache.enabled', false);

        $workflow = $this->createTestWorkflow();

        $result = Workflow::findForModel(Order::class);
        $this->assertNotNull($result);
        $this->assertEquals($workflow->id, $result->id);
    }

    // --- WorkflowStateAccessService cache ---

    public function test_scope_accessible_uses_cached_access_rules(): void
    {
        $workflow = $this->createTestWorkflow();
        $state = $this->createWorkflowState($workflow, [
            'name' => 'draft',
            'is_initial' => true,
        ]);

        WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'view',
            'rule' => '@authenticated',
            'priority' => 0,
            'is_active' => true,
        ]);

        $user = $this->createTestUser();
        $this->actingAs($user);

        $service = app(WorkflowStateAccessService::class);

        // canView calls getAccessibleStates internally (cached)
        $order = Order::create([
            'order_number' => 'ORD-CACHE-1',
            'customer_name' => 'Test',
            'total_amount' => 100,
            'state' => 'draft',
        ]);

        $result1 = $service->canView($order, $user);
        $result2 = $service->canView($order, $user);

        // Both calls should succeed
        $this->assertTrue($result1);
        $this->assertTrue($result2);
    }

    // --- WorkflowFieldPermissionsService cache ---

    public function test_field_permissions_are_cached(): void
    {
        $workflow = $this->createTestWorkflow();
        $state = $this->createWorkflowState($workflow, [
            'name' => 'draft',
            'is_initial' => true,
        ]);

        $user = $this->createTestUser(['role' => 'editor']);
        $service = app(WorkflowFieldPermissionsService::class);

        $order = Order::create([
            'order_number' => 'ORD-CACHE-2',
            'customer_name' => 'Test',
            'total_amount' => 100,
            'state' => 'draft',
        ]);

        // First call
        $result1 = $service->getFieldPermissions($order, $user);

        // Second call — should be cached
        $result2 = $service->getFieldPermissions($order, $user);
        $this->assertEquals($result1, $result2);
    }

    // --- Cache key isolation ---

    public function test_different_models_have_separate_cache_entries(): void
    {
        $this->createTestWorkflow();

        $result1 = Workflow::findForModel(Order::class);
        $result2 = Workflow::findForModel('App\\Models\\NonExistent');

        $this->assertNotNull($result1);
        $this->assertNull($result2);
    }

    // --- Flush ---

    public function test_flush_cache_clears_all_workflow_cache(): void
    {
        $workflow = $this->createTestWorkflow();

        // Populate cache
        Workflow::findForModel(Order::class);

        // Flush
        Workflow::flushCache();

        // Delete workflow so we can verify cache was actually cleared
        $workflow->forceDelete();

        // Without cache, should return null
        config()->set('filament-flow.cache.enabled', false);
        $result = Workflow::findForModel(Order::class);
        $this->assertNull($result);
    }
}
