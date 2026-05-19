<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Tables;

use Closure;
use RoBYCoNTe\FilamentFlow\Tables\Columns\AssignmentSummaryColumn;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class AssignmentSummaryColumnTest extends TestCase
{
    protected Order $order;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->createTestUser([
            'name' => 'John Doe',
            'email' => 'john@test.com',
        ]);

        $this->order = Order::create([
            'order_number' => 'ORD-ASC-001',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
        ]);
    }

    public function test_make_creates_instance(): void
    {
        $column = AssignmentSummaryColumn::make('assignments');

        $this->assertInstanceOf(AssignmentSummaryColumn::class, $column);
    }

    public function test_avatar_limit_defaults_to_three(): void
    {
        $column = AssignmentSummaryColumn::make('assignments');

        $this->assertEquals(3, $column->getAvatarLimit());
    }

    public function test_avatar_limit_can_be_changed(): void
    {
        $column = AssignmentSummaryColumn::make('assignments')->avatarLimit(5);

        $this->assertEquals(5, $column->getAvatarLimit());
    }

    public function test_avatar_decorator_defaults_to_null(): void
    {
        $column = AssignmentSummaryColumn::make('assignments');

        $this->assertNull($column->getAvatarDecorator());
    }

    public function test_avatar_decorator_fluent_method_returns_self(): void
    {
        $column = AssignmentSummaryColumn::make('assignments');
        $result = $column->avatarDecorator(fn (array $a): ?array => null);

        $this->assertSame($column, $result);
    }

    public function test_avatar_decorator_can_be_set(): void
    {
        $callback = fn (array $a): ?array => null;
        $column = AssignmentSummaryColumn::make('assignments')->avatarDecorator($callback);

        $this->assertInstanceOf(Closure::class, $column->getAvatarDecorator());
    }

    public function test_get_assigned_users_includes_metadata_key(): void
    {
        $this->order->assignTo($this->user, 'primary');

        $column = AssignmentSummaryColumn::make('assignments');
        $users = $column->getAssignedUsers($this->order);

        $this->assertCount(1, $users);
        $this->assertArrayHasKey('metadata', $users[0]);
    }

    public function test_get_assigned_users_metadata_is_null_when_not_set(): void
    {
        $this->order->assignTo($this->user, 'primary');

        $column = AssignmentSummaryColumn::make('assignments');
        $users = $column->getAssignedUsers($this->order);

        $this->assertNull($users[0]['metadata']);
    }

    public function test_get_assigned_users_metadata_is_populated_when_set(): void
    {
        $this->order->assignWithOverrides(
            $this->user,
            ['view' => true],
            'viewer',
            null,
            ['source' => 'diary_entry'],
        );

        $column = AssignmentSummaryColumn::make('assignments');
        $users = $column->getAssignedUsers($this->order);

        $this->assertCount(1, $users);
        $this->assertEquals(['source' => 'diary_entry'], $users[0]['metadata']);
    }

    public function test_avatar_decorator_callback_receives_assignment_data_shape(): void
    {
        $this->order->assignTo($this->user, 'primary');

        $capturedData = null;
        $column = AssignmentSummaryColumn::make('assignments')
            ->avatarDecorator(function (array $assignment) use (&$capturedData): ?array {
                $capturedData = $assignment;

                return null;
            });

        $users = $column->getAssignedUsers($this->order);
        $decorator = $column->getAvatarDecorator();
        $decorator($users[0]);

        $this->assertArrayHasKey('name', $capturedData);
        $this->assertArrayHasKey('initials', $capturedData);
        $this->assertArrayHasKey('assignment_type', $capturedData);
        $this->assertArrayHasKey('roles', $capturedData);
        $this->assertArrayHasKey('metadata', $capturedData);
    }

    public function test_avatar_decorator_callback_can_return_badge_config(): void
    {
        $this->order->assignWithOverrides(
            $this->user,
            ['view' => true],
            'viewer',
            null,
            ['source' => 'diary_entry'],
        );

        $column = AssignmentSummaryColumn::make('assignments')
            ->avatarDecorator(fn (array $a): ?array => match (true) {
                ($a['metadata']['source'] ?? null) === 'diary_entry' => [
                    'icon' => 'heroicon-m-book-open',
                    'class' => 'bg-warning-400',
                ],
                default => null,
            });

        $users = $column->getAssignedUsers($this->order);
        $decorator = $column->getAvatarDecorator();
        $result = $decorator($users[0]);

        $this->assertNotNull($result);
        $this->assertEquals('heroicon-m-book-open', $result['icon']);
        $this->assertEquals('bg-warning-400', $result['class']);
    }

    public function test_avatar_decorator_returns_null_for_regular_assignment(): void
    {
        $this->order->assignTo($this->user, 'primary');

        $column = AssignmentSummaryColumn::make('assignments')
            ->avatarDecorator(fn (array $a): ?array => match (true) {
                ($a['metadata']['source'] ?? null) === 'diary_entry' => [
                    'icon' => 'heroicon-m-book-open',
                    'class' => 'bg-warning-400',
                ],
                default => null,
            });

        $users = $column->getAssignedUsers($this->order);
        $decorator = $column->getAvatarDecorator();
        $result = $decorator($users[0]);

        $this->assertNull($result);
    }

    public function test_get_assigned_users_returns_empty_for_record_without_assignments_relation(): void
    {
        $column = AssignmentSummaryColumn::make('assignments');

        $this->assertEmpty($column->getAssignedUsers(null));
    }

    public function test_get_assigned_users_includes_assignment_type(): void
    {
        $secondUser = $this->createTestUser([
            'name' => 'Jane Smith',
            'email' => 'jane@test.com',
        ]);

        $this->order->assignTo($this->user, 'primary');
        $this->order->assignTo($secondUser, 'viewer');

        $column = AssignmentSummaryColumn::make('assignments');
        $users = $column->getAssignedUsers($this->order);

        $types = array_column($users, 'assignment_type');
        $this->assertContains('primary', $types);
        $this->assertContains('viewer', $types);
    }
}
