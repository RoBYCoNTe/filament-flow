<?php

namespace RoBYCoNTe\FilamentFlow\Services;

use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Support\AccessRuleEvaluator;

/**
 * The context a field permission needs before it can be answered.
 *
 * Which workflow the record belongs to, which state it is in, which roles the actor has
 * (including the overrides of a record) and which fields are therefore read-only or hidden:
 * the same handful of questions, asked by every entry point of the service.
 */
trait ResolvesFieldPermissionContext
{
    /**
     * Get workflow for a record using getWorkflowTenantId() when available,
     * so that models with N scoped workflows (e.g. Application→scheme_id)
     * resolve the correct one instead of falling back to the Filament tenant.
     */
    protected function getWorkflowForRecord(Model $record): ?Workflow
    {
        $tenantId = method_exists($record, 'getWorkflowTenantId') ? $record->getWorkflowTenantId() : null;

        return Workflow::findForModel(get_class($record), 'state', $tenantId);
    }

    /**
     * Resolve model class and optional tenant ID from either a class name string
     * or a model instance (even unsaved). Used by creation and table-column methods.
     *
     * @return array{string, int|null}
     */
    private function resolveClassAndTenantId(string|Model $recordOrClass): array
    {
        if (is_string($recordOrClass)) {
            return [$recordOrClass, null];
        }

        $tenantId = method_exists($recordOrClass, 'getWorkflowTenantId')
            ? $recordOrClass->getWorkflowTenantId()
            : null;

        return [get_class($recordOrClass), $tenantId];
    }

    /**
     * Find workflow state from state value
     */
    protected function findWorkflowState(Workflow $workflow, $stateValue): ?WorkflowState
    {
        if (is_object($stateValue)) {
            $stateValue = get_class($stateValue);
        }

        $state = $workflow->states()
            ->where('class_name', $stateValue)
            ->first();

        if ($state) {
            return $state;
        }

        return $workflow->states()
            ->where('name', $stateValue)
            ->first();
    }

    /**
     * Resolve the user's effective roles: static roles + virtual roles (@owner, @assigned).
     *
     * @return array<string>
     */
    protected function resolveEffectiveRoles(Model $user, ?Model $record = null, bool $isCreation = false): array
    {
        $roles = $this->resolveUserRoles($user);

        // @owner: during creation the current user is always the owner
        if ($isCreation) {
            $roles[] = '@owner';
        } elseif ($record) {
            $ownerField = config('filament-flow.state_access.owner_field', 'user_id');
            if (isset($record->{$ownerField}) && $record->{$ownerField} == $user->getKey()) {
                $roles[] = '@owner';
            }
        }

        // @assigned / @assigned:type
        if ($record && method_exists($record, 'isAssignedTo') && $record->isAssignedTo($user)) {
            $roles[] = '@assigned';

            if (method_exists($record, 'getAssignmentTypesForUser')) {
                foreach ($record->getAssignmentTypesForUser($user) as $type) {
                    $roles[] = "@assigned:{$type}";
                }
            }
        }

        return $roles;
    }

    /**
     * Resolve the user's role names via the configured role resolver.
     *
     * @return array<string>
     */
    protected function resolveUserRoles(Model $user): array
    {
        // One place decides what a role is: the resolver configured for the package.
        // The state access rules and the transition permissions ask the same question
        // through the same seam, so a host that keeps roles outside the user model — a
        // custom resolver, a tenant role, a super admin of its own — is not treated
        // differently by the field permissions than it is by them.
        return array_values(array_map('strval', app(AccessRuleEvaluator::class)->getRoleResolver()->getRoles($user)));
    }

    /**
     * Apply matching role overrides on top of the base field config.
     * The last matching role wins (allows priority ordering in the DB).
     */
    protected function applyRoleOverrides(array $config, $overrides, array $userRoles): array
    {
        foreach ($overrides as $override) {
            if (! in_array($override->role_name, $userRoles, true)) {
                continue;
            }

            if ($override->visibility !== null) {
                $config['visible'] = $override->visibility === 'visible';
            }

            if ($override->mutability !== null) {
                $config['readonly'] = $override->mutability === 'readonly';
                $config['locked'] = $override->mutability === 'locked';
            }

            if ($override->is_required !== null) {
                $config['required'] = $override->is_required;
            }
        }

        return $config;
    }

    /**
     * Get all readonly fields for a record
     */
    public function getReadonlyFields(Model $record, ?Model $user = null): array
    {
        $permissions = $this->getFieldPermissions($record, $user);

        $readonly = [];
        foreach ($permissions as $fieldName => $config) {
            if ($config['readonly'] ?? false) {
                $readonly[] = $fieldName;
            }
        }

        return $readonly;
    }

    /**
     * Get all hidden fields for a record
     */
    public function getHiddenFields(Model $record, ?Model $user = null): array
    {
        $permissions = $this->getFieldPermissions($record, $user);

        $hidden = [];
        foreach ($permissions as $fieldName => $config) {
            if (! ($config['visible'] ?? true) || ($config['locked'] ?? false)) {
                $hidden[] = $fieldName;
            }
        }

        return $hidden;
    }
}
