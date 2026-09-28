<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Livewire;

use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use RoBYCoNTe\FilamentFlow\Events\WorkflowOwnerChanged;
use RoBYCoNTe\FilamentFlow\Livewire\AssignmentManager;
use RoBYCoNTe\FilamentFlow\Models\WorkflowOwnerChange;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * The panel that hands out the work: somebody already assigned is not offered, somebody
 * unassigned is, adding the same person twice is refused — and every permission has three
 * readings: the call decides, allowed, shut out.
 *
 * The ownership of the record is in the same room: handing it over, and what the previous
 * holder keeps when the hands change.
 */
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
            ->set('addFormData.overrideView', 'grant')
            ->call('addAssignment')
            ->assertHasErrors();

        $this->assertEquals(1, $this->order->fresh()->assignments()->count());
    }

    public function test_all_follow_is_a_legitimate_assignment(): void
    {
        $this->actingAs($this->admin);

        // Every permission on "the call decides": the row carries no word of its own, and
        // the type still matters to the rules that ask for an assignee.
        Livewire::test(AssignmentManager::class, ['record' => $this->order])
            ->set('addFormData.selectedUserId', $this->regularUser->id)
            ->set('addFormData.overrideView', 'follow')
            ->set('addFormData.overrideEdit', 'follow')
            ->set('addFormData.overrideTransition', 'follow')
            ->call('addAssignment');

        $assignment = $this->order->fresh()->assignments()->first();
        $this->assertNotNull($assignment);
        $this->assertNull($assignment->override_view);
        $this->assertNull($assignment->override_edit);
        $this->assertNull($assignment->override_transition);
    }

    public function test_view_defaults_to_grant_when_form_opens(): void
    {
        $this->actingAs($this->admin);

        $component = Livewire::test(AssignmentManager::class, ['record' => $this->order])
            ->call('toggleAddForm');

        $this->assertSame('grant', $component->get('addFormData.overrideView'));
    }

    public function test_view_defaults_to_grant_on_component_init(): void
    {
        $this->actingAs($this->admin);

        $component = Livewire::test(AssignmentManager::class, ['record' => $this->order]);

        $this->assertSame('grant', $component->get('addFormData.overrideView'));
    }

    public function test_can_add_assignment_with_only_view(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(AssignmentManager::class, ['record' => $this->order])
            ->set('addFormData.selectedUserId', $this->regularUser->id)
            ->set('addFormData.overrideView', 'grant')
            ->set('addFormData.overrideEdit', 'follow')
            ->set('addFormData.overrideTransition', 'follow')
            ->call('addAssignment');

        $this->assertEquals(1, $this->order->fresh()->assignments()->count());

        $assignment = $this->order->assignments()->first();
        $this->assertTrue($assignment->override_view);
        $this->assertNull($assignment->override_edit);
        $this->assertNull($assignment->override_transition);
    }

    public function test_can_add_assignment_with_a_denial(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(AssignmentManager::class, ['record' => $this->order])
            ->set('addFormData.selectedUserId', $this->regularUser->id)
            ->set('addFormData.assignmentType', 'secondary')
            ->set('addFormData.overrideView', 'deny')
            ->set('addFormData.overrideEdit', 'deny')
            ->set('addFormData.overrideTransition', 'deny')
            ->call('addAssignment');

        $assignment = $this->order->fresh()->assignments()->first();
        $this->assertNotNull($assignment);
        $this->assertFalse($assignment->override_view);
        $this->assertFalse($assignment->override_edit);

        $assignments = Livewire::test(AssignmentManager::class, ['record' => $this->order])
            ->instance()
            ->getAssignments();

        $this->assertTrue($assignments[0]['has_denial']);
        $this->assertFalse($assignments[0]['has_overrides']);
    }

    public function test_edit_and_transition_are_optional(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(AssignmentManager::class, ['record' => $this->order])
            ->set('addFormData.selectedUserId', $this->regularUser->id)
            ->set('addFormData.overrideView', 'grant')
            ->set('addFormData.overrideEdit', 'grant')
            ->set('addFormData.overrideTransition', 'grant')
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
            ->set('addFormData.overrideView', 'grant')
            ->call('addAssignment');

        $this->assertEquals(0, $this->order->fresh()->assignments()->count());
    }

    public function test_super_admin_only_keeps_plain_admins_out(): void
    {
        $this->actingAs($this->admin);

        // An admin reads the panel, and nothing more: the buttons never appear and the
        // commands refuse to run.
        $component = Livewire::test(AssignmentManager::class, [
            'record' => $this->order,
            'superAdminOnly' => true,
        ]);

        $this->assertFalse($component->instance()->canManageAssignments());

        Livewire::test(AssignmentManager::class, [
            'record' => $this->order,
            'superAdminOnly' => true,
        ])
            ->set('addFormData.selectedUserId', $this->regularUser->id)
            ->call('addAssignment');

        $this->assertEquals(0, $this->order->fresh()->assignments()->count());
    }

    public function test_super_admin_manages_when_super_admin_only(): void
    {
        $super = $this->createTestUser([
            'name' => 'Super User',
            'email' => 'super@test.com',
            'role' => 'super_admin',
        ]);

        $this->actingAs($super);

        $component = Livewire::test(AssignmentManager::class, [
            'record' => $this->order,
            'superAdminOnly' => true,
        ]);

        $this->assertTrue($component->instance()->canManageAssignments());
    }

    public function test_assigned_user_appears_in_assignments_list(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(AssignmentManager::class, ['record' => $this->order])
            ->set('addFormData.selectedUserId', $this->regularUser->id)
            ->set('addFormData.overrideView', 'grant')
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
            ->set('addFormData.overrideView', 'grant')
            ->call('addAssignment');

        Livewire::test(AssignmentManager::class, ['record' => $this->order])
            ->set('addFormData.selectedUserId', $secondUser->id)
            ->set('addFormData.overrideView', 'grant')
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
            ->set('addFormData.overrideView', 'grant')
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
            ->set('addFormData.overrideView', 'grant')
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

    public function test_override_cycles_through_its_three_readings(): void
    {
        $this->actingAs($this->admin);

        $assignment = $this->order->assignTo($this->regularUser, 'primary');

        $panel = Livewire::test(AssignmentManager::class, ['record' => $this->order]);

        // The call decides, allowed, shut out, and back to the call.
        $panel->call('toggleOverride', $assignment->id, 'view');
        $this->assertTrue($assignment->fresh()->override_view);

        $panel->call('toggleOverride', $assignment->id, 'view');
        $this->assertFalse($assignment->fresh()->override_view);

        $panel->call('toggleOverride', $assignment->id, 'view');
        $this->assertNull($assignment->fresh()->override_view);
    }

    public function test_non_admin_cannot_toggle_override(): void
    {
        $this->actingAs($this->regularUser);

        $assignment = $this->order->assignTo($this->regularUser, 'primary');

        Livewire::test(AssignmentManager::class, ['record' => $this->order])
            ->call('toggleOverride', $assignment->id, 'view');

        $this->assertNull($assignment->fresh()->override_view);
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

    /** The record shows who holds it, and the list of takers leaves the holder out. */
    public function test_current_owner_is_shown_and_not_offered_as_successor(): void
    {
        $this->actingAs($this->admin);

        $this->order->update(['user_id' => $this->admin->id]);

        $component = Livewire::test(AssignmentManager::class, ['record' => $this->order]);
        $currentOwner = $component->instance()->getCurrentOwner();
        $candidates = $component->instance()->getTransferCandidates();

        $this->assertNotNull($currentOwner);
        $this->assertEquals($this->admin->id, $currentOwner['id']);
        $this->assertArrayNotHasKey($this->admin->id, $candidates);
        $this->assertArrayHasKey($this->regularUser->id, $candidates);
    }

    public function test_transfer_moves_the_owner_and_keeps_nobody(): void
    {
        Event::fake([WorkflowOwnerChanged::class]);

        $this->actingAs($this->admin);

        $this->order->update(['user_id' => $this->admin->id]);

        Livewire::test(AssignmentManager::class, ['record' => $this->order])
            ->set('transferFormData.newOwnerId', $this->regularUser->id)
            ->set('transferFormData.retention', 'none')
            ->call('transferOwnership')
            ->assertNotified();

        $this->assertEquals($this->regularUser->id, $this->order->fresh()->user_id);
        $this->assertEquals(0, $this->order->assignments()->count());

        // The handover is written down where the owner column reads it.
        $change = WorkflowOwnerChange::query()->forRecord($this->order)->first();
        $this->assertNotNull($change);
        $this->assertEquals($this->admin->id, $change->from_user_id);
        $this->assertEquals($this->regularUser->id, $change->to_user_id);
        $this->assertEquals($this->admin->id, $change->changed_by);
        $this->assertSame('none', $change->retention);
        $this->assertSame('user_id', $change->owner_field);

        Event::assertDispatched(WorkflowOwnerChanged::class, function (WorkflowOwnerChanged $event): bool {
            return $event->fromUserId === $this->admin->id
                && $event->toUserId === $this->regularUser->id
                && $event->retention === 'none';
        });
    }

    public function test_transfer_keeps_the_previous_owner_as_observer(): void
    {
        $this->actingAs($this->admin);

        $this->order->update(['user_id' => $this->admin->id]);

        Livewire::test(AssignmentManager::class, ['record' => $this->order])
            ->set('transferFormData.newOwnerId', $this->regularUser->id)
            ->set('transferFormData.retention', 'viewer')
            ->set('transferFormData.note', 'Away from the desk')
            ->call('transferOwnership')
            ->assertNotified();

        $this->assertEquals($this->regularUser->id, $this->order->fresh()->user_id);

        $assignment = $this->order->assignments()->where('user_id', $this->admin->id)->first();
        $this->assertNotNull($assignment, 'The previous owner keeps a row of their own.');
        $this->assertEquals('viewer', $assignment->assignment_type);
        $this->assertTrue($assignment->override_view);
        $this->assertTrue((bool) $assignment->getMetadata('owner_transfer'));
        $this->assertEquals('Away from the desk', $assignment->getMetadata('transfer_note'));
    }

    public function test_transfer_keeps_the_previous_owner_as_collaborator(): void
    {
        $this->actingAs($this->admin);

        $this->order->update(['user_id' => $this->admin->id]);

        Livewire::test(AssignmentManager::class, ['record' => $this->order])
            ->set('transferFormData.newOwnerId', $this->regularUser->id)
            ->set('transferFormData.retention', 'secondary')
            ->call('transferOwnership')
            ->assertNotified();

        $assignment = $this->order->assignments()->where('user_id', $this->admin->id)->first();
        $this->assertNotNull($assignment);
        $this->assertEquals('secondary', $assignment->assignment_type);
        $this->assertNull($assignment->override_view);
    }

    public function test_transfer_without_a_successor_is_refused(): void
    {
        $this->actingAs($this->admin);

        $this->order->update(['user_id' => $this->admin->id]);

        Livewire::test(AssignmentManager::class, ['record' => $this->order])
            ->set('transferFormData.retention', 'none')
            ->call('transferOwnership')
            ->assertHasErrors();

        $this->assertEquals($this->admin->id, $this->order->fresh()->user_id);
    }

    public function test_transfer_to_the_current_owner_is_refused(): void
    {
        $this->actingAs($this->admin);

        $this->order->update(['user_id' => $this->admin->id]);

        Livewire::test(AssignmentManager::class, ['record' => $this->order])
            ->set('transferFormData.newOwnerId', $this->admin->id)
            ->call('transferOwnership')
            ->assertHasErrors();

        $this->assertEquals($this->admin->id, $this->order->fresh()->user_id);
    }

    public function test_non_admin_cannot_transfer_ownership(): void
    {
        $this->actingAs($this->regularUser);

        $this->order->update(['user_id' => $this->admin->id]);

        Livewire::test(AssignmentManager::class, ['record' => $this->order])
            ->set('transferFormData.newOwnerId', $this->regularUser->id)
            ->call('transferOwnership');

        $this->assertEquals($this->admin->id, $this->order->fresh()->user_id);
    }

    /** The panel says what it is for: whoever uses it reads it, and needs no manual beside. */
    public function test_the_panel_explains_itself(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(AssignmentManager::class, ['record' => $this->order])
            ->assertSee(__('filament-flow::messages.explanation_heading'))
            ->assertSee(__('filament-flow::messages.explanation_intro'))
            ->assertDontSee(__('filament-flow::messages.explanation_read_only'));
    }

    /** Read once: folded for whoever acts, because the working area is what they came for. */
    public function test_the_explanation_is_folded_for_whoever_acts(): void
    {
        $this->actingAs($this->admin);

        $component = Livewire::test(AssignmentManager::class, ['record' => $this->order]);

        $this->assertTrue($component->instance()->explanationStartsCollapsed());
    }

    /** And open for whoever may not: there it is the whole panel, and it says why. */
    public function test_the_explanation_opens_for_whoever_reads(): void
    {
        $this->actingAs($this->regularUser);

        $component = Livewire::test(AssignmentManager::class, ['record' => $this->order]);

        $this->assertFalse($component->instance()->explanationStartsCollapsed());
    }

    /** A host that wants the panel bare switches the explanation off. */
    public function test_the_explanation_can_be_turned_off(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(AssignmentManager::class, ['record' => $this->order, 'showExplanation' => false])
            ->assertDontSee(__('filament-flow::messages.explanation_heading'));
    }

    /** Whoever reads without managing is told that the settings are not theirs to change. */
    public function test_the_reader_is_told_they_cannot_change_it(): void
    {
        $this->actingAs($this->regularUser);

        Livewire::test(AssignmentManager::class, ['record' => $this->order])
            ->assertSee(__('filament-flow::messages.explanation_heading'))
            ->assertSee(__('filament-flow::messages.explanation_read_only'));
    }

    /** The handovers are kept in the panel — collapsed — where they can be read in full. */
    public function test_the_panel_keeps_the_handovers(): void
    {
        $this->actingAs($this->admin);

        $this->order->update(['user_id' => $this->admin->id]);

        Livewire::test(AssignmentManager::class, ['record' => $this->order])
            ->set('transferFormData.newOwnerId', $this->regularUser->id)
            ->set('transferFormData.retention', 'viewer')
            ->call('transferOwnership');

        Livewire::test(AssignmentManager::class, ['record' => $this->order])
            ->assertSee(__('filament-flow::messages.ownership_history_label'))
            // What the previous holder kept, and whose hand the record came from.
            ->assertSee(__('filament-flow::messages.retention_viewer'))
            ->assertSee(__('filament-flow::messages.owner_change_from', ['name' => 'Admin User']));
    }

    /** A record that never changed hands says so, in one line. */
    public function test_a_record_that_never_changed_hands_says_so(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(AssignmentManager::class, ['record' => $this->order])
            ->assertSee(__('filament-flow::messages.ownership_history_empty'));
    }

    /** A host that wants the panel bare switches the explanation off. */
    public function test_the_history_can_be_turned_off(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(AssignmentManager::class, ['record' => $this->order, 'showHistory' => false])
            ->assertDontSee(__('filament-flow::messages.ownership_history_label'));
    }
}
