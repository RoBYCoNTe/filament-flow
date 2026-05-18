<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Filament;

use Livewire\Livewire;
use RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowResource\Pages\CreateWorkflow;
use RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowResource\Pages\EditWorkflow;
use RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowResource\Pages\ListWorkflows;
use RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowResource\RelationManagers\NotificationsRelationManager;
use RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowResource\RelationManagers\StatesRelationManager;
use RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowResource\RelationManagers\TransitionsRelationManager;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Tests\FilamentTestCase;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;

class WorkflowResourcePagesTest extends FilamentTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'name' => 'Admin',
            'email' => 'workflow-admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
        $this->actingAs($this->user);
    }

    public function test_workflow_list_page_renders(): void
    {
        Livewire::test(ListWorkflows::class)
            ->assertSuccessful();
    }

    public function test_workflow_create_page_renders(): void
    {
        Livewire::test(CreateWorkflow::class)
            ->assertSuccessful();
    }

    public function test_workflow_edit_page_renders(): void
    {
        $workflow = $this->createTestWorkflow();

        Livewire::test(EditWorkflow::class, ['record' => $workflow->getRouteKey()])
            ->assertSuccessful();
    }

    public function test_create_workflow(): void
    {
        // Add test fixtures path to model discovery so Order is available
        config()->set('filament-flow.model_discovery_paths', [
            app_path('Models'),
            dirname(__DIR__, 2).'/Fixtures/Models',
        ]);

        Livewire::test(CreateWorkflow::class)
            ->fillForm([
                'name' => 'New Test Workflow',
                'model_type' => Order::class,
                'state_column' => 'state',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('workflows', [
            'name' => 'New Test Workflow',
            'model_type' => Order::class,
        ]);
    }

    public function test_toggle_active_action(): void
    {
        $workflow = $this->createTestWorkflow(['is_active' => true]);

        // Test that the workflow starts active
        $this->assertTrue($workflow->is_active);

        // The toggle action is named 'toggle_active' on the edit page header
        Livewire::test(EditWorkflow::class, ['record' => $workflow->getRouteKey()])
            ->callAction('toggle_active');

        $workflow->refresh();
        $this->assertFalse($workflow->is_active);
    }

    public function test_toggle_active_action_activates(): void
    {
        $workflow = $this->createTestWorkflow(['is_active' => false]);

        $this->assertFalse($workflow->is_active);

        Livewire::test(EditWorkflow::class, ['record' => $workflow->getRouteKey()])
            ->callAction('toggle_active');

        $workflow->refresh();
        $this->assertTrue($workflow->is_active);
    }

    public function test_list_page_shows_workflows(): void
    {
        $workflow1 = $this->createTestWorkflow(['name' => 'Workflow One']);
        $workflow2 = $this->createTestWorkflow(['name' => 'Workflow Two']);

        Livewire::test(ListWorkflows::class)
            ->assertCanSeeTableRecords([$workflow1, $workflow2]);
    }

    public function test_list_page_has_columns(): void
    {
        Livewire::test(ListWorkflows::class)
            ->assertTableColumnExists('name')
            ->assertTableColumnExists('model_type')
            ->assertTableColumnExists('state_column')
            ->assertTableColumnExists('is_active');
    }

    public function test_states_relation_manager_renders(): void
    {
        $workflowData = $this->createFullWorkflow();

        Livewire::test(
            StatesRelationManager::class,
            ['ownerRecord' => $workflowData['workflow'], 'pageClass' => EditWorkflow::class]
        )->assertSuccessful();
    }

    public function test_transitions_relation_manager_renders(): void
    {
        $workflowData = $this->createFullWorkflow();

        Livewire::test(
            TransitionsRelationManager::class,
            ['ownerRecord' => $workflowData['workflow'], 'pageClass' => EditWorkflow::class]
        )->assertSuccessful();
    }

    public function test_notifications_relation_manager_renders(): void
    {
        $workflowData = $this->createFullWorkflow();

        Livewire::test(
            NotificationsRelationManager::class,
            ['ownerRecord' => $workflowData['workflow'], 'pageClass' => EditWorkflow::class]
        )->assertSuccessful();
    }

    public function test_edit_page_has_settings_action(): void
    {
        $workflow = $this->createTestWorkflow();

        Livewire::test(EditWorkflow::class, ['record' => $workflow->getRouteKey()])
            ->assertActionExists('settings');
    }

    public function test_edit_page_has_delete_action(): void
    {
        $workflow = $this->createTestWorkflow();

        Livewire::test(EditWorkflow::class, ['record' => $workflow->getRouteKey()])
            ->assertActionExists('delete');
    }
}
