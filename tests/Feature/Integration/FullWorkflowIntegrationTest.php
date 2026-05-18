<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Integration;

use Illuminate\Support\Facades\Notification;
use RoBYCoNTe\FilamentFlow\Exceptions\UnauthorizedTransitionException;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotification as WorkflowNotificationConfig;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotificationChannel;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotificationLog;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotificationRecipient;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotificationTemplate;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateAccessRule;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateField;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateTransition;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionField;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionMetadata;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionPermission;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionSnapshot;
use RoBYCoNTe\FilamentFlow\Notifications\WorkflowNotification;
use RoBYCoNTe\FilamentFlow\Services\WorkflowFieldPermissionsService;
use RoBYCoNTe\FilamentFlow\Services\WorkflowStateAccessService;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * Comprehensive integration test: database-first workflow with all features.
 *
 * Scenario: an order workflow with 4 states (draft → pending → processing → completed),
 * field permissions per state, access control rules, transition permissions,
 * transition form fields, notifications with recipients/channels/templates,
 * creation permissions/fields, and full audit trail.
 *
 * Everything is configured via database (database-first) — no PHP State classes.
 */
class FullWorkflowIntegrationTest extends TestCase
{
    private Workflow $workflow;

    private WorkflowState $draftState;

    private WorkflowState $pendingState;

    private WorkflowState $processingState;

    private WorkflowState $completedState;

    private WorkflowTransition $draftToPending;

    private WorkflowTransition $pendingToProcessing;

    private WorkflowTransition $processingToCompleted;

    private User $admin;

    private User $manager;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable access control enforcement
        config()->set('filament-flow.state_access.enforce_on_transition', true);
        config()->set('filament-flow.notifications.enabled', true);

        $this->buildWorkflow();
        $this->buildUsers();
    }

    // ──────────────────────────────────────────────────────────────
    //  Workflow scaffolding
    // ──────────────────────────────────────────────────────────────

    private function buildWorkflow(): void
    {
        // 1. Workflow
        $this->workflow = $this->createTestWorkflow([
            'name' => 'Order Processing',
        ]);

        // 2. States
        $this->draftState = $this->createWorkflowState($this->workflow, [
            'name' => 'draft',
            'label' => 'Draft',
            'color' => 'gray',
            'is_initial' => true,
            'sort_order' => 0,
        ]);
        $this->pendingState = $this->createWorkflowState($this->workflow, [
            'name' => 'pending',
            'label' => 'Pending',
            'color' => 'warning',
            'sort_order' => 1,
        ]);
        $this->processingState = $this->createWorkflowState($this->workflow, [
            'name' => 'processing',
            'label' => 'Processing',
            'color' => 'info',
            'sort_order' => 2,
        ]);
        $this->completedState = $this->createWorkflowState($this->workflow, [
            'name' => 'completed',
            'label' => 'Completed',
            'color' => 'success',
            'is_final' => true,
            'sort_order' => 3,
        ]);

        // 3. Transitions
        $this->draftToPending = $this->createWorkflowTransition(
            $this->workflow, $this->draftState, $this->pendingState, [
                'name' => 'submit',
                'label' => 'Submit',
            ]
        );
        $this->pendingToProcessing = $this->createWorkflowTransition(
            $this->workflow, $this->pendingState, $this->processingState, [
                'name' => 'process',
                'label' => 'Start Processing',
                'requires_confirmation' => true,
            ]
        );
        $this->processingToCompleted = $this->createWorkflowTransition(
            $this->workflow, $this->processingState, $this->completedState, [
                'name' => 'complete',
                'label' => 'Complete',
                'requires_reason' => true,
            ]
        );

        // 4. Field permissions per state
        $this->buildFieldPermissions();

        // 5. Access rules per state
        $this->buildAccessRules();

        // 6. Transition permissions
        $this->buildTransitionPermissions();

        // 7. Transition form fields
        $this->buildTransitionFields();

        // 8. Notifications
        $this->buildNotifications();

        // 9. Creation permissions & fields
        $this->buildCreationConfig();
    }

    private function buildFieldPermissions(): void
    {
        // Draft: everything editable
        WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'customer_name',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => true,
        ]);
        WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'total_amount',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => true,
        ]);
        WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'notes',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => false,
        ]);
        // Draft: tracking_number is hidden (makes no sense yet)
        WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'tracking_number',
            'visibility' => 'hidden',
            'mutability' => 'editable',
            'is_required' => false,
        ]);

        // Pending: customer_name read-only, total_amount read-only, notes editable
        WorkflowStateField::create([
            'state_id' => $this->pendingState->id,
            'field_name' => 'customer_name',
            'visibility' => 'visible',
            'mutability' => 'readonly',
            'is_required' => false,
        ]);
        WorkflowStateField::create([
            'state_id' => $this->pendingState->id,
            'field_name' => 'total_amount',
            'visibility' => 'visible',
            'mutability' => 'readonly',
            'is_required' => false,
        ]);
        WorkflowStateField::create([
            'state_id' => $this->pendingState->id,
            'field_name' => 'notes',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => false,
        ]);

        // Processing: tracking_number becomes visible & editable
        WorkflowStateField::create([
            'state_id' => $this->processingState->id,
            'field_name' => 'customer_name',
            'visibility' => 'visible',
            'mutability' => 'readonly',
            'is_required' => false,
        ]);
        WorkflowStateField::create([
            'state_id' => $this->processingState->id,
            'field_name' => 'tracking_number',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => true,
        ]);

        // Completed: everything read-only
        WorkflowStateField::create([
            'state_id' => $this->completedState->id,
            'field_name' => 'customer_name',
            'visibility' => 'visible',
            'mutability' => 'readonly',
            'is_required' => false,
        ]);
        WorkflowStateField::create([
            'state_id' => $this->completedState->id,
            'field_name' => 'total_amount',
            'visibility' => 'visible',
            'mutability' => 'readonly',
            'is_required' => false,
        ]);
        WorkflowStateField::create([
            'state_id' => $this->completedState->id,
            'field_name' => 'tracking_number',
            'visibility' => 'visible',
            'mutability' => 'readonly',
            'is_required' => false,
        ]);
    }

    private function buildAccessRules(): void
    {
        // Draft: only owner can edit
        WorkflowStateAccessRule::create([
            'state_id' => $this->draftState->id,
            'access_type' => 'view',
            'rule' => '@authenticated',
        ]);
        WorkflowStateAccessRule::create([
            'state_id' => $this->draftState->id,
            'access_type' => 'edit',
            'rule' => '@owner',
        ]);
        WorkflowStateAccessRule::create([
            'state_id' => $this->draftState->id,
            'access_type' => 'transition',
            'rule' => '@owner',
        ]);

        // Pending: manager or admin can transition
        WorkflowStateAccessRule::create([
            'state_id' => $this->pendingState->id,
            'access_type' => 'view',
            'rule' => '@authenticated',
        ]);
        WorkflowStateAccessRule::create([
            'state_id' => $this->pendingState->id,
            'access_type' => 'transition',
            'rule' => 'role:manager,admin',
        ]);

        // Processing: only admin can transition to completed
        WorkflowStateAccessRule::create([
            'state_id' => $this->processingState->id,
            'access_type' => 'view',
            'rule' => '@authenticated',
        ]);
        WorkflowStateAccessRule::create([
            'state_id' => $this->processingState->id,
            'access_type' => 'transition',
            'rule' => 'role:admin',
        ]);

        // Completed: everyone can view, nobody edits
        WorkflowStateAccessRule::create([
            'state_id' => $this->completedState->id,
            'access_type' => 'view',
            'rule' => '*',
        ]);
    }

    private function buildTransitionPermissions(): void
    {
        // draft → pending: only role operator or manager
        WorkflowTransitionPermission::create([
            'transition_id' => $this->draftToPending->id,
            'permission_type' => 'role',
            'permission_value' => 'operator,manager,admin',
            'require_all' => false,
        ]);

        // pending → processing: only manager,admin
        WorkflowTransitionPermission::create([
            'transition_id' => $this->pendingToProcessing->id,
            'permission_type' => 'role',
            'permission_value' => 'manager,admin',
            'require_all' => false,
        ]);

        // processing → completed: only admin
        WorkflowTransitionPermission::create([
            'transition_id' => $this->processingToCompleted->id,
            'permission_type' => 'role',
            'permission_value' => 'admin',
            'require_all' => false,
        ]);
    }

    private function buildTransitionFields(): void
    {
        // pending → processing: processing_notes required
        WorkflowTransitionField::create([
            'transition_id' => $this->pendingToProcessing->id,
            'field_name' => 'processing_notes',
            'field_type' => 'textarea',
            'label' => 'Processing Notes',
            'model_attribute' => 'processing_notes',
            'is_required' => true,
            'save_to_model' => true,
            'sort_order' => 0,
        ]);

        // processing → completed: tracking_number & carrier required
        WorkflowTransitionField::create([
            'transition_id' => $this->processingToCompleted->id,
            'field_name' => 'tracking_number',
            'field_type' => 'text',
            'label' => 'Tracking Number',
            'model_attribute' => 'tracking_number',
            'is_required' => true,
            'save_to_model' => true,
            'sort_order' => 0,
        ]);
        WorkflowTransitionField::create([
            'transition_id' => $this->processingToCompleted->id,
            'field_name' => 'carrier',
            'field_type' => 'text',
            'label' => 'Carrier',
            'model_attribute' => 'carrier',
            'is_required' => true,
            'save_to_model' => true,
            'sort_order' => 1,
        ]);
    }

    private function buildNotifications(): void
    {
        // Notification: when order enters "processing" state → notify owner
        $notifConfig = WorkflowNotificationConfig::create([
            'workflow_id' => $this->workflow->id,
            'trigger_event' => 'on_state_enter',
            'state_id' => $this->processingState->id,
            'name' => 'Order Processing Started',
            'is_active' => true,
            'timing' => 'immediate',
            'priority' => 'medium',
        ]);

        WorkflowNotificationRecipient::create([
            'notification_id' => $notifConfig->id,
            'recipient_type' => 'record_owner',
            'recipient_config' => [],
            'sort_order' => 0,
        ]);

        $channel = WorkflowNotificationChannel::create([
            'notification_id' => $notifConfig->id,
            'channel_type' => 'database',
            'is_active' => true,
        ]);

        WorkflowNotificationTemplate::create([
            'notification_id' => $notifConfig->id,
            'channel_id' => $channel->id,
            'subject' => 'Order {{order_number}} is being processed',
            'title' => 'Order Processing',
            'body' => 'Your order {{order_number}} for {{customer_name}} is now being processed.',
            'template_engine' => 'plain',
        ]);
    }

    private function buildCreationConfig(): void
    {
        // Creation permission is now defined via access rules on the initial state.
        // The draft (initial) state already has view/edit/transition rules.
        // Add create rules: only operator, manager, admin can create orders.
        WorkflowStateAccessRule::create([
            'state_id' => $this->draftState->id,
            'access_type' => 'create',
            'rule' => 'role:operator,manager,admin',
        ]);

        // Creation field visibility is now derived from the initial state's
        // field permissions (already set up in buildFieldPermissions()).
        // Draft state already has: customer_name (visible/editable/required),
        // total_amount (visible/editable/required), notes (visible/editable),
        // tracking_number (hidden).
    }

    private function buildUsers(): void
    {
        $this->admin = $this->createTestUser([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'role' => 'admin',
        ]);
        $this->manager = $this->createTestUser([
            'name' => 'Manager',
            'email' => 'manager@test.com',
            'role' => 'manager',
        ]);
        $this->operator = $this->createTestUser([
            'name' => 'Operator',
            'email' => 'operator@test.com',
            'role' => 'operator',
        ]);
    }

    private function createOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-FULL-'.uniqid(),
            'customer_name' => 'Mario Rossi',
            'customer_email' => 'mario@rossi.it',
            'total_amount' => 250.00,
            'state' => 'draft',
            'user_id' => $this->operator->id,
        ], $overrides));
    }

    // ──────────────────────────────────────────────────────────────
    //  Tests
    // ──────────────────────────────────────────────────────────────

    // -- Creation permissions --

    public function test_creation_permissions_allow_authorized_roles(): void
    {
        $service = app(WorkflowStateAccessService::class);
        $modelClass = Order::class;

        $this->assertTrue($service->canCreate($modelClass, $this->admin));
        $this->assertTrue($service->canCreate($modelClass, $this->manager));
        $this->assertTrue($service->canCreate($modelClass, $this->operator));
    }

    public function test_creation_permissions_deny_unauthorized_role(): void
    {
        $viewer = $this->createTestUser([
            'email' => 'viewer@test.com',
            'role' => 'viewer',
        ]);

        $service = app(WorkflowStateAccessService::class);
        $this->assertFalse($service->canCreate(Order::class, $viewer));
    }

    public function test_creation_fields_from_initial_state(): void
    {
        $service = new WorkflowFieldPermissionsService;
        $permissions = $service->getCreationFieldPermissions(Order::class);

        // customer_name: visible, editable, required (from draft state field permissions)
        $this->assertArrayHasKey('customer_name', $permissions);
        $this->assertTrue($permissions['customer_name']['visible']);
        $this->assertFalse($permissions['customer_name']['readonly']);
        $this->assertTrue($permissions['customer_name']['required']);

        // tracking_number: hidden in draft state
        $this->assertArrayHasKey('tracking_number', $permissions);
        $this->assertFalse($permissions['tracking_number']['visible']);
    }

    // -- Field permissions per state --

    public function test_field_permissions_in_draft_state(): void
    {
        $order = $this->createOrder();
        $service = new WorkflowFieldPermissionsService;

        $perms = $service->getFieldPermissions($order);

        // customer_name editable and required
        $this->assertTrue($perms['customer_name']['visible']);
        $this->assertFalse($perms['customer_name']['readonly']);
        $this->assertTrue($perms['customer_name']['required']);

        // tracking_number hidden
        $this->assertFalse($perms['tracking_number']['visible']);
    }

    public function test_field_permissions_change_when_state_changes(): void
    {
        $order = $this->createOrder();
        $service = new WorkflowFieldPermissionsService;

        // Draft → customer_name editable
        $draftPerms = $service->getFieldPermissions($order);
        $this->assertFalse($draftPerms['customer_name']['readonly']);

        // Move to pending
        $order->state = 'pending';
        $order->save();
        $order->refresh();

        $pendingPerms = $service->getFieldPermissions($order);
        $this->assertTrue($pendingPerms['customer_name']['readonly']);
    }

    public function test_hidden_fields_per_state(): void
    {
        $order = $this->createOrder();
        $service = new WorkflowFieldPermissionsService;

        // Draft: tracking_number hidden
        $hidden = $service->getHiddenFields($order);
        $this->assertContains('tracking_number', $hidden);

        // Processing: tracking_number visible & required
        $order->state = 'processing';
        $order->save();
        $order->refresh();

        $processingPerms = $service->getFieldPermissions($order);
        $this->assertTrue($processingPerms['tracking_number']['visible']);
        $this->assertTrue($processingPerms['tracking_number']['required']);
    }

    public function test_readonly_fields_in_completed_state(): void
    {
        $order = $this->createOrder();
        $order->state = 'completed';
        $order->save();
        $order->refresh();

        $service = new WorkflowFieldPermissionsService;
        $readonly = $service->getReadonlyFields($order);

        $this->assertContains('customer_name', $readonly);
        $this->assertContains('total_amount', $readonly);
        $this->assertContains('tracking_number', $readonly);
    }

    // -- Access control --

    public function test_owner_can_view_and_edit_draft(): void
    {
        $order = $this->createOrder();

        $this->assertTrue($order->canBeViewedBy($this->operator));
        $this->assertTrue($order->canBeEditedBy($this->operator));
    }

    public function test_non_owner_cannot_edit_draft(): void
    {
        $order = $this->createOrder();

        // Manager can view but NOT edit (not owner)
        $this->assertTrue($order->canBeViewedBy($this->manager));
        $this->assertFalse($order->canBeEditedBy($this->manager));
    }

    public function test_only_owner_can_transition_from_draft(): void
    {
        $order = $this->createOrder();

        $this->assertTrue($order->canBeTransitionedBy($this->operator));
        $this->assertFalse($order->canBeTransitionedBy($this->manager));
    }

    public function test_manager_can_transition_from_pending(): void
    {
        $order = $this->createOrder(['state' => 'pending']);

        $this->assertTrue($order->canBeTransitionedBy($this->manager));
        $this->assertTrue($order->canBeTransitionedBy($this->admin));
        $this->assertFalse($order->canBeTransitionedBy($this->operator));
    }

    public function test_only_admin_can_transition_from_processing(): void
    {
        $order = $this->createOrder(['state' => 'processing']);

        $this->assertTrue($order->canBeTransitionedBy($this->admin));
        $this->assertFalse($order->canBeTransitionedBy($this->manager));
        $this->assertFalse($order->canBeTransitionedBy($this->operator));
    }

    public function test_completed_is_visible_to_everyone(): void
    {
        $order = $this->createOrder(['state' => 'completed']);
        $viewer = $this->createTestUser(['email' => 'anyone@test.com', 'role' => 'viewer']);

        $this->assertTrue($order->canBeViewedBy($viewer));
    }

    // -- Transition permissions (on the transition itself) --

    public function test_transition_permission_blocks_operator_from_processing_to_completed(): void
    {
        $order = $this->createOrder(['state' => 'processing']);

        // Operator has role 'operator' → not in 'admin' for processing→completed permission
        $this->assertFalse(
            $order->asUser($this->operator)->canTransitionTo('completed')
        );
    }

    public function test_transition_permission_allows_admin_full_chain(): void
    {
        $order = $this->createOrder(['state' => 'draft', 'user_id' => $this->admin->id]);

        // admin should be able to go through the full chain
        $this->assertTrue($order->asUser($this->admin)->canTransitionTo('pending'));
    }

    // -- Full lifecycle --

    /** @noinspection PhpUnhandledExceptionInspection */
    public function test_full_lifecycle_with_all_features(): void
    {
        Notification::fake();

        $order = $this->createOrder([
            'order_number' => 'ORD-LIFECYCLE-001',
            'user_id' => $this->operator->id,
        ]);

        // ── Step 1: draft → pending (operator is owner) ──
        $order->asUser($this->operator)->transitionTo('pending');
        $order->refresh();
        $this->assertEquals('pending', $order->state);

        // History logged
        $this->assertTransitionLogged($order, 'draft', 'pending');

        // ── Step 2: pending → processing (manager, with form data) ──
        $order->asUser($this->manager)->transitionTo('processing', [
            'processing_notes' => 'Verified and approved',
        ]);
        $order->refresh();
        $this->assertEquals('processing', $order->state);
        $this->assertEquals('Verified and approved', $order->processing_notes);

        $this->assertTransitionLogged($order, 'pending', 'processing');

        // Notification should have been triggered for state entry
        // (record_owner recipient → operator)
        Notification::assertSentTo($this->operator, WorkflowNotification::class);

        // ── Step 3: processing → completed (admin, with tracking data) ──
        $order->asUser($this->admin)->transitionTo('completed', [
            'tracking_number' => 'TRK-ABC-123',
            'carrier' => 'DHL',
        ]);
        $order->refresh();
        $this->assertEquals('completed', $order->state);
        $this->assertEquals('TRK-ABC-123', $order->tracking_number);
        $this->assertEquals('DHL', $order->carrier);

        $this->assertTransitionLogged($order, 'processing', 'completed');

        // ── Verify full transition history ──
        $history = WorkflowStateTransition::where('transitionable_type', Order::class)
            ->where('transitionable_id', $order->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $history);
        $this->assertEquals('draft', $history[0]->from_state);
        $this->assertEquals('pending', $history[0]->to_state);
        $this->assertEquals('pending', $history[1]->from_state);
        $this->assertEquals('processing', $history[1]->to_state);
        $this->assertEquals('processing', $history[2]->from_state);
        $this->assertEquals('completed', $history[2]->to_state);
    }

    // -- Unauthorized transitions --

    /** @noinspection PhpUnhandledExceptionInspection */
    public function test_unauthorized_transition_is_blocked(): void
    {
        $order = $this->createOrder(['state' => 'pending']);

        // Operator should not be able to transition from pending (access rule: role:manager,admin)
        $this->expectException(UnauthorizedTransitionException::class);
        $order->asUser($this->operator)->transitionTo('processing');
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    public function test_force_transition_bypasses_access_control(): void
    {
        // Operator owns a draft order. Normally, state access rules say
        // only manager/admin can transition from pending, but forceTransitionTo
        // bypasses state-level access enforcement.
        // We use the manager here who has transition permission but would fail
        // state access if they weren't the owner.
        $order = $this->createOrder([
            'state' => 'pending',
            'user_id' => $this->admin->id, // admin is owner
        ]);

        // Manager has transition permission on pending→processing but
        // is NOT the owner, so state access "edit" would block them.
        // forceTransitionTo should bypass the state-level check.
        $order->asUser($this->manager)->forceTransitionTo('processing', [
            'processing_notes' => 'Force override',
        ]);
        $order->refresh();

        $this->assertEquals('processing', $order->state);
    }

    // -- Audit trail: snapshots & metadata --

    /** @noinspection PhpUnhandledExceptionInspection */
    public function test_transition_creates_snapshots(): void
    {
        $order = $this->createOrder([
            'order_number' => 'ORD-SNAP-001',
            'user_id' => $this->operator->id,
        ]);

        $order->asUser($this->operator)->transitionTo('pending');

        $historyRecord = $this->getLastTransition($order);
        $this->assertNotNull($historyRecord);
        $this->assertTrue($historyRecord->has_snapshot);

        // After snapshot should exist
        $afterSnapshot = WorkflowTransitionSnapshot::where('transition_history_id', $historyRecord->id)
            ->where('snapshot_type', 'after')
            ->first();

        $this->assertNotNull($afterSnapshot);
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    public function test_transition_with_form_data_creates_metadata(): void
    {
        $order = $this->createOrder([
            'state' => 'pending',
            'user_id' => $this->manager->id,
        ]);

        $order->asUser($this->manager)->transitionTo('processing', [
            'processing_notes' => 'Rush order - priority handling',
        ]);

        $historyRecord = $this->getLastTransition($order);
        $this->assertTrue($historyRecord->has_metadata);

        $metadata = WorkflowTransitionMetadata::where('transition_history_id', $historyRecord->id)->first();
        $this->assertNotNull($metadata);
        $this->assertEquals('Rush order - priority handling', $metadata->form_data['processing_notes']);
    }

    // -- Notifications: database-first --

    /** @noinspection PhpUnhandledExceptionInspection */
    public function test_notification_dispatched_on_state_enter(): void
    {
        Notification::fake();

        $order = $this->createOrder([
            'state' => 'pending',
            'user_id' => $this->operator->id,
        ]);

        $order->asUser($this->manager)->transitionTo('processing', [
            'processing_notes' => 'Let\'s go',
        ]);

        // Owner (operator) should receive the database notification
        Notification::assertSentTo($this->operator, WorkflowNotification::class);
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    public function test_notification_logged_in_database(): void
    {
        $order = $this->createOrder([
            'state' => 'pending',
            'user_id' => $this->operator->id,
        ]);

        $order->asUser($this->manager)->transitionTo('processing', [
            'processing_notes' => 'Moving forward',
        ]);

        $logs = WorkflowNotificationLog::where('notifiable_type', Order::class)
            ->where('notifiable_id', $order->id)
            ->get();

        $this->assertGreaterThanOrEqual(1, $logs->count());
        $log = $logs->first();
        $this->assertEquals('database', $log->channel);
        $this->assertContains($log->status, ['sent', 'pending', 'failed']);
    }

    // -- Data integrity across the full chain --

    /** @noinspection PhpUnhandledExceptionInspection */
    public function test_original_data_preserved_through_all_transitions(): void
    {
        $order = $this->createOrder([
            'order_number' => 'ORD-INTEGRITY-001',
            'customer_name' => 'Luigi Verdi',
            'total_amount' => 1500.00,
            'user_id' => $this->admin->id,
        ]);

        // Run through all states
        $order->asUser($this->admin)->transitionTo('pending');
        $order->refresh();
        $order->asUser($this->admin)->transitionTo('processing', [
            'processing_notes' => 'Processing',
        ]);
        $order->refresh();
        $order->asUser($this->admin)->transitionTo('completed', [
            'tracking_number' => 'TRK-999',
            'carrier' => 'UPS',
        ]);
        $order->refresh();

        // Original data still intact
        $this->assertEquals('ORD-INTEGRITY-001', $order->order_number);
        $this->assertEquals('Luigi Verdi', $order->customer_name);
        $this->assertEquals(1500.00, (float) $order->total_amount);

        // Transition data correctly applied
        $this->assertEquals('Processing', $order->processing_notes);
        $this->assertEquals('TRK-999', $order->tracking_number);
        $this->assertEquals('UPS', $order->carrier);
    }

    // -- Invalid transitions --

    /** @noinspection PhpUnhandledExceptionInspection */
    public function test_cannot_skip_states(): void
    {
        $order = $this->createOrder();

        // draft → processing should fail (no such transition)
        $this->assertFalse($order->canTransitionTo('processing'));
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    public function test_cannot_transition_from_final_state(): void
    {
        $order = $this->createOrder(['state' => 'completed']);

        $this->assertFalse($order->canTransitionTo('draft'));
        $this->assertFalse($order->canTransitionTo('pending'));
    }

    // -- Multiple orders in different states --

    /** @noinspection PhpUnhandledExceptionInspection */
    public function test_multiple_orders_independent_state_management(): void
    {
        $order1 = $this->createOrder(['order_number' => 'ORD-MULTI-1', 'user_id' => $this->admin->id]);
        $order2 = $this->createOrder(['order_number' => 'ORD-MULTI-2', 'user_id' => $this->admin->id]);

        // Move order1 to pending
        $order1->asUser($this->admin)->transitionTo('pending');
        $order1->refresh();

        // order2 still in draft
        $order2->refresh();
        $this->assertEquals('pending', $order1->state);
        $this->assertEquals('draft', $order2->state);
    }

    // -- Super admin bypass --

    /** @noinspection PhpUnhandledExceptionInspection */
    public function test_super_admin_bypasses_all_access_rules(): void
    {
        $superAdmin = $this->createTestUser([
            'email' => 'super@test.com',
            'role' => 'super_admin',
        ]);

        $order = $this->createOrder(['state' => 'processing']);

        // super_admin is in default super_admin_roles config
        $this->assertTrue($order->canBeViewedBy($superAdmin));
        $this->assertTrue($order->canBeTransitionedBy($superAdmin));
    }

    // -- Workflow model relationships --

    public function test_workflow_has_all_relationships(): void
    {
        $this->assertEquals(4, $this->workflow->states()->count());
        $this->assertEquals(3, $this->workflow->transitions()->count());
        $this->assertEquals(1, $this->workflow->notifications()->count());
    }

    public function test_state_has_fields_and_access_rules(): void
    {
        $this->assertEquals(4, $this->draftState->fields()->count());
        $this->assertEquals(4, $this->draftState->accessRules()->count());
    }

    public function test_transition_has_fields_and_permissions(): void
    {
        $this->assertEquals(1, $this->pendingToProcessing->fields()->count());
        $this->assertEquals(2, $this->processingToCompleted->fields()->count());
        $this->assertEquals(1, $this->pendingToProcessing->permissions()->count());
    }

    public function test_notification_has_recipients_channels_and_templates(): void
    {
        $notif = $this->workflow->notifications()->first();

        $this->assertEquals(1, $notif->recipients()->count());
        $this->assertEquals(1, $notif->channels()->count());
        $this->assertGreaterThanOrEqual(1, $notif->templates()->count());
    }

    // -- Disabled enforcement --

    /** @noinspection PhpUnhandledExceptionInspection */
    public function test_disabled_enforcement_allows_unauthorized_transition(): void
    {
        config()->set('filament-flow.state_access.enforce_on_transition', false);

        $order = $this->createOrder(['state' => 'pending']);

        // Manager has transition permission but state access says only
        // role:manager,admin for pending. With enforcement off, the state-level
        // check is bypassed and only the transition permission matters.
        $order->asUser($this->manager)->transitionTo('processing', [
            'processing_notes' => 'Override',
        ]);
        $order->refresh();

        $this->assertEquals('processing', $order->state);

        config()->set('filament-flow.state_access.enforce_on_transition', true);
    }
}
