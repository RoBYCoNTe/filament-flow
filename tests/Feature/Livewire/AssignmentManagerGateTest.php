<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Livewire;

use InvalidArgumentException;
use Livewire\Livewire;
use RoBYCoNTe\FilamentFlow\Livewire\AssignmentManager;
use RoBYCoNTe\FilamentFlow\Support\AccessGate;
use RoBYCoNTe\FilamentFlow\Support\AdminAccessGate;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Gates\ReviewerAccessGate;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * Who deals out the work is a rule of the host: the panel asks a gate, by the class it was
 * given, on every request. The historical rule stays when nobody names another one, and a call
 * that asked for the super administrators only never opens to anybody else.
 */
class AssignmentManagerGateTest extends TestCase
{
    protected Order $order;

    protected User $admin;

    protected User $reviewer;

    protected User $super;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createTestUser(['email' => 'admin@test.com', 'role' => 'admin']);
        $this->reviewer = $this->createTestUser(['email' => 'reviewer@test.com', 'role' => 'reviewer']);
        $this->super = $this->createTestUser(['email' => 'super@test.com', 'role' => 'super_admin']);

        $this->order = Order::create([
            'order_number' => 'ORD-GATE-001',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
        ]);

        ReviewerAccessGate::$lastRecord = null;
    }

    private function manages(User $user, array $parameters = []): bool
    {
        $this->actingAs($user);

        return Livewire::test(AssignmentManager::class, ['record' => $this->order, ...$parameters])
            ->instance()
            ->canManageAssignments();
    }

    public function test_without_a_gate_the_administrators_manage_as_they_always_did(): void
    {
        $this->assertTrue($this->manages($this->admin));
        $this->assertTrue($this->manages($this->super));
        $this->assertFalse($this->manages($this->reviewer));
    }

    public function test_a_gate_of_the_call_opens_the_panel_to_other_people(): void
    {
        $this->assertTrue($this->manages($this->reviewer, ['gate' => ReviewerAccessGate::class]));
    }

    public function test_a_gate_of_the_call_can_shut_out_the_administrators(): void
    {
        $this->assertFalse($this->manages($this->admin, ['gate' => ReviewerAccessGate::class]));
        $this->assertFalse($this->manages($this->super, ['gate' => ReviewerAccessGate::class]));
    }

    public function test_the_gate_is_asked_about_the_record_of_the_panel(): void
    {
        $this->manages($this->reviewer, ['gate' => ReviewerAccessGate::class]);

        $this->assertNotNull(ReviewerAccessGate::$lastRecord);
        $this->assertTrue(ReviewerAccessGate::$lastRecord->is($this->order));
    }

    public function test_a_gate_of_the_configuration_is_used_when_the_call_names_none(): void
    {
        config(['filament-flow.state_access.access_control_gate' => ReviewerAccessGate::class]);

        $this->assertTrue($this->manages($this->reviewer));
        $this->assertFalse($this->manages($this->admin));
    }

    public function test_the_gate_of_the_call_beats_the_one_of_the_configuration(): void
    {
        config(['filament-flow.state_access.access_control_gate' => ReviewerAccessGate::class]);

        $this->assertTrue($this->manages($this->admin, ['gate' => AdminAccessGate::class]));
        $this->assertFalse($this->manages($this->reviewer, ['gate' => AdminAccessGate::class]));
    }

    public function test_super_admin_only_stays_strict_whatever_the_configuration_opens(): void
    {
        config(['filament-flow.state_access.access_control_gate' => ReviewerAccessGate::class]);

        $this->assertFalse($this->manages($this->reviewer, ['superAdminOnly' => true]));
        $this->assertFalse($this->manages($this->admin, ['superAdminOnly' => true]));
        $this->assertTrue($this->manages($this->super, ['superAdminOnly' => true]));
    }

    public function test_a_gate_that_is_not_a_gate_is_refused_with_a_clear_message(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a class that implements');

        AccessGate::resolve(\stdClass::class);
    }

    public function test_the_commands_follow_the_gate_and_not_only_the_buttons(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(AssignmentManager::class, ['record' => $this->order, 'gate' => ReviewerAccessGate::class])
            ->set('addFormData.selectedUserId', $this->reviewer->id)
            ->call('addAssignment');

        $this->assertSame(0, $this->order->fresh()->assignments()->count());

        $this->actingAs($this->reviewer);

        Livewire::test(AssignmentManager::class, ['record' => $this->order, 'gate' => ReviewerAccessGate::class])
            ->set('addFormData.selectedUserId', $this->admin->id)
            ->call('addAssignment');

        $this->assertSame(1, $this->order->fresh()->assignments()->count());
    }
}
