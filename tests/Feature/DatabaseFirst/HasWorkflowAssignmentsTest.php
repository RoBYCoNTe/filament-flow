<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * Test HasWorkflowAssignments trait functionality
 */
class HasWorkflowAssignmentsTest extends TestCase
{
    /**
     * Test assigning user to record
     */
    public function test_assign_user_to_record(): void
    {
        $user = $this->createTestUser(['name' => 'John Smith', 'email' => 'john@example.com']);

        $order = Order::create([
            'order_number' => 'ORD-TRAIT-001',
            'customer_name' => 'John Doe',
            'total_amount' => 100.00,
        ]);

        $assignment = $order->assignTo($user, 'primary');

        $this->assertNotNull($assignment);
        $this->assertEquals($user->id, $assignment->user_id);
        $this->assertEquals('primary', $assignment->assignment_type);
    }

    /**
     * Test checking if user is assigned
     */
    public function test_check_if_user_assigned(): void
    {
        $user = $this->createTestUser(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        $order = Order::create([
            'order_number' => 'ORD-TRAIT-002',
            'customer_name' => 'Jane Doe',
            'total_amount' => 150.00,
        ]);

        $this->assertFalse($order->isAssignedTo($user));

        $order->assignTo($user);

        $this->assertTrue($order->isAssignedTo($user));
    }

    /**
     * Test unassigning user from record
     */
    public function test_unassign_user_from_record(): void
    {
        $user = $this->createTestUser(['name' => 'Bob Smith', 'email' => 'bob@example.com']);

        $order = Order::create([
            'order_number' => 'ORD-TRAIT-003',
            'customer_name' => 'Bob Doe',
            'total_amount' => 200.00,
        ]);

        $order->assignTo($user);
        $this->assertTrue($order->isAssignedTo($user));

        $order->unassignFrom($user);
        $this->assertFalse($order->isAssignedTo($user));
    }

    /**
     * Test getting assigned users
     */
    public function test_get_assigned_users(): void
    {
        $user1 = $this->createTestUser(['name' => 'User 1', 'email' => 'user1@example.com']);
        $user2 = $this->createTestUser(['name' => 'User 2', 'email' => 'user2@example.com']);

        $order = Order::create([
            'order_number' => 'ORD-TRAIT-004',
            'customer_name' => 'Alice Johnson',
            'total_amount' => 300.00,
        ]);

        $order->assignTo($user1, 'primary');
        $order->assignTo($user2, 'secondary');

        $assignedUsers = $order->getAssignedUsers();

        $this->assertCount(2, $assignedUsers);
    }

    /**
     * Test getting assigned user IDs
     */
    public function test_get_assigned_user_ids(): void
    {
        $user1 = $this->createTestUser(['name' => 'User A', 'email' => 'usera@example.com']);
        $user2 = $this->createTestUser(['name' => 'User B', 'email' => 'userb@example.com']);

        $order = Order::create([
            'order_number' => 'ORD-TRAIT-005',
            'customer_name' => 'Charlie Brown',
            'total_amount' => 250.00,
        ]);

        $order->assignTo($user1);
        $order->assignTo($user2);

        $userIds = $order->getAssignedUserIds();

        $this->assertCount(2, $userIds);
        $this->assertContains($user1->id, $userIds);
        $this->assertContains($user2->id, $userIds);
    }

    /**
     * Test getting users by assignment type
     */
    public function test_get_users_by_type(): void
    {
        $user1 = $this->createTestUser(['name' => 'Primary', 'email' => 'primary@example.com']);
        $user2 = $this->createTestUser(['name' => 'Secondary', 'email' => 'secondary@example.com']);
        $user3 = $this->createTestUser(['name' => 'Viewer', 'email' => 'viewer@example.com']);

        $order = Order::create([
            'order_number' => 'ORD-TRAIT-006',
            'customer_name' => 'David Lee',
            'total_amount' => 175.00,
        ]);

        $order->assignTo($user1, 'primary');
        $order->assignTo($user2, 'secondary');
        $order->assignTo($user3, 'viewer');

        $primaryUsers = $order->getPrimaryAssignedUsers();
        $secondaryUsers = $order->getSecondaryAssignedUsers();
        $viewerUsers = $order->getViewerAssignedUsers();

        $this->assertCount(1, $primaryUsers);
        $this->assertCount(1, $secondaryUsers);
        $this->assertCount(1, $viewerUsers);
    }

    /**
     * Test reassigning from one user to another
     */
    public function test_reassign_user(): void
    {
        $user1 = $this->createTestUser(['name' => 'Old User', 'email' => 'old@example.com']);
        $user2 = $this->createTestUser(['name' => 'New User', 'email' => 'new@example.com']);

        $order = Order::create([
            'order_number' => 'ORD-TRAIT-007',
            'customer_name' => 'Eve Martin',
            'total_amount' => 400.00,
        ]);

        $order->assignTo($user1, 'primary');
        $this->assertTrue($order->isAssignedTo($user1, 'primary'));
        $this->assertFalse($order->isAssignedTo($user2, 'primary'));

        $order->reassign($user1, $user2, 'primary');

        $this->assertFalse($order->isAssignedTo($user1, 'primary'));
        $this->assertTrue($order->isAssignedTo($user2, 'primary'));
    }

    /**
     * Test syncing assignments
     */
    public function test_sync_assignments(): void
    {
        $user1 = $this->createTestUser(['name' => 'Sync 1', 'email' => 'sync1@example.com']);
        $user2 = $this->createTestUser(['name' => 'Sync 2', 'email' => 'sync2@example.com']);
        $user3 = $this->createTestUser(['name' => 'Sync 3', 'email' => 'sync3@example.com']);

        $order = Order::create([
            'order_number' => 'ORD-TRAIT-008',
            'customer_name' => 'Frank Miller',
            'total_amount' => 550.00,
        ]);

        $order->assignTo($user1, 'primary');
        $order->assignTo($user2, 'primary');

        // Sync to only user2 and user3
        $order->syncAssignments([$user2->id, $user3->id], 'primary');

        $primaryUserIds = $order->getAssignedUserIds(['primary']);

        $this->assertCount(2, $primaryUserIds);
        $this->assertNotContains($user1->id, $primaryUserIds);
        $this->assertContains($user2->id, $primaryUserIds);
        $this->assertContains($user3->id, $primaryUserIds);
    }

    /**
     * Test clearing all assignments
     */
    public function test_clear_assignments(): void
    {
        $user1 = $this->createTestUser(['name' => 'Clear 1', 'email' => 'clear1@example.com']);
        $user2 = $this->createTestUser(['name' => 'Clear 2', 'email' => 'clear2@example.com']);

        $order = Order::create([
            'order_number' => 'ORD-TRAIT-009',
            'customer_name' => 'Grace Wilson',
            'total_amount' => 600.00,
        ]);

        $order->assignTo($user1, 'primary');
        $order->assignTo($user2, 'secondary');

        $order->clearAssignments();

        $this->assertFalse($order->isAssignedTo($user1));
        $this->assertFalse($order->isAssignedTo($user2));
    }

    /**
     * Test getting assignment types for user
     */
    public function test_get_assignment_types_for_user(): void
    {
        $user = $this->createTestUser(['name' => 'Types', 'email' => 'types@example.com']);

        $order = Order::create([
            'order_number' => 'ORD-TRAIT-010',
            'customer_name' => 'Henry Davis',
            'total_amount' => 700.00,
        ]);

        $order->assignTo($user, 'primary');
        $order->assignTo($user, 'secondary');

        $types = $order->getAssignmentTypesForUser($user);

        $this->assertCount(2, $types);
        $this->assertContains('primary', $types);
        $this->assertContains('secondary', $types);
    }

    /**
     * Test checking if user has specific assignment type
     */
    public function test_has_assignment_type(): void
    {
        $user = $this->createTestUser(['name' => 'Type Check', 'email' => 'typecheck@example.com']);

        $order = Order::create([
            'order_number' => 'ORD-TRAIT-011',
            'customer_name' => 'Iris Thompson',
            'total_amount' => 800.00,
        ]);

        $order->assignTo($user, 'primary');

        $this->assertTrue($order->hasAssignmentType($user, 'primary'));
        $this->assertFalse($order->hasAssignmentType($user, 'secondary'));
    }

    /**
     * Test morphMany relationship
     */
    public function test_assignments_relationship(): void
    {
        $user1 = $this->createTestUser(['name' => 'Relation 1', 'email' => 'relation1@example.com']);
        $user2 = $this->createTestUser(['name' => 'Relation 2', 'email' => 'relation2@example.com']);

        $order = Order::create([
            'order_number' => 'ORD-TRAIT-012',
            'customer_name' => 'Jack Wilson',
            'total_amount' => 900.00,
        ]);

        $order->assignTo($user1);
        $order->assignTo($user2);

        $assignments = $order->assignments;

        $this->assertCount(2, $assignments);
    }
}
