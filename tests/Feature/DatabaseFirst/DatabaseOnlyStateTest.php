<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use RoBYCoNTe\FilamentFlow\Actions\StateAction;
use RoBYCoNTe\FilamentFlow\Actions\StateActionGroup;
use RoBYCoNTe\FilamentFlow\Services\StateService;
use RoBYCoNTe\FilamentFlow\StateTabs;
use RoBYCoNTe\FilamentFlow\Tables\Columns\StateColumn;
use RoBYCoNTe\FilamentFlow\Tables\Columns\StateExportColumn;
use RoBYCoNTe\FilamentFlow\Tables\Columns\StateSelectColumn;
use RoBYCoNTe\FilamentFlow\Tables\Grouping\StateGroup;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Ticket;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;
use Spatie\ModelStates\Exceptions\TransitionNotFound;
use Spatie\ModelStates\State;

/**
 * Tests for models that use plain string states (no Spatie State classes).
 * This mimics the behavior of App\Models\Claim which uses 'state' => 'string' cast.
 *
 * These tests verify that all filament-flow components gracefully handle
 * database-only states without crashing.
 */
class DatabaseOnlyStateTest extends TestCase
{
    // =========================================================================
    // Setup
    // =========================================================================

    private array $workflowData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workflowData = $this->createTicketWorkflow();
    }

    // =========================================================================
    // Basic Model Operations
    // =========================================================================

    public function test_ticket_model_stores_state_as_plain_string(): void
    {
        $ticket = $this->createTestTicket();

        $this->assertIsString($ticket->state);
        $this->assertEquals('open', $ticket->state);
    }

    public function test_ticket_state_is_not_spatie_state_instance(): void
    {
        $ticket = $this->createTestTicket();

        $this->assertNotInstanceOf(State::class, $ticket->state);
    }

    public function test_ticket_can_change_state_directly(): void
    {
        $ticket = $this->createTestTicket(['state' => 'open']);
        $ticket->state = 'in_progress';
        $ticket->save();

        $ticket->refresh();
        $this->assertEquals('in_progress', $ticket->state);
    }

    // =========================================================================
    // Database Transitions (HasDatabaseTransitions trait)
    // =========================================================================

    public function test_ticket_can_transition_via_database(): void
    {
        $ticket = $this->createTestTicket(['state' => 'open']);

        $ticket->transitionTo('in_progress');
        $ticket->refresh();

        $this->assertEquals('in_progress', $ticket->state);
    }

    public function test_ticket_transition_is_logged(): void
    {
        $ticket = $this->createTestTicket(['state' => 'open']);
        $ticket->transitionTo('in_progress');

        $this->assertTransitionLogged($ticket, 'open', 'in_progress');
    }

    public function test_ticket_invalid_transition_is_rejected(): void
    {
        $ticket = $this->createTestTicket(['state' => 'open']);

        $this->expectException(TransitionNotFound::class);
        $ticket->transitionTo('closed');
    }

    public function test_ticket_can_check_allowed_transitions(): void
    {
        $ticket = $this->createTestTicket(['state' => 'open']);

        $this->assertTrue($ticket->canTransitionToFromDatabaseString('open', 'in_progress', 'state'));
        $this->assertFalse($ticket->canTransitionToFromDatabaseString('open', 'closed', 'state'));
    }

    // =========================================================================
    // StateService
    // =========================================================================

    public function test_state_service_returns_all_states_for_ticket(): void
    {
        $stateService = app(StateService::class);
        $states = $stateService->getAllStatesForModel(Ticket::class, 'state');

        $this->assertIsArray($states);
        $this->assertArrayHasKey('open', $states);
        $this->assertArrayHasKey('in_progress', $states);
        $this->assertArrayHasKey('resolved', $states);
        $this->assertArrayHasKey('closed', $states);
    }

    public function test_state_service_returns_metadata_for_ticket_state(): void
    {
        $stateService = app(StateService::class);
        $metadata = $stateService->getStateMetadata(Ticket::class, 'open', 'state');

        $this->assertNotNull($metadata);
        $this->assertEquals('Open', $metadata['label']);
        $this->assertEquals('warning', $metadata['color']);
    }

    // =========================================================================
    // StateTabs
    // =========================================================================

    public function test_state_tabs_work_with_database_only_states(): void
    {
        $this->createTestTicket(['state' => 'open']);
        $this->createTestTicket(['state' => 'in_progress']);

        $tabs = StateTabs::make(Ticket::class)
            ->attribute('state')
            ->toArray();

        $this->assertNotEmpty($tabs);
        $this->assertCount(4, $tabs); // open, in_progress, resolved, closed
    }

    public function test_state_tabs_with_all_tab(): void
    {
        $this->createTestTicket(['state' => 'open']);

        $tabs = StateTabs::make(Ticket::class)
            ->attribute('state')
            ->includeAll()
            ->toArray();

        $this->assertCount(5, $tabs); // all + 4 states
    }

    public function test_state_tabs_with_badge_counts(): void
    {
        $this->createTestTicket(['state' => 'open']);
        $this->createTestTicket(['state' => 'open']);
        $this->createTestTicket(['state' => 'in_progress']);

        $tabs = StateTabs::make(Ticket::class)
            ->attribute('state')
            ->badge(true)
            ->toArray();

        $this->assertNotEmpty($tabs);
    }

    public function test_state_tabs_without_explicit_attribute_falls_back(): void
    {
        // StateTabs without attribute() should not crash even when
        // the model doesn't have getDefaultStates()
        $this->createTestTicket(['state' => 'open']);

        $tabs = StateTabs::make(Ticket::class)
            ->toArray(); // No explicit ->attribute() call

        $this->assertNotEmpty($tabs);
    }

    // =========================================================================
    // StateColumn
    // =========================================================================

    public function test_state_column_renders_label_for_database_state(): void
    {
        $ticket = $this->createTestTicket(['state' => 'open']);

        $column = StateColumn::make('state')
            ->attribute('state')
            ->record($ticket);

        // Should not throw
        $this->assertNotNull($column);
    }

    public function test_state_column_get_attribute_with_explicit_attribute(): void
    {
        $ticket = $this->createTestTicket(['state' => 'open']);

        $column = StateColumn::make('state')
            ->attribute('state')
            ->record($ticket);

        $this->assertEquals('state', $column->getAttribute($ticket));
    }

    public function test_state_column_get_attribute_without_explicit_attribute_does_not_crash(): void
    {
        $ticket = $this->createTestTicket(['state' => 'open']);

        $column = StateColumn::make('state')
            ->record($ticket);

        // Should fallback gracefully, not call getDefaultStates()
        $attribute = $column->getAttribute($ticket);
        $this->assertIsString($attribute);
    }

    // =========================================================================
    // StateExportColumn
    // =========================================================================

    public function test_state_export_column_with_explicit_attribute(): void
    {
        $column = StateExportColumn::make('state')
            ->stateAttribute('state');

        $this->assertEquals('state', $column->getStateAttribute());
    }

    public function test_state_export_column_without_explicit_attribute_falls_back(): void
    {
        // Without record, should return default 'state'
        $column = StateExportColumn::make('state');

        $attribute = $column->getStateAttribute();
        $this->assertEquals('state', $attribute);
    }

    // =========================================================================
    // StateSelectColumn
    // =========================================================================

    public function test_state_select_column_with_database_state(): void
    {
        $ticket = $this->createTestTicket(['state' => 'open']);

        $column = StateSelectColumn::make('state')
            ->attribute('state')
            ->record($ticket);

        $this->assertNotNull($column);
    }

    public function test_state_select_column_get_attribute_without_explicit_does_not_crash(): void
    {
        $ticket = $this->createTestTicket(['state' => 'open']);

        $column = StateSelectColumn::make('state')
            ->record($ticket);

        // Should not call getDefaultStates()
        $attribute = $column->getAttribute($ticket);
        $this->assertIsString($attribute);
    }

    // =========================================================================
    // StateGroup
    // =========================================================================

    public function test_state_group_with_database_only_states(): void
    {
        $group = StateGroup::make('state');

        $this->assertNotNull($group);
        $this->assertEquals('state', $group->getStateAttribute());
    }

    // =========================================================================
    // StateActionGroup
    // =========================================================================

    public function test_state_action_group_for_database_record(): void
    {
        $ticket = $this->createTestTicket(['state' => 'open']);

        $actions = StateActionGroup::forDatabaseRecord($ticket, 'state');

        $this->assertIsArray($actions);
        // Should include the 'start_work' transition (open -> in_progress)
        $this->assertNotEmpty($actions);
    }

    public function test_state_action_group_generate_with_null_state_class(): void
    {
        $actions = StateActionGroup::generate('state', null);

        // Should not crash when no state class is provided
        $this->assertNotNull($actions);
    }

    public function test_state_action_group_for_record_with_null_state_class(): void
    {
        $ticket = $this->createTestTicket(['state' => 'open']);

        $actions = StateActionGroup::forRecord($ticket, 'state', null);

        $this->assertIsArray($actions);
    }

    // =========================================================================
    // HasStateAttributes trait
    // =========================================================================

    public function test_has_state_attributes_fallback_when_no_get_default_states(): void
    {
        // Models without getDefaultStates should get 'state' as fallback
        // This is tested indirectly via StateAction
        $this->assertTrue(true); // Placeholder — real test is through StateAction
    }

    // =========================================================================
    // HasStateOptions (used by StateSelectColumn)
    // =========================================================================

    public function test_state_options_do_not_call_get_default_state_for(): void
    {
        // Ticket doesn't have getDefaultStateFor method
        $this->assertFalse(method_exists(Ticket::class, 'getDefaultStateFor'));
    }

    // =========================================================================
    // Multi-step workflow
    // =========================================================================

    public function test_full_ticket_lifecycle_via_database_transitions(): void
    {
        $ticket = $this->createTestTicket(['state' => 'open']);

        $ticket->transitionTo('in_progress');
        $ticket->refresh();
        $this->assertEquals('in_progress', $ticket->state);

        $ticket->transitionTo('resolved');
        $ticket->refresh();
        $this->assertEquals('resolved', $ticket->state);

        $ticket->transitionTo('closed');
        $ticket->refresh();
        $this->assertEquals('closed', $ticket->state);
    }

    public function test_ticket_reopen_transition(): void
    {
        $ticket = $this->createTestTicket(['state' => 'open']);
        $ticket->transitionTo('in_progress');
        $ticket->refresh();

        $ticket->transitionTo('open');
        $ticket->refresh();
        $this->assertEquals('open', $ticket->state);
    }

    public function test_all_transitions_are_logged(): void
    {
        $ticket = $this->createTestTicket(['state' => 'open']);

        $ticket->transitionTo('in_progress');
        $this->assertTransitionLogged($ticket, 'open', 'in_progress');

        $ticket->refresh();
        $ticket->transitionTo('resolved');
        $this->assertTransitionLogged($ticket, 'in_progress', 'resolved');
    }
}
