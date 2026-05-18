<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use RoBYCoNTe\FilamentFlow\Models\WorkflowAssignment;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * Test workflow assignment to records and users (Database-First approach)
 */
class WorkflowAssignmentTest extends TestCase
{
    /**
     * Test creating an assignment
     */
    public function test_create_assignment(): void
    {
        $user = $this->createTestUser(['name' => 'John Smith', 'email' => 'john@example.com']);

        $order = Order::create([
            'order_number' => 'ORD-ASSIGN-001',
            'customer_name' => 'John Doe',
            'total_amount' => 100.00,
        ]);

        $assignment = WorkflowAssignment::create([
            'assignable_type' => Order::class,
            'assignable_id' => $order->id,
            'user_id' => $user->id,
            'assignment_type' => 'primary',
        ]);

        $this->assertNotNull($assignment->id);
        $this->assertEquals($order->id, $assignment->assignable_id);
        $this->assertEquals($user->id, $assignment->user_id);
        $this->assertEquals('primary', $assignment->assignment_type);
    }

    /**
     * Test assignment tracks assignment time
     */
    public function test_assignment_tracks_time(): void
    {
        $user = $this->createTestUser(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        $order = Order::create([
            'order_number' => 'ORD-ASSIGN-002',
            'customer_name' => 'Jane Doe',
            'total_amount' => 150.00,
        ]);

        $assignment = WorkflowAssignment::create([
            'assignable_type' => Order::class,
            'assignable_id' => $order->id,
            'user_id' => $user->id,
            'assigned_at' => now(),
        ]);

        $this->assertNotNull($assignment->assigned_at);
    }

    /**
     * Test multiple assignments for same record
     */
    public function test_multiple_assignments_for_same_record(): void
    {
        $user1 = $this->createTestUser(['name' => 'User 1', 'email' => 'user1@example.com']);
        $user2 = $this->createTestUser(['name' => 'User 2', 'email' => 'user2@example.com']);

        $order = Order::create([
            'order_number' => 'ORD-ASSIGN-003',
            'customer_name' => 'Bob Smith',
            'total_amount' => 200.00,
        ]);

        WorkflowAssignment::create([
            'assignable_type' => Order::class,
            'assignable_id' => $order->id,
            'user_id' => $user1->id,
            'assignment_type' => 'primary',
        ]);

        WorkflowAssignment::create([
            'assignable_type' => Order::class,
            'assignable_id' => $order->id,
            'user_id' => $user2->id,
            'assignment_type' => 'secondary',
        ]);

        $assignments = WorkflowAssignment::where('assignable_id', $order->id)
            ->where('assignable_type', Order::class)
            ->get();

        $this->assertCount(2, $assignments);
    }

    /**
     * Test assignment retrieval by record
     */
    public function test_retrieve_assignments_by_record(): void
    {
        $user = $this->createTestUser(['name' => 'Assignee 1', 'email' => 'assignee1@example.com']);

        $order = Order::create([
            'order_number' => 'ORD-ASSIGN-004',
            'customer_name' => 'Alice Johnson',
            'total_amount' => 300.00,
        ]);

        WorkflowAssignment::create([
            'assignable_type' => Order::class,
            'assignable_id' => $order->id,
            'user_id' => $user->id,
        ]);

        $found = WorkflowAssignment::where('assignable_type', Order::class)
            ->where('assignable_id', $order->id)
            ->first();

        $this->assertNotNull($found);
        $this->assertEquals($user->id, $found->user_id);
    }

    /**
     * Test assignment can store different types
     */
    public function test_assignment_stores_assignment_type(): void
    {
        $user = $this->createTestUser(['name' => 'Charlie Smith', 'email' => 'charlie@example.com']);

        $order = Order::create([
            'order_number' => 'ORD-ASSIGN-005',
            'customer_name' => 'Charlie Brown',
            'total_amount' => 250.00,
        ]);

        $assignment = WorkflowAssignment::create([
            'assignable_type' => Order::class,
            'assignable_id' => $order->id,
            'user_id' => $user->id,
            'assignment_type' => 'viewer',
        ]);

        $this->assertEquals($user->id, $assignment->user_id);
        $this->assertEquals('viewer', $assignment->assignment_type);
    }

    /**
     * Test assignment user relationship
     */
    public function test_assignment_user_relationship(): void
    {
        $user = $this->createTestUser(['name' => 'David', 'email' => 'david@example.com']);

        $order = Order::create([
            'order_number' => 'ORD-ASSIGN-006',
            'customer_name' => 'David Lee',
            'total_amount' => 175.00,
        ]);

        $assignment = WorkflowAssignment::create([
            'assignable_type' => Order::class,
            'assignable_id' => $order->id,
            'user_id' => $user->id,
        ]);

        $this->assertEquals($user->id, $assignment->user->id);
        $this->assertEquals($user->name, $assignment->user->name);
    }

    /**
     * Test list assignments for record
     */
    public function test_list_assignments_for_record(): void
    {
        $user1 = $this->createTestUser(['name' => 'User 1', 'email' => 'user1a@example.com']);
        $user2 = $this->createTestUser(['name' => 'User 2', 'email' => 'user2a@example.com']);

        $order = Order::create([
            'order_number' => 'ORD-ASSIGN-007',
            'customer_name' => 'Eve Martin',
            'total_amount' => 400.00,
        ]);

        WorkflowAssignment::create([
            'assignable_type' => Order::class,
            'assignable_id' => $order->id,
            'user_id' => $user1->id,
            'assignment_type' => 'primary',
        ]);

        WorkflowAssignment::create([
            'assignable_type' => Order::class,
            'assignable_id' => $order->id,
            'user_id' => $user2->id,
            'assignment_type' => 'secondary',
        ]);

        $assignments = WorkflowAssignment::where('assignable_id', $order->id)
            ->where('assignable_type', Order::class)
            ->get();

        $this->assertCount(2, $assignments);
    }
}
