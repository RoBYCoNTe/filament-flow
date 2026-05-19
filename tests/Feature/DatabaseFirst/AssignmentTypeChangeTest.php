<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class AssignmentTypeChangeTest extends TestCase
{
    private Order $order;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->createTestUser(['name' => 'Type Change User', 'email' => 'typechange@example.com']);
        $this->order = Order::create([
            'order_number' => 'ORD-TC-001',
            'customer_name' => 'Type Change Customer',
            'total_amount' => 100.00,
        ]);
    }

    public function test_change_assignment_type_updates_the_type(): void
    {
        $assignment = $this->order->assignTo($this->user, 'viewer');

        $result = $this->order->changeAssignmentType($assignment->id, 'secondary');

        $this->assertTrue($result);
        $assignment->refresh();
        $this->assertEquals('secondary', $assignment->assignment_type);
    }

    public function test_change_assignment_type_to_primary(): void
    {
        $assignment = $this->order->assignTo($this->user, 'viewer');

        $this->order->changeAssignmentType($assignment->id, 'primary');

        $assignment->refresh();
        $this->assertEquals('primary', $assignment->assignment_type);
    }

    public function test_change_assignment_type_to_viewer(): void
    {
        $assignment = $this->order->assignTo($this->user, 'primary');

        $this->order->changeAssignmentType($assignment->id, 'viewer');

        $assignment->refresh();
        $this->assertEquals('viewer', $assignment->assignment_type);
    }

    public function test_change_assignment_type_returns_false_for_nonexistent_assignment(): void
    {
        $result = $this->order->changeAssignmentType(99999, 'secondary');

        $this->assertFalse($result);
    }

    public function test_change_assignment_type_returns_false_on_type_conflict(): void
    {
        $secondUser = $this->createTestUser(['name' => 'Second User', 'email' => 'second@example.com']);

        $viewerAssignment = $this->order->assignTo($this->user, 'viewer');
        $this->order->assignTo($this->user, 'secondary');

        $result = $this->order->changeAssignmentType($viewerAssignment->id, 'secondary');

        $this->assertFalse($result);

        $viewerAssignment->refresh();
        $this->assertEquals('viewer', $viewerAssignment->assignment_type);
    }

    public function test_change_assignment_type_does_not_affect_other_users(): void
    {
        $otherUser = $this->createTestUser(['name' => 'Other User', 'email' => 'other@example.com']);

        $assignment = $this->order->assignTo($this->user, 'viewer');
        $otherAssignment = $this->order->assignTo($otherUser, 'primary');

        $this->order->changeAssignmentType($assignment->id, 'secondary');

        $otherAssignment->refresh();
        $this->assertEquals('primary', $otherAssignment->assignment_type);
    }

    public function test_change_assignment_type_does_not_belong_to_different_record(): void
    {
        $otherOrder = Order::create([
            'order_number' => 'ORD-TC-002',
            'customer_name' => 'Other Customer',
            'total_amount' => 200.00,
        ]);

        $assignmentOnOtherOrder = $otherOrder->assignTo($this->user, 'viewer');

        $result = $this->order->changeAssignmentType($assignmentOnOtherOrder->id, 'primary');

        $this->assertFalse($result);
        $assignmentOnOtherOrder->refresh();
        $this->assertEquals('viewer', $assignmentOnOtherOrder->assignment_type);
    }
}
