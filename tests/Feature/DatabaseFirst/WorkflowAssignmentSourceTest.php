<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class WorkflowAssignmentSourceTest extends TestCase
{
    private Order $order;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->createTestUser(['name' => 'Source User', 'email' => 'source@example.com']);
        $this->order = Order::create([
            'order_number' => 'ORD-SRC-001',
            'customer_name' => 'Source Customer',
            'total_amount' => 100.00,
        ]);
    }

    public function test_get_metadata_returns_null_when_no_metadata(): void
    {
        $assignment = $this->order->assignTo($this->user, 'viewer');

        $this->assertNull($assignment->getMetadata('source'));
    }

    public function test_get_metadata_returns_value_from_metadata(): void
    {
        $assignment = $this->order->assignWithOverrides(
            $this->user,
            ['view' => true],
            'viewer',
            null,
            ['source' => 'diary_entry'],
        );

        $this->assertEquals('diary_entry', $assignment->getMetadata('source'));
    }

    public function test_get_metadata_returns_null_when_key_absent(): void
    {
        $assignment = $this->order->assignWithOverrides(
            $this->user,
            ['view' => true],
            'viewer',
            null,
            ['source' => 'manual'],
        );

        $this->assertNull($assignment->getMetadata('other_key'));
    }

    public function test_get_metadata_returns_all_when_no_key_given(): void
    {
        $assignment = $this->order->assignWithOverrides(
            $this->user,
            ['view' => true],
            'viewer',
            null,
            ['source' => 'diary_entry'],
        );

        $this->assertIsArray($assignment->getMetadata());
        $this->assertEquals('diary_entry', $assignment->getMetadata()['source']);
    }

    public function test_assign_with_overrides_stores_metadata(): void
    {
        $assignment = $this->order->assignWithOverrides(
            $this->user,
            ['view' => true, 'edit' => false],
            'viewer',
            null,
            ['source' => 'diary_entry', 'extra' => 'data'],
        );

        $assignment->refresh();

        $this->assertEquals('diary_entry', $assignment->metadata['source']);
        $this->assertEquals('data', $assignment->metadata['extra']);
    }

    public function test_assign_with_overrides_without_metadata_leaves_metadata_null(): void
    {
        $assignment = $this->order->assignWithOverrides(
            $this->user,
            ['view' => true],
            'viewer',
        );

        $assignment->refresh();

        $this->assertNull($assignment->metadata);
    }

    public function test_assign_with_overrides_on_existing_merges_metadata(): void
    {
        $this->order->assignWithOverrides(
            $this->user,
            ['view' => true],
            'viewer',
            null,
            ['source' => 'diary_entry'],
        );

        $updated = $this->order->assignWithOverrides(
            $this->user,
            ['view' => true, 'edit' => true],
            'viewer',
            null,
            ['extra' => 'merged'],
        );

        $updated->refresh();

        $this->assertEquals('diary_entry', $updated->metadata['source']);
        $this->assertEquals('merged', $updated->metadata['extra']);
    }

    public function test_assign_with_overrides_on_existing_without_new_metadata_preserves_existing(): void
    {
        $this->order->assignWithOverrides(
            $this->user,
            ['view' => true],
            'viewer',
            null,
            ['source' => 'diary_entry'],
        );

        $updated = $this->order->assignWithOverrides(
            $this->user,
            ['view' => true, 'edit' => true],
            'viewer',
        );

        $updated->refresh();

        $this->assertEquals('diary_entry', $updated->metadata['source']);
    }
}
