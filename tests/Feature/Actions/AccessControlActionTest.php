<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Actions;

use Livewire\Livewire;
use RoBYCoNTe\FilamentFlow\Actions\AccessControlAction;
use RoBYCoNTe\FilamentFlow\Livewire\AssignmentManager;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * The door to the room where the hands are dealt: it carries a name of its own, it hides from
 * whoever may not manage the work (or from everyone but super administrators, when the host
 * says so), and it hands the panel the record — the one beside it, or the one it was given.
 */
class AccessControlActionTest extends TestCase
{
    public function test_it_carries_its_own_name_and_words(): void
    {
        $action = AccessControlAction::make();

        $this->assertSame('accessControl', $action->getName());
        $this->assertSame(__('filament-flow::messages.access_control'), $action->getLabel());
    }

    public function test_it_hides_from_the_anonymous(): void
    {
        $action = AccessControlAction::make();

        $this->assertFalse($action->isVisible());
    }

    public function test_admins_see_it_by_default(): void
    {
        $this->actingAs($this->createTestUser(['email' => 'admin@test.com', 'role' => 'admin']));

        $this->assertTrue(AccessControlAction::make()->isVisible());
    }

    public function test_super_admin_only_keeps_plain_admins_out(): void
    {
        $this->actingAs($this->createTestUser(['email' => 'admin@test.com', 'role' => 'admin']));

        $this->assertFalse(AccessControlAction::make()->superAdminOnly()->isVisible());
    }

    public function test_super_admins_stay_in_when_super_admin_only(): void
    {
        $this->actingAs($this->createTestUser(['email' => 'super@test.com', 'role' => 'super_admin']));

        $this->assertTrue(AccessControlAction::make()->superAdminOnly()->isVisible());
    }

    public function test_the_record_beside_it_reaches_the_panel(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-ACA-001',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
        ]);

        $action = AccessControlAction::make();

        $resolved = $this->resolvePanelRecord($action, $order);

        $this->assertNotNull($resolved);
        $this->assertEquals($order->getKey(), $resolved->getKey());
    }

    public function test_a_record_given_by_hand_reaches_the_panel(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-ACA-002',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
        ]);

        $action = AccessControlAction::make()
            ->accessRecord(fn (): Order => $order);

        $resolved = $this->resolvePanelRecord($action, null);

        $this->assertNotNull($resolved);
        $this->assertEquals($order->getKey(), $resolved->getKey());
    }

    public function test_a_key_with_its_type_reaches_the_panel(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-ACA-003',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
        ]);

        $action = AccessControlAction::make()
            ->accessRecord($order->getKey())
            ->accessRecordType(Order::class);

        $resolved = $this->resolvePanelRecord($action, null);

        $this->assertNotNull($resolved);
        $this->assertEquals($order->getKey(), $resolved->getKey());
    }

    public function test_the_panel_keeps_its_own_protection_even_inside_the_door(): void
    {
        // The action may open for a plain admin; with superAdminOnly the panel inside still
        // answers to super administrators only.
        $admin = $this->createTestUser(['email' => 'admin@test.com', 'role' => 'admin']);
        $this->actingAs($admin);

        $order = Order::create([
            'order_number' => 'ORD-ACA-004',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
        ]);

        $component = Livewire::test(AssignmentManager::class, [
            'record' => $order,
            'superAdminOnly' => true,
        ]);

        $this->assertFalse($component->instance()->canManageAssignments());
        $this->assertTrue($admin->isAdmin());
    }

    private function resolvePanelRecord(AccessControlAction $action, ?object $record): ?object
    {
        $method = new \ReflectionMethod($action, 'resolvePanelRecord');

        /** @var ?object $resolved */
        $resolved = $method->invoke($action, $record);

        return $resolved;
    }
}
