<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Filament;

use Livewire\Livewire;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateField;
use RoBYCoNTe\FilamentFlow\Tests\FilamentTestCase;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Resources\OrderResource\Pages\EditOrder;

class OrderEditPageTest extends FilamentTestCase
{
    private array $workflowData;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workflowData = $this->createFullWorkflow();
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'edit-test@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($this->user);
    }

    public function test_edit_page_renders(): void
    {
        $order = $this->createOrderInState('pending', ['user_id' => $this->user->id]);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertSuccessful();
    }

    public function test_form_fields_match_resource(): void
    {
        $order = $this->createOrderInState('pending', ['user_id' => $this->user->id]);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertFormFieldExists('order_number')
            ->assertFormFieldExists('customer_name')
            ->assertFormFieldExists('total_amount')
            ->assertFormFieldExists('notes');
    }

    public function test_field_hidden_in_pending_state(): void
    {
        // tracking_number is LOCKED in pending state = hidden
        $order = $this->createOrderInState('pending', ['user_id' => $this->user->id]);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertFormFieldHidden('tracking_number');
    }

    public function test_field_editable_in_shipped_state(): void
    {
        // tracking_number is visible+editable in shipped state
        $order = $this->createOrderInState('shipped', ['user_id' => $this->user->id]);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertFormFieldExists('tracking_number');
    }

    public function test_field_readonly_in_processing_state(): void
    {
        // order_number is readonly in processing state
        $order = $this->createOrderInState('processing', ['user_id' => $this->user->id]);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertFormFieldDisabled('order_number');
    }

    public function test_field_required_in_processing_state(): void
    {
        // processing_notes is required in processing state
        $order = $this->createOrderInState('processing', ['user_id' => $this->user->id]);

        // Fill the form without processing_notes to trigger required validation
        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->fillForm(['processing_notes' => ''])
            ->call('save')
            ->assertHasFormErrors(['processing_notes' => 'required']);
    }

    public function test_permissions_change_with_state(): void
    {
        // In pending: tracking_number is locked (hidden)
        $pendingOrder = $this->createOrderInState('pending', ['user_id' => $this->user->id]);
        Livewire::test(EditOrder::class, ['record' => $pendingOrder->getRouteKey()])
            ->assertFormFieldHidden('tracking_number');

        // In shipped: tracking_number is visible and editable
        $shippedOrder = $this->createOrderInState('shipped', ['user_id' => $this->user->id]);
        Livewire::test(EditOrder::class, ['record' => $shippedOrder->getRouteKey()])
            ->assertFormFieldExists('tracking_number');
    }

    public function test_locked_field_completely_hidden(): void
    {
        $order = $this->createOrderInState('pending', ['user_id' => $this->user->id]);

        // tracking_number is locked (mutability=locked) in pending
        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertFormFieldHidden('tracking_number');
    }

    public function test_save_record_updates_data(): void
    {
        $order = $this->createOrderInState('pending', ['user_id' => $this->user->id]);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->fillForm([
                'customer_name' => 'Updated Customer',
                'notes' => 'Updated notes',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $order->refresh();
        $this->assertEquals('Updated Customer', $order->customer_name);
    }

    public function test_form_without_workflow_shows_all(): void
    {
        // Disable workflow
        config()->set('filament-flow.enabled', false);

        $order = $this->createOrderInState('pending', ['user_id' => $this->user->id]);

        // When workflow is disabled, all fields should be visible (no permissions applied)
        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertFormFieldExists('tracking_number')
            ->assertFormFieldExists('carrier');
    }

    public function test_form_without_permissions_shows_all(): void
    {
        // Delete all field permissions
        WorkflowStateField::query()->delete();

        $order = $this->createOrderInState('pending', ['user_id' => $this->user->id]);

        // Without any WorkflowStateField records, getFieldPermissions returns []
        // which means no permissions applied = all fields visible
        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertFormFieldExists('order_number')
            ->assertFormFieldExists('tracking_number');
    }

    public function test_role_override_makes_field_visible(): void
    {
        // carrier is hidden in pending state
        // Add role override for 'admin' to make it visible
        $carrierField = WorkflowStateField::where('state_id', $this->workflowData['states']['pending']->id)
            ->where('field_name', 'carrier')
            ->first();

        $this->assertNotNull($carrierField);

        $this->createFieldRoleOverride($carrierField, 'admin', 'visible');

        // Create admin user
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-edit@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $order = $this->createOrderInState('pending', ['user_id' => $admin->id]);

        $this->actingAs($admin);

        // Admin should see carrier (role override makes it visible)
        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertFormFieldExists('carrier');
    }

    public function test_owner_sees_different_permissions(): void
    {
        // Add @owner override for carrier in pending state
        $carrierField = WorkflowStateField::where('state_id', $this->workflowData['states']['pending']->id)
            ->where('field_name', 'carrier')
            ->first();

        $this->assertNotNull($carrierField);

        $this->createFieldRoleOverride($carrierField, '@owner', 'visible');

        $order = $this->createOrderInState('pending', ['user_id' => $this->user->id]);

        // Owner should see carrier (hidden base, but @owner override makes visible)
        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertFormFieldExists('carrier');
    }
}
