<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\UI;

use Filament\Schemas\Components\Tabs\Tab;
use RoBYCoNTe\FilamentFlow\StateTabs;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Ticket;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\PendingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ProcessingState;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * StateTabs UI Component Tests
 *
 * Tests the StateTabs functionality for creating
 * tab-based filtering in Filament resources.
 */
class StateTabsTest extends TestCase
{
    /**
     * Create an order with the specified state
     */
    private function createOrderInState(string $stateClass, array $attributes = []): Order
    {
        $order = Order::create(array_merge([
            'order_number' => 'ORD-TABS-'.uniqid(),
            'customer_name' => 'Tabs Test Customer',
            'total_amount' => 100.00,
        ], $attributes));

        $order->state = new $stateClass($order);
        $order->save();
        $order->refresh();

        return $order;
    }

    // ===========================================
    // CONFIGURATION TESTS
    // ===========================================

    /**
     * Test StateTabs can be instantiated
     */
    public function test_tabs_can_be_created(): void
    {
        $tabs = StateTabs::make(Order::class);

        $this->assertInstanceOf(StateTabs::class, $tabs);
    }

    /**
     * Test StateTabs can use custom attribute
     */
    public function test_tabs_can_use_custom_attribute(): void
    {
        $tabs = StateTabs::make(Order::class)
            ->attribute('status');

        $attribute = $tabs->getAttribute();

        $this->assertEquals('status', $attribute);
    }

    /**
     * Test StateTabs badge configuration
     */
    public function test_tabs_badge_configuration(): void
    {
        $tabsWithBadge = StateTabs::make(Order::class)
            ->attribute('state')
            ->badge(true);

        $tabsWithoutBadge = StateTabs::make(Order::class)
            ->attribute('state')
            ->badge(false);

        // Both should be StateTabs instances
        $this->assertInstanceOf(StateTabs::class, $tabsWithBadge);
        $this->assertInstanceOf(StateTabs::class, $tabsWithoutBadge);
    }

    /**
     * Test StateTabs includeAll configuration
     */
    public function test_tabs_include_all_configuration(): void
    {
        $tabsWithAll = StateTabs::make(Order::class)
            ->attribute('state')
            ->includeAll(true);

        $tabsWithoutAll = StateTabs::make(Order::class)
            ->attribute('state')
            ->includeAll(false);

        $this->assertInstanceOf(StateTabs::class, $tabsWithAll);
        $this->assertInstanceOf(StateTabs::class, $tabsWithoutAll);
    }

    // ===========================================
    // FLUENT API TESTS
    // ===========================================

    /**
     * Test fluent API chaining
     */
    public function test_fluent_api_chaining(): void
    {
        $tabs = StateTabs::make(Order::class)
            ->attribute('state')
            ->badge(true)
            ->includeAll(true);

        $this->assertInstanceOf(StateTabs::class, $tabs);
        $this->assertEquals('state', $tabs->getAttribute());
    }

    // ===========================================
    // ORDER DATA TESTS
    // ===========================================

    /**
     * Test orders can be created in different states
     */
    public function test_orders_can_be_created_in_different_states(): void
    {
        $pending = $this->createOrderInState(PendingState::class);
        $processing = $this->createOrderInState(ProcessingState::class);

        $this->assertInstanceOf(PendingState::class, $pending->state);
        $this->assertInstanceOf(ProcessingState::class, $processing->state);
    }

    /**
     * Test order count by state
     */
    public function test_order_count_by_state(): void
    {
        // Create known number of orders
        $this->createOrderInState(PendingState::class);
        $this->createOrderInState(PendingState::class);
        $this->createOrderInState(ProcessingState::class);

        $totalCount = Order::count();

        $this->assertGreaterThanOrEqual(3, $totalCount);
    }

    /**
     * Test state labels are accessible
     */
    public function test_state_labels_accessible(): void
    {
        $pending = $this->createOrderInState(PendingState::class);
        $processing = $this->createOrderInState(ProcessingState::class);

        $this->assertEquals('Pending', $pending->state->getLabel());
        $this->assertEquals('Processing', $processing->state->getLabel());
    }

    // ===========================================
    // SCOPED QUERY TESTS (tenant-aware badge counts)
    // ===========================================

    /**
     * Test that query() method is available and returns StateTabs instance
     */
    public function test_query_method_returns_fluent_instance(): void
    {
        $tabs = StateTabs::make(Ticket::class)
            ->attribute('state')
            ->query(Ticket::query()->where('user_id', 1));

        $this->assertInstanceOf(StateTabs::class, $tabs);
    }

    /**
     * Test badge counts use scoped query when provided.
     * Simulates multi-tenant scoping: user_id acts as tenant_id.
     */
    public function test_badge_counts_respect_scoped_query(): void
    {
        $this->createTicketWorkflow();

        $user1 = $this->createTestUser(['email' => 'user1@test.com']);
        $user2 = $this->createTestUser(['email' => 'user2@test.com']);

        // User 1: 3 open tickets
        $this->createTestTicket(['state' => 'open', 'user_id' => $user1->id]);
        $this->createTestTicket(['state' => 'open', 'user_id' => $user1->id]);
        $this->createTestTicket(['state' => 'open', 'user_id' => $user1->id]);

        // User 2: 2 open tickets
        $this->createTestTicket(['state' => 'open', 'user_id' => $user2->id]);
        $this->createTestTicket(['state' => 'open', 'user_id' => $user2->id]);

        // User 1: 1 in_progress ticket
        $this->createTestTicket(['state' => 'in_progress', 'user_id' => $user1->id]);

        // Without scoped query: badge counts include ALL records
        $unscopedTabs = StateTabs::make(Ticket::class)
            ->attribute('state')
            ->badge(true)
            ->includeAll(true)
            ->toArray();

        $allTabUnscoped = $unscopedTabs[0]; // "All" tab
        $this->assertEquals(6, $this->extractBadgeCount($allTabUnscoped));

        // With scoped query for user 1: badge counts should only count user 1's records
        $scopedTabs = StateTabs::make(Ticket::class)
            ->attribute('state')
            ->query(Ticket::query()->where('user_id', $user1->id))
            ->badge(true)
            ->includeAll(true)
            ->toArray();

        $allTabScoped = $scopedTabs[0]; // "All" tab
        $this->assertEquals(4, $this->extractBadgeCount($allTabScoped));

        // Find the "Open" tab and verify its count
        $openTabScoped = $this->findTabByLabel($scopedTabs, 'Open');
        $this->assertNotNull($openTabScoped);
        $this->assertEquals(3, $this->extractBadgeCount($openTabScoped));

        // Find "In Progress" tab — should be 1 for user 1
        $inProgressScoped = $this->findTabByLabel($scopedTabs, 'In Progress');
        $this->assertNotNull($inProgressScoped);
        $this->assertEquals(1, $this->extractBadgeCount($inProgressScoped));
    }

    /**
     * Test that without query(), badge counts are unscoped (backward compatible)
     */
    public function test_without_query_badge_counts_are_unscoped(): void
    {
        $this->createTicketWorkflow();

        $user1 = $this->createTestUser(['email' => 'compat1@test.com']);
        $user2 = $this->createTestUser(['email' => 'compat2@test.com']);

        $this->createTestTicket(['state' => 'open', 'user_id' => $user1->id]);
        $this->createTestTicket(['state' => 'open', 'user_id' => $user2->id]);

        $tabs = StateTabs::make(Ticket::class)
            ->attribute('state')
            ->badge(true)
            ->includeAll(true)
            ->toArray();

        $allTab = $tabs[0];
        $this->assertEquals(2, $this->extractBadgeCount($allTab));
    }

    /**
     * Test scoped query with empty result set
     */
    public function test_scoped_query_with_no_matching_records(): void
    {
        $this->createTicketWorkflow();

        $this->createTestTicket(['state' => 'open', 'user_id' => 1]);

        $tabs = StateTabs::make(Ticket::class)
            ->attribute('state')
            ->query(Ticket::query()->where('user_id', 9999)) // non-existent user
            ->badge(true)
            ->includeAll(true)
            ->toArray();

        $allTab = $tabs[0];
        $this->assertEquals(0, $this->extractBadgeCount($allTab));
    }

    /**
     * Extract the badge count from a Tab instance.
     */
    private function extractBadgeCount(Tab $tab): int
    {
        $badge = $tab->getBadge();

        // Badge can be a string, int, or Closure
        if ($badge instanceof \Closure) {
            $badge = $badge();
        }

        return (int) $badge;
    }

    /**
     * Find a tab by its label in the tabs array.
     */
    private function findTabByLabel(array $tabs, string $label): ?Tab
    {
        foreach ($tabs as $tab) {
            if ($tab->getLabel() === $label) {
                return $tab;
            }
        }

        return null;
    }
}
