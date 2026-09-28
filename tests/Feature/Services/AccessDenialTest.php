<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Services;

use RoBYCoNTe\FilamentFlow\Models\WorkflowStateAccessRule;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * The refusal: an assignment may shut a person out of a record, whatever the rules of the
 * state would tell them — and a refusal wins over a permission given beside it.
 *
 * The three readings of an override meet here: the call decides (the row untouched),
 * allowed (true), shut out (false). The docs of the package have promised the third one
 * for a long time; these are the tests that hold them to their word.
 */
class AccessDenialTest extends TestCase
{
    protected User $owner;

    protected User $outsider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->createTestUser([
            'name' => 'The Owner',
            'email' => 'owner@test.com',
        ]);

        $this->outsider = $this->createTestUser([
            'name' => 'The Outsider',
            'email' => 'outsider@test.com',
        ]);
    }

    /** A state that opens to the owner, and to nobody else. */
    private function makeWorkflowWithOwnerViewRule(): void
    {
        $workflow = $this->createTestWorkflow();

        $state = $this->createWorkflowState($workflow, ['name' => 'queued']);

        WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'view',
            'rule' => '@owner',
        ]);
    }

    private function makeOrder(): Order
    {
        return Order::create([
            'order_number' => 'ORD-DENY-'.uniqid(),
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
            'user_id' => $this->owner->id,
            'state' => 'queued',
        ]);
    }

    public function test_denial_blocks_the_owner_even_where_the_state_would_open(): void
    {
        $this->makeWorkflowWithOwnerViewRule();
        $order = $this->makeOrder();

        $this->assertTrue($order->canBeViewedBy($this->owner));

        $order->assignWithOverrides($this->owner, ['view' => false]);

        $this->assertFalse($order->canBeViewedBy($this->owner));
    }

    public function test_grant_opens_the_record_where_the_state_would_not(): void
    {
        $this->makeWorkflowWithOwnerViewRule();
        $order = $this->makeOrder();

        $this->assertFalse($order->canBeViewedBy($this->outsider));

        $order->assignWithOverrides($this->outsider, ['view' => true], 'viewer');

        $this->assertTrue($order->canBeViewedBy($this->outsider));
    }

    public function test_denial_wins_over_a_grant(): void
    {
        $this->makeWorkflowWithOwnerViewRule();
        $order = $this->makeOrder();

        // Two rows, two readings: the person was allowed once and shut out later.
        // The refusal is the one that holds.
        $order->assignWithOverrides($this->outsider, ['view' => true], 'viewer');
        $order->assignWithOverrides($this->outsider, ['view' => false], 'secondary');

        $this->assertFalse($order->canBeViewedBy($this->outsider));
    }

    public function test_follow_leaves_the_decision_to_the_state(): void
    {
        $this->makeWorkflowWithOwnerViewRule();
        $order = $this->makeOrder();

        // An assignment with no explicit word does not move the answer either way.
        $order->assignWithOverrides($this->outsider, ['view' => null], 'viewer');

        $this->assertFalse($order->canBeViewedBy($this->outsider));
        $this->assertTrue($order->canBeViewedBy($this->owner));
    }

    public function test_denial_keeps_the_record_out_of_the_visible_scope(): void
    {
        $this->makeWorkflowWithOwnerViewRule();
        $denied = $this->makeOrder();
        $granted = Order::create([
            'order_number' => 'ORD-DENY-'.uniqid(),
            'customer_name' => 'Other Customer',
            'total_amount' => 50.00,
            'user_id' => $this->owner->id,
            'state' => 'queued',
        ]);

        $denied->assignWithOverrides($this->outsider, ['view' => false], 'secondary');
        $granted->assignWithOverrides($this->outsider, ['view' => true], 'viewer');

        $visibleIds = Order::visibleTo($this->outsider)->pluck('id')->all();

        $this->assertNotContains($denied->getKey(), $visibleIds, 'The refused record stays out of the list.');
        $this->assertContains($granted->getKey(), $visibleIds, 'The granted record stays in the list.');
    }

    public function test_denial_holds_in_the_scope_even_where_the_role_would_open(): void
    {
        // A free state: everybody with a role sees it. A refusal still keeps the record out.
        $workflow = $this->createTestWorkflow();

        $state = $this->createWorkflowState($workflow, ['name' => 'open']);

        WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'view',
            'rule' => '@authenticated',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-DENY-'.uniqid(),
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
            'user_id' => $this->owner->id,
            'state' => 'open',
        ]);

        $this->assertTrue($order->canBeViewedBy($this->outsider));

        $order->assignWithOverrides($this->outsider, ['view' => false], 'secondary');

        $this->assertFalse($order->canBeViewedBy($this->outsider));
        $this->assertNotContains($order->getKey(), Order::visibleTo($this->outsider)->pluck('id')->all());
    }

    public function test_has_access_denial_answers_the_question_directly(): void
    {
        $this->makeWorkflowWithOwnerViewRule();
        $order = $this->makeOrder();

        $this->assertFalse($order->hasAccessDenial($this->owner, 'view'));

        $order->assignWithOverrides($this->owner, ['view' => false]);

        $this->assertTrue($order->hasAccessDenial($this->owner, 'view'));
        $this->assertFalse($order->hasAccessDenial($this->owner, 'edit'));
    }
}
