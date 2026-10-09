<?php

namespace RoBYCoNTe\FilamentFlow\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use RoBYCoNTe\FilamentFlow\Contracts\ResolvesRequestScope;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Support\RequestScopeOverlay;
use RoBYCoNTe\FilamentFlow\Support\WorkflowCacheManager;

/**
 * What a state says about its own fields.
 *
 * The rules of a state are read once per state and per role, remembering the result while
 * the request lasts; a path is then resolved against them — the field itself, its parent, or
 * the column of a row — with the overrides of the role applied on the way.
 */
trait ReadsFieldPermissions
{
    /**
     * Get field permissions for a specific record based on its current state.
     * When $user is provided, role overrides are applied on top of the base config.
     */
    public function getFieldPermissions(Model $record, ?Model $user = null): array
    {
        $permissions = $this->getStateFieldPermissions($record, $user);
        $scope = $this->requestScopeFor($record, $user);

        return $scope === null ? $permissions : RequestScopeOverlay::applyToMap($permissions, $scope);
    }

    /**
     * What the state says, the same for every record in it: the part that is cached.
     */
    private function getStateFieldPermissions(Model $record, ?Model $user): array
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
            $cache = new WorkflowCacheManager;
            $cacheKey = "perms:{$workflow->id}:{$state->id}:{$rolesHash}";
            $ttl = config('filament-flow.cache.safety_ttl', 86400);

            return $cache->remember($cacheKey, $ttl, function () use ($state, $user, $effectiveRoles) {
                return $this->buildFieldPermissions($state, $user, $effectiveRoles);
            }, [$cache->fieldsTag($workflow->id)]);
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
     * Resolve the effective permission for a field path, honouring nested paths.
     *
     * Matching rules:
     * - a rule on an ancestor path also applies to its descendants (`costs` -> `costs.amount`);
     * - the most specific rule wins attribute by attribute (`costs.amount` beats
     *   `costs`); every rule always defines all attributes, so a specific rule
     *   must re-declare `required()` when it needs it;
     * - role overrides are applied after every base rule of the chain, so a role
     *   override on an ancestor wins over a more specific base rule (an admin can
     *   be granted access to a nested column whose parent is locked).
     *
     * Returns null when no rule matches: callers treat null as "unconfigured"
     * (visible, editable), never as "denied".
     *
     * @return array{visible:bool,readonly:bool,locked:bool,required:bool,validation:array<int,string>|null}|null
     */
    public function permissionFor(Model $record, string $path, ?Model $user = null): ?array
    {
        $workflow = $this->getWorkflowForRecord($record);

        if (! $workflow) {
            return null;
        }

        $state = $this->findWorkflowState($workflow, $record->{$workflow->state_column});

        if (! $state) {
            return null;
        }

        $effectiveRoles = $user ? $this->resolveEffectiveRoles($user, $record) : [];
        $rules = $this->ruleMapForState($workflow, $state, $effectiveRoles);

        $resolved = $rules === [] ? null : $this->resolvePath($rules, $path);
        $scope = $this->requestScopeFor($record, $user, $effectiveRoles);

        return $scope === null ? $resolved : RequestScopeOverlay::applyToResolved($resolved, $path, $scope);
    }

    /**
     * What a state says about a path for a given set of roles, whoever the record is today.
     *
     * Nothing is laid over it: it is the state's own answer, for a state the record is not in
     * yet — what the requester reads to offer only what the answering side will see there.
     *
     * @param  array<int,string>  $effectiveRoles
     * @return array{visible:bool,readonly:bool,locked:bool,required:bool,validation:array<int,string>|null}|null
     */
    public function permissionForStateRoles(Model $record, string $stateValue, string $path, array $effectiveRoles): ?array
    {
        $workflow = $this->getWorkflowForRecord($record);
        $state = $workflow ? $this->findWorkflowState($workflow, $stateValue) : null;

        if (! $workflow || ! $state) {
            return null;
        }

        $rules = $this->ruleMapForState($workflow, $state, $effectiveRoles);

        return $rules === [] ? null : $this->resolvePath($rules, $path);
    }

    /**
     * The scope an open request puts over this record, laid over the cached rules and never
     * inside them.
     *
     * @return array{mode:string,paths:list<string>,whitelist:list<string>|null,universe:list<string>}|null
     */
    protected function requestScopeFor(Model $record, ?Model $user, ?array $effectiveRoles = null): ?array
    {
        if ($user === null || ! app()->bound(ResolvesRequestScope::class)) {
            return null;
        }

        $resolver = app(ResolvesRequestScope::class);

        if (! $resolver->declaresScope($record)) {
            return null;
        }

        return $resolver->scopeFor($record, $user, $effectiveRoles ?? $this->resolveEffectiveRoles($user, $record));
    }

    /**
     * Merge the rules matching a path into a single permission set.
     *
     * @param  array<string,array<string,mixed>>  $rules  keyed by configured field name
     * @return array<string,mixed>|null
     */
    public function resolvePath(array $rules, string $path): ?array
    {
        $chain = $this->pathChain($path);
        $resolved = null;

        foreach ($chain as $candidate) {
            if (isset($rules[$candidate]['base'])) {
                $resolved = $resolved === null
                    ? $rules[$candidate]['base']
                    : array_replace($resolved, $rules[$candidate]['base']);
            }
        }

        foreach ($chain as $candidate) {
            foreach ($rules[$candidate]['overrides'] ?? [] as $patch) {
                if ($resolved === null) {
                    continue;
                }

                $resolved = array_replace($resolved, $patch);
            }
        }

        return $resolved;
    }

    /**
     * Ancestors of a dotted path, least specific first: `costs.amount` ->
     * `['costs', 'costs.amount']`.
     *
     * @return array<int,string>
     */
    private function pathChain(string $path): array
    {
        $segments = explode('.', $path);
        $chain = [];

        for ($i = 1; $i <= count($segments); $i++) {
            $chain[] = implode('.', array_slice($segments, 0, $i));
        }

        return $chain;
    }

    /**
     * Base config and role override patches per configured field name.
     *
     * @param  array<int,string>  $effectiveRoles
     * @return array<string,array{base:array<string,mixed>,overrides:array<int,array<string,mixed>>}>
     */
    protected function ruleMapForState(Workflow $workflow, WorkflowState $state, array $effectiveRoles): array
    {
        return $this->rememberForWorkflow(
            "rules:{$workflow->id}:{$state->id}:".md5(implode(',', $effectiveRoles)),
            $workflow->id,
            fn (): array => $this->buildRuleMap($state, $effectiveRoles),
        );
    }

    /**
     * @param  array<int,string>  $effectiveRoles
     * @return array<string,array{base:array<string,mixed>,overrides:array<int,array<string,mixed>>}>
     */
    protected function buildRuleMap(WorkflowState $state, array $effectiveRoles): array
    {
        $map = [];

        foreach ($state->fields()->with('roleOverrides')->get() as $field) {
            $overrides = [];

            foreach ($field->roleOverrides as $override) {
                if (! in_array($override->role_name, $effectiveRoles, true)) {
                    continue;
                }

                $patch = [];

                if ($override->visibility !== null) {
                    $patch['visible'] = $override->visibility === 'visible';
                }

                if ($override->mutability !== null) {
                    $patch['readonly'] = $override->mutability === 'readonly';
                    $patch['locked'] = $override->mutability === 'locked';
                }

                if ($override->is_required !== null) {
                    $patch['required'] = (bool) $override->is_required;
                }

                if ($patch !== []) {
                    $overrides[] = $patch;
                }
            }

            $map[$field->field_name] = [
                'base' => [
                    'visible' => $field->visibility === 'visible',
                    'readonly' => $field->mutability === 'readonly',
                    'locked' => $field->mutability === 'locked',
                    'required' => (bool) $field->is_required,
                    'validation' => $field->validation_rules,
                ],
                'overrides' => $overrides,
            ];
        }

        return $map;
    }

    /**
     * Cache a value under the workflow field-permission tag.
     *
     * @template TValue
     *
     * @param  callable():TValue  $callback
     * @return TValue
     */
    protected function rememberForWorkflow(string $key, int $workflowId, callable $callback): mixed
    {
        if (! config('filament-flow.cache.enabled', true)) {
            return $callback();
        }

        $cache = new WorkflowCacheManager;
        $ttl = config('filament-flow.cache.safety_ttl', 86400);

        return $cache->remember($key, $ttl, $callback, [$cache->fieldsTag($workflowId)]);
    }
}
