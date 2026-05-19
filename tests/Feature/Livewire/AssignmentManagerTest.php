<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Livewire;

use Livewire\Livewire;
use RoBYCoNTe\FilamentFlow\Livewire\AssignmentManager;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class AssignmentManagerTest extends TestCase
{
    protected Order $order;

    protected User $admin;

    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createTestUser([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'role' => 'admin',
        ]);

        $this->regularUser = $this->createTestUser([
            'name' => 'Regular User',
            'email' => 'user@test.com',
        ]);

        $this->order = Order::create([
            'order_number' => 'ORD-AM-001',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
        ]);
    }

    public function test_already_assigned_user_is_excluded_from_available_users(): void
    {
        $this->actingAs($this->admin);

        $this->order->assignTo($this->regularUser, 'primary');

        $component = Livewire::test(AssignmentManager::class, ['record' => $this->order]);
        $availableUsers = $component->instance()->getAvailableUsers();

        $this->assertArrayNotHasKey($this->regularUser->id, $availableUsers);
    }

    public function test_unassigned_user_appears_in_available_users(): void
    {
        $this->actingAs($this->admin);

        $component = Livewire::test(AssignmentManager::class, ['record' => $this->order]);
        $availableUsers = $component->instance()->getAvailableUsers();

        $this->assertArrayHasKey($this->regularUser->id, $availableUsers);
    }

    public function test_cannot_add_already_assigned_user(): void
    {
        $this->actingAs($this->admin);

        $this->order->assignTo($this->regularUser, 'primary');

        Livewire::test(AssignmentManager::class, ['record' => $this->order])
            ->set('addFormData.selectedUserId', $this->regularUser->id)
            ->set('addFormData.overrideView', true)
            ->call('addAssignment')
            ->assertHasErrors();

        $this->assertEquals(1, $this->order->fresh()->assignments()->count());
    }

    public function test_view_is_required_to_save_assignment(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(AssignmentManager::class, ['record' => $this->order])
            ->set('addFormData.selectedUserId', $this->regularUser->id)
            ->set('addFormData.overrideView', false)
            ->call('addAssignment')
            ->assertHasErrors();

        $this->assertEquals(0, $this->order->fresh()->assignments()->count());
    }

    public function test_view_defaults_to_true_when_form_opens(): void
    {
        $this->actingAs($this->admin);

        $component = Livewire::test(AssignmentManager::class, ['record' => $this->order])
            ->call('toggleAddForm');

        $this->assertTrue($component->get('addFormData.overrideView'));
    }

    public function test_view_defaults_to_true_on_component_init(): void
    {
        $this->actingAs($this->admin);

        $component = Livewire::test(AssignmentManager::class, ['record' => $this->order]);

        $this->assertTrue($component->get('addFormData.overrideView'));
    }

    public function test_can_add_assignment_with_only_view(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(AssignmentManager::class, ['record' => $this->order])
            ->set('addFormData.selectedUserId', $this->regularUser->id)
            ->set('addFormData.overrideView', true)
            ->set('addFormData.overrideEdit', false)
            ->set('addFormData.overrideTransition', false)
            ->call('addAssignment');

        $this->assertEquals(1, $this->order->fresh()->assignments()->count());

        $assignment = $this->order->assignments()->first();
        $this->assertTrue($assignment->override_view);
        $this->assertNull($assignment->override_edit);
        $this->assertNull($assignment->override_transition);
    }

    public function test_edit_and_transition_are_optional(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(AssignmentManager::class, ['record' => $this->order])
            ->set('addFormData.selectedUserId', $this->regularUser->id)
            ->set('addFormData.overrideView', true)
            ->set('addFormData.overrideEdit', true)
            ->set('addFormData.overrideTransition', true)
            ->call('addAssignment');

        $assignment = $this->order->fresh()->assignments()->first();
        $this->assertNotNull($assignment);
        $this->assertTrue($assignment->override_view);
        $this->assertTrue($assignment->override_edit);
        $this->assertTrue($assignment->override_transition);
    }

    public function test_non_admin_cannot_add_assignment(): void
    {
        $this->actingAs($this->regularUser);

        Livewire::test(AssignmentManager::class, ['record' => $this->order])
            ->set('addFormData.selectedUserId', $this->admin->id)
            ->set('addFormData.overrideView', true)
            ->call('addAssignment');

        $this->assertEquals(0, $this->order->fresh()->assignments()->count());
    }

    public function test_assigned_user_appears_in_assignments_list(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(AssignmentManager::class, ['record' => $this->order])
            ->set('addFormData.selectedUserId', $this->regularUser->id)
            ->set('addFormData.overrideView', true)
            ->call('addAssignment');

        $component = Livewire::test(AssignmentManager::class, ['record' => $this->order]);
        $assignments = $component->instance()->getAssignments();

        $this->assertCount(1, $assignments);
        $this->assertEquals($this->regularUser->id, $assignments[0]['user_id']);
        $this->assertTrue($assignments[0]['override_view']);
    }

    public function test_second_user_can_be_added_after_first(): void
    {
        $secondUser = $this->createTestUser([
            'name' => 'Second User',
            'email' => 'second@test.com',
        ]);

        $this->actingAs($this->admin);

        Livewire::test(AssignmentManager::class, ['record' => $this->order])
            ->set('addFormData.selectedUserId', $this->regularUser->id)
            ->set('addFormData.overrideView', true)
            ->call('addAssignment');

        Livewire::test(AssignmentManager::class, ['record' => $this->order])
            ->set('addFormData.selectedUserId', $secondUser->id)
            ->set('addFormData.overrideView', true)
            ->call('addAssignment');

        $this->assertEquals(2, $this->order->fresh()->assignments()->count());
    }

    public function test_second_user_excluded_from_dropdown_after_first_assigned(): void
    {
        $secondUser = $this->createTestUser([
            'name' => 'Second User',
            'email' => 'second@test.com',
        ]);

        $this->actingAs($this->admin);

        $this->order->assignTo($this->regularUser, 'primary');

        $component = Livewire::test(AssignmentManager::class, ['record' => $this->order]);
        $availableUsers = $component->instance()->getAvailableUsers();

        $this->assertArrayNotHasKey($this->regularUser->id, $availableUsers);
        $this->assertArrayHasKey($secondUser->id, $availableUsers);
    }

    public function test_add_assignment_uses_selected_type(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(AssignmentManager::class, ['record' => $this->order])
            ->set('addFormData.selectedUserId', $this->regularUser->id)
            ->set('addFormData.assignmentType', 'secondary')
            ->set('addFormData.overrideView', true)
            ->call('addAssignment');

        $assignment = $this->order->fresh()->assignments()->first();
        $this->assertNotNull($assignment);
        $this->assertEquals('secondary', $assignment->assignment_type);
    }

    public function test_add_assignment_defaults_to_primary_type(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(AssignmentManager::class, ['record' => $this->order])
            ->set('addFormData.selectedUserId', $this->regularUser->id)
            ->set('addFormData.overrideView', true)
            ->call('addAssignment');

        $assignment = $this->order->fresh()->assignments()->first();
        $this->assertNotNull($assignment);
        $this->assertEquals('primary', $assignment->assignment_type);
    }

    public function test_change_assignment_type_updates_type(): void
    {
        $this->actingAs($this->admin);

        $assignment = $this->order->assignTo($this->regularUser, 'viewer');

        Livewire::test(AssignmentManager::class, ['record' => $this->order])
            ->call('changeAssignmentType', $assignment->id, 'secondary');

        $assignment->refresh();
        $this->assertEquals('secondary', $assignment->assignment_type);
    }

    public function test_non_admin_cannot_change_assignment_type(): void
    {
        $this->actingAs($this->regularUser);

        $assignment = $this->order->assignTo($this->admin, 'viewer');

        Livewire::test(AssignmentManager::class, ['record' => $this->order])
            ->call('changeAssignmentType', $assignment->id, 'primary');

        $assignment->refresh();
        $this->assertEquals('viewer', $assignment->assignment_type);
    }

    public function test_change_assignment_type_sends_warning_on_conflict(): void
    {
        $this->actingAs($this->admin);

        $viewerAssignment = $this->order->assignTo($this->regularUser, 'viewer');
        $this->order->assignTo($this->regularUser, 'primary');

        Livewire::test(AssignmentManager::class, ['record' => $this->order])
            ->call('changeAssignmentType', $viewerAssignment->id, 'primary')
            ->assertNotified();

        $viewerAssignment->refresh();
        $this->assertEquals('viewer', $viewerAssignment->assignment_type);
    }

    public function test_assignments_list_includes_metadata(): void
    {
        $this->actingAs($this->admin);

        $this->order->assignWithOverrides(
            $this->regularUser,
            ['view' => true],
            'viewer',
            null,
            ['source' => 'diary_entry'],
        );

        $component = Livewire::test(AssignmentManager::class, ['record' => $this->order]);
        $assignments = $component->instance()->getAssignments();

        $this->assertCount(1, $assignments);
        $this->assertArrayHasKey('metadata', $assignments[0]);
        $this->assertEquals('diary_entry', $assignments[0]['metadata']['source']);
    }

    public function test_assignments_list_metadata_is_null_when_not_set(): void
    {
        $this->actingAs($this->admin);

        $this->order->assignTo($this->regularUser, 'primary');

        $component = Livewire::test(AssignmentManager::class, ['record' => $this->order]);
        $assignments = $component->instance()->getAssignments();

        $this->assertCount(1, $assignments);
        $this->assertArrayHasKey('metadata', $assignments[0]);
        $this->assertNull($assignments[0]['metadata']);
    }
}
