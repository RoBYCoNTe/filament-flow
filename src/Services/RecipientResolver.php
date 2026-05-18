<?php

namespace RoBYCoNTe\FilamentFlow\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotificationRecipient;
use RoBYCoNTe\FilamentFlow\Models\WorkflowUserInvolvement;

/**
 * Resolves notification recipients based on configuration.
 *
 * This service takes a WorkflowNotificationRecipient configuration
 * and resolves it to actual User models that should receive the notification.
 */
class RecipientResolver
{
    /**
     * Resolve recipients for a notification.
     *
     * @param  Model  $record  The record that triggered the notification
     * @return Collection Collection of User models
     */
    public function resolve(WorkflowNotificationRecipient $recipientConfig, Model $record, array $context = []): Collection
    {
        $type = $recipientConfig->recipient_type;
        $config = $recipientConfig->recipient_config ?? [];

        return match ($type) {
            'role' => $this->resolveByRole($config),
            'user' => $this->resolveByUser($config),
            'trigger_user' => $this->resolveTriggerUser($context),
            'assigned_users' => $this->resolveAssignedUsers($record, $config),
            'record_owner' => $this->resolveRecordOwner($record, $config),
            'state_actors' => $this->resolveStateActors($record, $config),
            'all_involved' => $this->resolveAllInvolved($record),
            'involvement_type' => $this->resolveByInvolvementType($record, $config),
            'custom_field' => $this->resolveCustomField($record, $config),
            'custom_query' => $this->resolveCustomQuery($record, $config),
            'custom_class' => $this->resolveCustomClass($record, $config),
            default => collect(),
        };
    }

    /**
     * Resolve all recipients for a notification (multiple recipient configs).
     *
     * @param  Collection  $recipientConfigs  Collection of WorkflowNotificationRecipient
     * @return Collection Unique collection of User models
     */
    public function resolveAll(Collection $recipientConfigs, Model $record, array $context = []): Collection
    {
        return $recipientConfigs
            ->sortBy('sort_order')
            ->flatMap(fn ($config) => $this->resolve($config, $record, $context))
            ->unique('id')
            ->values();
    }

    protected function resolveTriggerUser(array $context): Collection
    {
        if ($model = $context['assignee_model'] ?? null) {
            return collect([$model]);
        }

        $userId = $context['assigned_user_id'] ?? null;

        if (! $userId) {
            return collect();
        }

        $user = $this->getUserModel()::find($userId);

        return $user ? collect([$user]) : collect();
    }

    /**
     * Resolve users by role.
     * Config: ['roles' => ['admin', 'manager']]
     *
     * @noinspection PhpUndefinedMethodInspection
     */
    protected function resolveByRole(array $config): Collection
    {
        $roles = $config['roles'] ?? [];

        if (empty($roles)) {
            return collect();
        }

        $userModel = $this->getUserModel();

        // Check if using Spatie Permission (defines scopeRole, callable as ::role())
        if (method_exists($userModel, 'scopeRole')) {
            return $userModel::role($roles)->get();
        }

        // Fallback: check 'role' column directly
        return $userModel::whereIn('role', $roles)->get();
    }

    /**
     * Resolve specific users by ID.
     * Config: ['user_ids' => [1, 2, 3]]
     */
    protected function resolveByUser(array $config): Collection
    {
        $userIds = $config['user_ids'] ?? [];

        if (empty($userIds)) {
            return collect();
        }

        return $this->getUserModel()::whereIn('id', $userIds)->get();
    }

    /**
     * Resolve users assigned to the record via WorkflowAssignment.
     * Config: ['types' => ['primary', 'secondary']] (optional, all if empty)
     */
    protected function resolveAssignedUsers(Model $record, array $config): Collection
    {
        if (! method_exists($record, 'getAssignedUsers')) {
            return collect();
        }

        $types = $config['types'] ?? null;

        return $record->getAssignedUsers($types);
    }

    /**
     * Resolve the record owner.
     * Config: ['owner_field' => 'user_id'] (optional, uses config default)
     */
    protected function resolveRecordOwner(Model $record, array $config): Collection
    {
        $ownerField = $config['owner_field']
            ?? config('filament-flow.state_access.owner_field', 'user_id');

        $ownerId = $record->{$ownerField};

        if (! $ownerId) {
            return collect();
        }

        $owner = $this->getUserModel()::find($ownerId);

        return $owner ? collect([$owner]) : collect();
    }

    /**
     * Resolve users who performed transitions in specific states.
     * Config: ['states' => ['pending', 'processing']] (optional, all if empty)
     */
    protected function resolveStateActors(Model $record, array $config): Collection
    {
        $states = $config['states'] ?? [];

        $query = DB::table('workflow_state_transitions')
            ->where('transitionable_type', get_class($record))
            ->where('transitionable_id', $record->getKey())
            ->whereNotNull('user_id');

        if (! empty($states)) {
            $query->where(function ($q) use ($states) {
                $q->whereIn('from_state', $states)
                    ->orWhereIn('to_state', $states);
            });
        }

        $userIds = $query->pluck('user_id')->unique()->toArray();

        if (empty($userIds)) {
            return collect();
        }

        return $this->getUserModel()::whereIn('id', $userIds)->get();
    }

    /**
     * Resolve all users who have ever been involved with the record.
     * Collects IDs from all sources, then performs a single user query.
     */
    protected function resolveAllInvolved(Model $record): Collection
    {
        $userIds = collect();
        $recordClass = get_class($record);
        $recordId = $record->getKey();

        // From assignments
        if (method_exists($record, 'getAssignedUserIds')) {
            $userIds = $userIds->merge($record->getAssignedUserIds());
        }

        // From transition history + user involvement in a single union query
        $transitionUserIds = DB::table('workflow_state_transitions')
            ->where('transitionable_type', $recordClass)
            ->where('transitionable_id', $recordId)
            ->whereNotNull('user_id')
            ->pluck('user_id');

        $involvementUserIds = WorkflowUserInvolvement::where('model_type', $recordClass)
            ->where('model_id', $recordId)
            ->pluck('user_id');

        $userIds = $userIds->merge($transitionUserIds)->merge($involvementUserIds);

        // Record owner
        $ownerField = config('filament-flow.state_access.owner_field', 'user_id');
        if ($record->{$ownerField}) {
            $userIds->push($record->{$ownerField});
        }

        $uniqueIds = $userIds->unique()->filter()->toArray();

        if (empty($uniqueIds)) {
            return collect();
        }

        return $this->getUserModel()::whereIn('id', $uniqueIds)->get();
    }

    /**
     * Resolve users by involvement type.
     * Config: ['involvement_type' => 'reviewer']
     */
    protected function resolveByInvolvementType(Model $record, array $config): Collection
    {
        $involvementType = $config['involvement_type'] ?? null;

        if (! $involvementType) {
            return collect();
        }

        $userIds = WorkflowUserInvolvement::where('model_type', get_class($record))
            ->where('model_id', $record->getKey())
            ->where('involvement_type', $involvementType)
            ->pluck('user_id')
            ->toArray();

        if (empty($userIds)) {
            return collect();
        }

        return $this->getUserModel()::whereIn('id', $userIds)->get();
    }

    /**
     * Resolve from a custom field on the record.
     * Config: ['field' => 'approver_id'] or ['field' => 'team_members'] (for arrays)
     */
    protected function resolveCustomField(Model $record, array $config): Collection
    {
        $field = $config['field'] ?? null;

        if (! $field || ! isset($record->{$field})) {
            return collect();
        }

        $value = $record->{$field};

        // Handle array of IDs
        if (is_array($value)) {
            return $this->getUserModel()::whereIn('id', $value)->get();
        }

        // Handle single ID
        if (is_numeric($value)) {
            $user = $this->getUserModel()::find($value);

            return $user ? collect([$user]) : collect();
        }

        // Handle relationship
        if ($value instanceof Model) {
            return collect([$value]);
        }

        if ($value instanceof Collection) {
            return $value;
        }

        return collect();
    }

    /**
     * Resolve from a custom database query.
     * Config: ['query' => 'SELECT id FROM users WHERE department_id = :department_id', 'bindings' => ['department_id' => 'department_id']]
     */
    protected function resolveCustomQuery(Model $record, array $config): Collection
    {
        $query = $config['query'] ?? null;
        $bindingsMap = $config['bindings'] ?? [];

        if (! $query) {
            return collect();
        }

        // Replace bindings from record
        $bindings = [];
        foreach ($bindingsMap as $placeholder => $recordField) {
            $bindings[$placeholder] = $record->{$recordField};
        }

        try {
            $results = DB::select($query, $bindings);
            $userIds = collect($results)->pluck('id')->toArray();

            if (empty($userIds)) {
                return collect();
            }

            return $this->getUserModel()::whereIn('id', $userIds)->get();
        } catch (\Exception $e) {
            report($e);

            return collect();
        }
    }

    /**
     * Resolve from a custom class.
     * Config: ['class' => 'App\\Services\\CustomRecipientResolver', 'method' => 'resolve']
     */
    protected function resolveCustomClass(Model $record, array $config): Collection
    {
        $class = $config['class'] ?? null;
        $method = $config['method'] ?? 'resolve';

        if (! $class || ! class_exists($class)) {
            return collect();
        }

        try {
            $instance = app($class);

            if (! method_exists($instance, $method)) {
                return collect();
            }

            $result = $instance->{$method}($record, $config);

            if ($result instanceof Collection) {
                return $result;
            }

            if (is_array($result)) {
                return collect($result);
            }

            return collect();
        } catch (\Exception $e) {
            report($e);

            return collect();
        }
    }

    /**
     * Get the user model class.
     */
    protected function getUserModel(): string
    {
        return config('filament-flow.user_model')
            ?? config('auth.providers.users.model')
            ?? 'App\\Models\\User';
    }

    /**
     * Resolve recipients from code-first format.
     *
     * Supported formats:
     * - '@owner' - Record owner
     * - '@assigned' - Assigned users
     * - '@all_involved' - All involved users
     * - 'role:admin' or 'role:admin,manager' - Users with specific role(s)
     * - 'user:1' or 'user:1,2,3' - Specific user IDs
     * - 'involvement:reviewer' - Users involved as specific type
     * - Callable: fn($record) => collect([...]) - Custom resolver
     */
    public function resolveCodeFirst(array|callable $recipients, Model $record): Collection
    {
        // Handle callable
        if (is_callable($recipients)) {
            $result = $recipients($record);
            if ($result instanceof Collection) {
                return $result;
            }

            return collect(is_array($result) ? $result : [$result])->filter();
        }

        $allRecipients = collect();

        foreach ($recipients as $recipient) {
            // Handle callable in array
            if (is_callable($recipient)) {
                $result = $recipient($record);
                if ($result instanceof Collection) {
                    $allRecipients = $allRecipients->merge($result);
                } else {
                    $resolved = is_array($result) ? $result : [$result];
                    $allRecipients = $allRecipients->merge(collect($resolved)->filter());
                }

                continue;
            }

            if (! is_string($recipient)) {
                continue;
            }

            // Parse the recipient string
            $resolved = $this->resolveCodeFirstRecipient($recipient, $record);
            $allRecipients = $allRecipients->merge($resolved);
        }

        return $allRecipients->unique('id')->values();
    }

    /**
     * Resolve a single code-first recipient string.
     */
    protected function resolveCodeFirstRecipient(string $recipient, Model $record): Collection
    {
        // Handle @ prefixed shortcuts
        if (str_starts_with($recipient, '@')) {
            return match (strtolower($recipient)) {
                '@owner' => $this->resolveRecordOwner($record, []),
                '@assigned' => $this->resolveAssignedUsers($record, []),
                '@all_involved' => $this->resolveAllInvolved($record),
                default => collect(),
            };
        }

        // Handle prefixed formats (role:, user:, involvement:)
        if (str_contains($recipient, ':')) {
            [$type, $value] = explode(':', $recipient, 2);
            $values = array_map('trim', explode(',', $value));

            return match (strtolower($type)) {
                'role' => $this->resolveByRole(['roles' => $values]),
                'user' => $this->resolveByUser(['user_ids' => array_map('intval', $values)]),
                'involvement' => $this->resolveByInvolvementType($record, ['involvement_type' => $values[0] ?? null]),
                default => collect(),
            };
        }

        return collect();
    }
}
