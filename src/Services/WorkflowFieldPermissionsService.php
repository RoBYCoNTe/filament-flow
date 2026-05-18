<?php

namespace RoBYCoNTe\FilamentFlow\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;

class WorkflowFieldPermissionsService
{
    /**
     * Get field permissions for a specific record based on its current state.
     * When $user is provided, role overrides are applied on top of the base config.
     */
    public function getFieldPermissions(Model $record, ?Model $user = null): array
    {
        $workflow = $this->getWorkflowForRecord($record);

        if (! $workflow) {
            return [];
        }

        $stateColumn = $workflow->state_column;
        $currentStateValue = $record->$stateColumn;

        $state = $this->findWorkflowState($workflow, $currentStateValue);

        if (! $state) {
            return [];
        }

        // Cache the base field permissions (without role overrides applied)
        $effectiveRoles = $user ? $this->resolveEffectiveRoles($user, $record) : [];
        $rolesHash = md5(implode(',', $effectiveRoles));

        if (config('filament-flow.cache.enabled', true)) {
            $prefix = config('filament-flow.cache.prefix', 'filament-flow');
            $cacheKey = "{$prefix}:perms:{$workflow->id}:{$state->id}:{$rolesHash}";
            $ttl = min(config('filament-flow.cache.ttl', 300), 60);
            $store = config('filament-flow.cache.store');

            return Cache::store($store)->remember($cacheKey, $ttl, function () use ($state, $user, $effectiveRoles) {
                return $this->buildFieldPermissions($state, $user, $effectiveRoles);
            });
        }

        return $this->buildFieldPermissions($state, $user, $effectiveRoles);
    }

    /**
     * Build field permissions config for a given state.
     */
    protected function buildFieldPermissions(WorkflowState $state, ?Model $user, array $effectiveRoles): array
    {
        $fieldPermissions = $state->fields()->with('roleOverrides')->get();
        $config = [];

        foreach ($fieldPermissions as $field) {
            $fieldConfig = [
                'visible' => $field->visibility === 'visible',
                'readonly' => $field->mutability === 'readonly',
                'locked' => $field->mutability === 'locked',
                'required' => $field->is_required,
                'validation' => $field->validation_rules,
            ];

            // Apply role overrides when a user is provided
            if ($user && $effectiveRoles) {
                $fieldConfig = $this->applyRoleOverrides($fieldConfig, $field->roleOverrides, $effectiveRoles);
            }

            $config[$field->field_name] = $fieldConfig;
        }

        return $config;
    }

    /**
     * Get field permissions for record creation, based on the initial state.
     * Used when no record exists yet — looks up the initial state and applies
     * the same field permission + role override logic as editing.
     */
    public function getCreationFieldPermissions(string $modelClass, ?Model $user = null): array
    {
        $workflow = Workflow::findForModel($modelClass);

        if (! $workflow) {
            return [];
        }

        $initialState = $workflow->initialState();

        if (! $initialState) {
            return [];
        }

        $fieldPermissions = $initialState->fields()->with('roleOverrides')->get();

        // During creation the user is always the owner (no record yet, no assignments)
        $effectiveRoles = $user ? $this->resolveEffectiveRoles($user, null, isCreation: true) : [];

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
     */
    public function getTableColumnPermissions(string $modelClass, ?Model $user = null): array
    {
        $workflow = Workflow::findForModel($modelClass);

        if (! $workflow) {
            return [];
        }

        $states = $workflow->states()->with('fields.roleOverrides')->get();
        $userRoles = $user ? $this->resolveUserRoles($user) : [];

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

    /**
     * Get workflow for a record (with tenant fallback support)
     */
    protected function getWorkflowForRecord(Model $record): ?Workflow
    {
        return Workflow::findForModel(get_class($record));
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
        $resolverClass = config('filament-flow.state_access.role_resolver');

        if ($resolverClass && class_exists($resolverClass)) {
            return app($resolverClass)->getRoles($user);
        }

        // Spatie Permission
        if (method_exists($user, 'getRoleNames')) {
            return $user->getRoleNames()->toArray();
        }

        // Fallback: a "role" attribute on the user
        if (isset($user->role)) {
            return (array) $user->role;
        }

        return [];
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
