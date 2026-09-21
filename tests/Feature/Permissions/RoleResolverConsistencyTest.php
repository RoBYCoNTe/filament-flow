<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Permissions;

use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Infolists\Components\AssignmentSummaryEntry;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionPermission;
use RoBYCoNTe\FilamentFlow\Support\DefaultRoleResolver;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * Every authorisation path must resolve roles the same way: the configured role
 * resolver is the single source of truth, so a host that keeps roles outside the
 * user model (tenant/company roles, custom resolver) is honoured by transition
 * permissions and by the UI components too — not only by the state access rules.
 */
class RoleResolverConsistencyTest extends TestCase
{
    private Workflow $workflow;

    private WorkflowState $pending;

    private WorkflowState $processing;

    private WorkflowTransition $transition;

    protected function setUp(): void
    {
        parent::setUp();

        // States are used by name: the transition permissions are checked by the
        // database-first path, not by a code-first state machine.
        $this->workflow = $this->createTestWorkflow();
        $this->pending = $this->createWorkflowState($this->workflow, ['name' => 'pending']);
        $this->processing = $this->createWorkflowState($this->workflow, ['name' => 'processing']);
        $this->transition = $this->createWorkflowTransition($this->workflow, $this->pending, $this->processing);
    }

    /** A resolver that knows a role the user model itself does not expose. */
    private function useResolverGranting(string $role): void
    {
        $resolver = new class($role) extends DefaultRoleResolver
        {
            public function __construct(private readonly string $grantedRole) {}

            public function getRoles(Model $user): array
            {
                return array_merge(parent::getRoles($user), [$this->grantedRole]);
            }
        };

        config()->set('filament-flow.state_access.role_resolver', $resolver::class);
        app()->instance($resolver::class, $resolver);
    }

    /** @param array<string,mixed> $attributes */
    private function userWithoutRoles(array $attributes = []): User
    {
        return $this->createTestUser(array_merge(['email' => uniqid().'@test.com'], $attributes));
    }

    /** @param array<string,mixed> $data */
    private function createOrder(array $data = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-RR-'.uniqid(),
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
        ], $data));
    }

    private function orderRequiringRole(string $role): Order
    {
        WorkflowTransitionPermission::create([
            'transition_id' => $this->transition->id,
            'permission_type' => 'role',
            'permission_value' => $role,
            'require_all' => false,
        ]);

        return $this->createOrder(['state' => 'pending']);
    }

    // ── transition permissions ────────────────────────────────────────────────

    public function test_a_transition_permission_denies_a_user_without_the_role(): void
    {
        $order = $this->orderRequiringRole('approver');

        $this->assertFalse($order->asUser($this->userWithoutRoles())->canTransitionTo('processing'));
    }

    public function test_a_transition_permission_honours_the_configured_resolver(): void
    {
        $this->useResolverGranting('approver');

        $order = $this->orderRequiringRole('approver');

        $this->assertTrue($order->asUser($this->userWithoutRoles())->canTransitionTo('processing'));
    }

    public function test_a_resolver_without_the_role_still_denies_the_transition(): void
    {
        $this->useResolverGranting('someone_else');

        $order = $this->orderRequiringRole('approver');

        $this->assertFalse($order->asUser($this->userWithoutRoles())->canTransitionTo('processing'));
    }

    public function test_a_role_attribute_on_the_user_keeps_working(): void
    {
        $order = $this->orderRequiringRole('approver');

        $this->assertTrue($order->asUser($this->userWithoutRoles(['role' => 'approver']))->canTransitionTo('processing'));
    }

    // ── UI components ─────────────────────────────────────────────────────────

    public function test_the_assignment_summary_sees_a_super_admin_from_the_resolver(): void
    {
        $this->useResolverGranting('super_admin');
        $this->actingAs($this->userWithoutRoles());

        $this->assertTrue((new AssignmentSummaryEntry('summary'))->isCurrentUserSuperAdmin());
    }

    public function test_the_assignment_summary_denies_a_regular_user(): void
    {
        $this->actingAs($this->userWithoutRoles());

        $this->assertFalse((new AssignmentSummaryEntry('summary'))->isCurrentUserSuperAdmin());
    }
}
