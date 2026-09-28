<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoBYCoNTe\FilamentFlow\Actions\StateActionGroup;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateAccessRule;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateField;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionPermission;
use RoBYCoNTe\FilamentFlow\Services\NotificationService;
use RoBYCoNTe\FilamentFlow\Services\TransitionFormService;
use RoBYCoNTe\FilamentFlow\Services\WorkflowFieldPermissionsService;
use RoBYCoNTe\FilamentFlow\Services\WorkflowStateAccessService;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\ScopedOrder;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * Verifies that filament-flow resolves the correct workflow when a single
 * model type (ScopedOrder) has N workflows identified by scope_id
 * (the pattern used by Application→scheme_id in the platform).
 *
 * Every service that accepts a Model record MUST call getWorkflowTenantId()
 * to discriminate between workflows — never falling back to Filament's
 * getCurrentTenantId() (which returns the Filament panel tenant, not the
 * fine-grained scope).
 */
class MultiScopeWorkflowTest extends TestCase
{
    private WorkflowFieldPermissionsService $fieldPerms;

    private WorkflowStateAccessService $stateAccess;

    private TransitionFormService $transitionForm;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('test_scoped_orders')) {
            Schema::create('test_scoped_orders', function (Blueprint $table) {
                $table->id();
                $table->string('state')->default('draft');
                $table->unsignedBigInteger('scope_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
            });
        }

        $this->fieldPerms = app(WorkflowFieldPermissionsService::class);
        $this->stateAccess = app(WorkflowStateAccessService::class);
        $this->transitionForm = app(TransitionFormService::class);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function buildScopedWorkflow(int $scopeId, string $uniqueFieldName): array
    {
        $workflow = $this->createTestWorkflow([
            'name' => "scope-{$scopeId}-workflow",
            'model_type' => ScopedOrder::class,
            'state_column' => 'state',
            'tenant_id' => $scopeId,
        ]);

        $draft = $this->createWorkflowState($workflow, [
            'name' => 'draft',
            'label' => 'Draft',
            'is_initial' => true,
        ]);

        $submitted = $this->createWorkflowState($workflow, [
            'name' => 'submitted',
            'label' => 'Submitted',
        ]);

        $transition = $this->createWorkflowTransition($workflow, $draft, $submitted, [
            'name' => "submit-scope-{$scopeId}",
            'label' => "Submit (scope {$scopeId})",
        ]);

        // Each scope has a unique locked field so we can tell them apart
        WorkflowStateField::create([
            'state_id' => $draft->id,
            'field_name' => $uniqueFieldName,
            'visibility' => 'visible',
            'mutability' => 'locked',
            'is_required' => false,
            'sort_order' => 0,
        ]);

        return compact('workflow', 'draft', 'submitted', 'transition');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // StateActionGroup
    // ──────────────────────────────────────────────────────────────────────────

    public function test_state_action_group_resolves_actions_for_scope_1_only(): void
    {
        $this->buildScopedWorkflow(1, 'field_scope_1');
        $this->buildScopedWorkflow(2, 'field_scope_2');

        $record = ScopedOrder::create(['state' => 'draft', 'scope_id' => 1]);
        $actions = StateActionGroup::forDatabaseRecord($record, 'state');

        $names = array_map(fn ($a) => $a->getName(), $actions);

        $this->assertContains('transition-submit-scope-1', $names,
            'scope=1 record should see scope-1 transition');
        $this->assertNotContains('transition-submit-scope-2', $names,
            'scope=1 record must NOT see scope-2 transition');
    }

    public function test_state_action_group_resolves_actions_for_scope_2_only(): void
    {
        $this->buildScopedWorkflow(1, 'field_scope_1');
        $this->buildScopedWorkflow(2, 'field_scope_2');

        $record = ScopedOrder::create(['state' => 'draft', 'scope_id' => 2]);
        $actions = StateActionGroup::forDatabaseRecord($record, 'state');

        $names = array_map(fn ($a) => $a->getName(), $actions);

        $this->assertContains('transition-submit-scope-2', $names,
            'scope=2 record should see scope-2 transition');
        $this->assertNotContains('transition-submit-scope-1', $names,
            'scope=2 record must NOT see scope-1 transition');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // WorkflowFieldPermissionsService – getFieldPermissions
    // ──────────────────────────────────────────────────────────────────────────

    public function test_field_permissions_uses_scope_1_workflow(): void
    {
        $this->buildScopedWorkflow(1, 'field_scope_1');
        $this->buildScopedWorkflow(2, 'field_scope_2');

        $record = ScopedOrder::create(['state' => 'draft', 'scope_id' => 1]);
        $perms = $this->fieldPerms->getFieldPermissions($record);

        $this->assertArrayHasKey('field_scope_1', $perms,
            'scope=1 record must see field_scope_1 permission');
        $this->assertArrayNotHasKey('field_scope_2', $perms,
            'scope=1 record must NOT see field_scope_2 permission');
    }

    public function test_field_permissions_uses_scope_2_workflow(): void
    {
        $this->buildScopedWorkflow(1, 'field_scope_1');
        $this->buildScopedWorkflow(2, 'field_scope_2');

        $record = ScopedOrder::create(['state' => 'draft', 'scope_id' => 2]);
        $perms = $this->fieldPerms->getFieldPermissions($record);

        $this->assertArrayHasKey('field_scope_2', $perms,
            'scope=2 record must see field_scope_2 permission');
        $this->assertArrayNotHasKey('field_scope_1', $perms,
            'scope=2 record must NOT see field_scope_1 permission');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // WorkflowFieldPermissionsService – getCreationFieldPermissions (virtual record)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_creation_field_permissions_with_virtual_scope_1_record(): void
    {
        $this->buildScopedWorkflow(1, 'field_scope_1');
        $this->buildScopedWorkflow(2, 'field_scope_2');

        // Virtual record: unsaved, but scope_id already set
        $virtual = new ScopedOrder(['scope_id' => 1]);
        $perms = $this->fieldPerms->getCreationFieldPermissions($virtual);

        $this->assertArrayHasKey('field_scope_1', $perms,
            'virtual scope=1 record must see scope-1 creation permissions');
        $this->assertArrayNotHasKey('field_scope_2', $perms,
            'virtual scope=1 record must NOT see scope-2 creation permissions');
    }

    public function test_creation_field_permissions_with_virtual_scope_2_record(): void
    {
        $this->buildScopedWorkflow(1, 'field_scope_1');
        $this->buildScopedWorkflow(2, 'field_scope_2');

        $virtual = new ScopedOrder(['scope_id' => 2]);
        $perms = $this->fieldPerms->getCreationFieldPermissions($virtual);

        $this->assertArrayHasKey('field_scope_2', $perms,
            'virtual scope=2 record must see scope-2 creation permissions');
        $this->assertArrayNotHasKey('field_scope_1', $perms,
            'virtual scope=2 record must NOT see scope-1 creation permissions');
    }

    public function test_creation_field_permissions_legacy_string_class_still_works(): void
    {
        // Passing a class name string (legacy API) must not break
        $workflow = $this->createTestWorkflow([
            'name' => 'global-workflow',
            'model_type' => ScopedOrder::class,
            'state_column' => 'state',
            'tenant_id' => null,
        ]);
        $initial = $this->createWorkflowState($workflow, ['name' => 'draft', 'is_initial' => true]);
        WorkflowStateField::create([
            'state_id' => $initial->id,
            'field_name' => 'global_field',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => false,
            'sort_order' => 0,
        ]);

        $perms = $this->fieldPerms->getCreationFieldPermissions(ScopedOrder::class);

        $this->assertArrayHasKey('global_field', $perms,
            'legacy string class should fall back to global workflow');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // WorkflowStateAccessService – checkTransitionPermissions
    // ──────────────────────────────────────────────────────────────────────────

    public function test_transition_permissions_use_correct_scope_workflow(): void
    {
        ['workflow' => $wf1, 'draft' => $draft1, 'submitted' => $sub1, 'transition' => $t1] =
            $this->buildScopedWorkflow(1, 'f1');
        ['workflow' => $wf2, 'draft' => $draft2, 'submitted' => $sub2, 'transition' => $t2] =
            $this->buildScopedWorkflow(2, 'f2');

        // scope-1 transition requires role "istruttore_1"
        WorkflowTransitionPermission::create([
            'transition_id' => $t1->id,
            'permission_type' => 'role',
            'permission_value' => 'istruttore_1',
            'require_all' => false,
        ]);

        $user1 = $this->createTestUser(['email' => 'u1@test.com', 'role' => 'istruttore_1']);
        $user2 = $this->createTestUser(['email' => 'u2@test.com', 'role' => 'istruttore_2']);

        $record = ScopedOrder::create(['state' => 'draft', 'scope_id' => 1]);

        // user1 has the right role for scope-1 → allowed
        $this->assertTrue(
            $this->stateAccess->canTransition($record, $user1, 'submitted'),
            'user with correct role for scope-1 must be allowed to transition'
        );

        // user2 doesn't have scope-1 role → denied
        $this->assertFalse(
            $this->stateAccess->canTransition($record, $user2, 'submitted'),
            'user without correct role for scope-1 must be denied'
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // TransitionFormService – getTransitionConfig
    // ──────────────────────────────────────────────────────────────────────────

    public function test_transition_form_service_resolves_correct_scope_workflow(): void
    {
        $this->buildScopedWorkflow(1, 'f1');
        $this->buildScopedWorkflow(2, 'f2');

        $tenantId1 = 1;
        $tenantId2 = 2;

        $result1 = $this->transitionForm->getTransitionConfig(
            ScopedOrder::class, 'draft', 'submitted', null, $tenantId1
        );
        $result2 = $this->transitionForm->getTransitionConfig(
            ScopedOrder::class, 'draft', 'submitted', null, $tenantId2
        );

        $this->assertNotNull($result1, 'scope-1 transition must be found');
        $this->assertNotNull($result2, 'scope-2 transition must be found');
        $this->assertSame('submit-scope-1', $result1->name,
            'scope-1 tenantId must return scope-1 transition');
        $this->assertSame('submit-scope-2', $result2->name,
            'scope-2 tenantId must return scope-2 transition');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // NotificationService – getWorkflowForModel (via internal method indirectly)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_notification_service_get_workflow_for_model_uses_tenant_id(): void
    {
        $this->buildScopedWorkflow(1, 'f1');
        $this->buildScopedWorkflow(2, 'f2');

        // Access protected method via reflection to test in isolation
        $service = app(NotificationService::class);
        $method = new \ReflectionMethod($service, 'getWorkflowForModel');
        $method->setAccessible(true);

        $record1 = ScopedOrder::create(['state' => 'draft', 'scope_id' => 1]);
        $record2 = ScopedOrder::create(['state' => 'draft', 'scope_id' => 2]);

        $wf1 = $method->invoke($service, $record1, 'state');
        $wf2 = $method->invoke($service, $record2, 'state');

        $this->assertNotNull($wf1, 'scope-1 record must resolve a workflow');
        $this->assertNotNull($wf2, 'scope-2 record must resolve a workflow');
        $this->assertSame('scope-1-workflow', $wf1->name,
            'scope-1 record must resolve scope-1 workflow');
        $this->assertSame('scope-2-workflow', $wf2->name,
            'scope-2 record must resolve scope-2 workflow');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // WorkflowStateAccessService – scopeAccessible
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * The scope is what an application list is built on: it has to apply the rules of the
     * workflow of the scope it is asked about. Without knowing that scope, no workflow is
     * found for a per-owner workflow — and the query comes back unfiltered, silently
     * showing records the state rules never allowed.
     */
    public function test_the_scope_applies_the_rules_of_the_workflow_of_the_given_scope(): void
    {
        ['draft' => $draft] = $this->buildScopedWorkflow(1, 'field_scope_1');

        $this->createAccessRule($draft->id, 'view', 'role:reviewer');

        ScopedOrder::create(['state' => 'draft', 'scope_id' => 1]);

        $outsider = $this->createTestUser();

        $visible = $this->stateAccess
            ->scopeAccessible(ScopedOrder::query(), $outsider, 'view', 1)
            ->count();

        $this->assertSame(0, $visible,
            'Whoever misses the role the view rule asks for must not see the row.');
    }

    /** And the same for editing: the scope answers for the access type it was asked about. */
    public function test_the_scope_applies_the_edit_rules_of_the_given_scope(): void
    {
        ['draft' => $draft] = $this->buildScopedWorkflow(1, 'field_scope_1');

        $this->createAccessRule($draft->id, 'edit', 'role:reviewer');

        ScopedOrder::create(['state' => 'draft', 'scope_id' => 1]);

        $outsider = $this->createTestUser();

        $editable = $this->stateAccess
            ->scopeAccessible(ScopedOrder::query(), $outsider, 'edit', 1)
            ->count();

        $this->assertSame(0, $editable,
            'The edit rules of the given scope hold in the query too.');
    }

    /**
     * The boundary of the scope, documented: the query narrows by **state** — it includes the
     * states where the rule exists — while the exact ownership (`@owner`) and the assignment
     * are verified **on the row**, with `canBeViewedBy()`. Whoever builds a list does both: the
     * scope for the query, the per-row check for certainty.
     */
    public function test_the_scope_narrows_by_state_and_the_record_check_decides_the_owner(): void
    {
        ['draft' => $draft] = $this->buildScopedWorkflow(1, 'field_scope_1');

        $this->createAccessRule($draft->id, 'view', '@owner');

        $owner = $this->createTestUser();
        $mine = ScopedOrder::create(['state' => 'draft', 'scope_id' => 1]);
        $mine->forceFill(['user_id' => $owner->getKey()])->save();

        $theirs = ScopedOrder::create(['state' => 'draft', 'scope_id' => 1]);
        $theirs->forceFill(['user_id' => $this->createTestUser(['email' => 'other@example.com', 'name' => 'Other User'])->getKey()])->save();

        // The query: the state is allowed, so both rows pass.
        $this->assertSame(
            2,
            $this->stateAccess->scopeAccessible(ScopedOrder::query(), $owner, 'view', 1)->count(),
            'The scope is by state: it does not know the ownership of a single row.'
        );

        // The row: the ownership decides (the check the trait of the model exposes).
        $this->assertTrue($this->stateAccess->canView($mine->refresh(), $owner), 'One\'s own row is seen.');
        $this->assertFalse($this->stateAccess->canView($theirs->refresh(), $owner), 'Somebody else\'s is not.');
    }

    private function createAccessRule(int $stateId, string $accessType, string $rule): void
    {
        WorkflowStateAccessRule::create([
            'state_id' => $stateId,
            'access_type' => $accessType,
            'rule' => $rule,
            'operator' => WorkflowStateAccessRule::OPERATOR_OR,
            'priority' => 0,
            'is_active' => true,
        ]);
    }
}
