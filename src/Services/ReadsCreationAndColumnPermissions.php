<?php

namespace RoBYCoNTe\FilamentFlow\Services;

use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Support\WorkflowCacheManager;

/**
 * The fields of an application that does not exist yet, and the columns of a table.
 *
 * Both answer the same question as the field permissions of a state, but for a different
 * subject: the initial state of a workflow, and the columns an office reads in a list.
 */
trait ReadsCreationAndColumnPermissions
{
    /**
     * Get field permissions for record creation, based on the initial state.
     * Accepts either a class name string (legacy/global fallback) or a Model
     * instance — including unsaved "virtual" records — so that callers can
     * pass a pre-filled model (e.g. new Application(['scheme_id' => 3])) to
     * resolve the correct scoped workflow without persisting anything.
     */
    public function getCreationFieldPermissions(string|Model $recordOrClass, ?Model $user = null): array
    {
        [$modelClass, $tenantId] = $this->resolveClassAndTenantId($recordOrClass);
        $workflow = Workflow::findForModel($modelClass, 'state', $tenantId);

        if (! $workflow) {
            return [];
        }

        $initialState = $workflow->initialState();

        if (! $initialState) {
            return [];
        }

        $effectiveRoles = $user ? $this->resolveEffectiveRoles($user, null, isCreation: true) : [];
        $rolesHash = md5(implode(',', $effectiveRoles));

        $cache = new WorkflowCacheManager;

        if (config('filament-flow.cache.enabled', true)) {
            $cacheKey = "creation_perms:{$workflow->id}:{$initialState->id}:{$rolesHash}";
            $ttl = config('filament-flow.cache.safety_ttl', 86400);

            return $cache->remember($cacheKey, $ttl, function () use ($initialState, $user, $effectiveRoles) {
                return $this->buildCreationFieldPermissions($initialState, $user, $effectiveRoles);
            }, [$cache->fieldsTag($workflow->id)]);
        }

        return $this->buildCreationFieldPermissions($initialState, $user, $effectiveRoles);
    }

    protected function buildCreationFieldPermissions(WorkflowState $initialState, ?Model $user, array $effectiveRoles): array
    {
        $fieldPermissions = $initialState->fields()->with('roleOverrides')->get();

        $config = [];

        foreach ($fieldPermissions as $field) {
            $fieldConfig = [
                'visible' => $field->visibility === 'visible',
                'readonly' => $field->mutability === 'readonly',
                'locked' => $field->mutability === 'locked',
                'required' => $field->is_required,
                'validation' => $field->validation_rules,
            ];

            if ($user && $effectiveRoles) {
                $fieldConfig = $this->applyRoleOverrides($fieldConfig, $field->roleOverrides, $effectiveRoles);
            }

            $config[$field->field_name] = $fieldConfig;
        }

        return $config;
    }

    /**
     * Get table column permissions aggregated across all workflow states.
     * A column is visible if it is visible in at least one state for the user's roles.
     * Accepts either a class name string (legacy) or a Model instance for scoped lookup.
     */
    public function getTableColumnPermissions(string|Model $recordOrClass, ?Model $user = null): array
    {
        [$modelClass, $tenantId] = $this->resolveClassAndTenantId($recordOrClass);
        $workflow = Workflow::findForModel($modelClass, 'state', $tenantId);

        if (! $workflow) {
            return [];
        }

        $userRoles = $user ? $this->resolveUserRoles($user) : [];
        $rolesHash = md5(implode(',', $userRoles));

        $cache = new WorkflowCacheManager;

        if (config('filament-flow.cache.enabled', true)) {
            $cacheKey = "table_perms:{$workflow->id}:{$rolesHash}";
            $ttl = config('filament-flow.cache.safety_ttl', 86400);

            return $cache->remember($cacheKey, $ttl, function () use ($workflow, $user, $userRoles) {
                return $this->buildTableColumnPermissions($workflow, $user, $userRoles);
            }, [$cache->fieldsTag($workflow->id)]);
        }

        return $this->buildTableColumnPermissions($workflow, $user, $userRoles);
    }

    protected function buildTableColumnPermissions(Workflow $workflow, ?Model $user, array $userRoles): array
    {
        $states = $workflow->states()->with('fields.roleOverrides')->get();

        // Collect per-field visibility across all states
        $fieldVisibility = [];

        foreach ($states as $state) {
            foreach ($state->fields as $field) {
                $visible = $field->visibility === 'visible';
                $locked = $field->mutability === 'locked';

                // Apply role overrides
                if ($user && $userRoles) {
                    foreach ($field->roleOverrides as $override) {
                        if (in_array($override->role_name, $userRoles, true)) {
                            if ($override->visibility !== null) {
                                $visible = $override->visibility === 'visible';
                            }
                            if ($override->mutability !== null) {
                                $locked = $override->mutability === 'locked';
                            }
                        }
                    }
                }

                $effectiveVisible = $visible && ! $locked;
                $name = $field->field_name;

                // Visible if visible in at least one state
                if (! isset($fieldVisibility[$name])) {
                    $fieldVisibility[$name] = false;
                }
                if ($effectiveVisible) {
                    $fieldVisibility[$name] = true;
                }
            }
        }

        $config = [];
        foreach ($fieldVisibility as $name => $visible) {
            $config[$name] = ['visible' => $visible];
        }

        return $config;
    }
}
