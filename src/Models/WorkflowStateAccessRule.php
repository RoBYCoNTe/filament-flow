<?php

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents an access rule for a workflow state
 *
 * @property int $id
 * @property int $state_id
 * @property string $access_type (view, edit, transition, create)
 * @property string $rule
 * @property string $operator (or, and)
 * @property int $priority
 * @property bool $is_active
 * @property array|null $metadata
 * @property WorkflowState $state
 *
 * @method static create(array $attributes)
 * @method static where(string $column, mixed $value)
 */
class WorkflowStateAccessRule extends Model
{
    public const ACCESS_TYPE_VIEW = 'view';

    public const ACCESS_TYPE_EDIT = 'edit';

    public const ACCESS_TYPE_TRANSITION = 'transition';

    public const ACCESS_TYPE_CREATE = 'create';

    public const OPERATOR_OR = 'or';

    public const OPERATOR_AND = 'and';

    // Special rule tokens
    public const RULE_ALL = '*';

    public const RULE_AUTHENTICATED = '@authenticated';

    public const RULE_ASSIGNED = '@assigned';

    public const RULE_OWNER = '@owner';

    public const RULE_PREFIX_ROLE = 'role:';

    public const RULE_PREFIX_PERMISSION = 'permission:';

    protected $fillable = [
        'state_id',
        'access_type',
        'rule',
        'operator',
        'priority',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
        'priority' => 'integer',
    ];

    protected $attributes = [
        'operator' => self::OPERATOR_OR,
        'priority' => 0,
        'is_active' => true,
    ];

    /**
     * Get the state this rule belongs to
     */
    public function state(): BelongsTo
    {
        return $this->belongsTo(WorkflowState::class, 'state_id');
    }

    /**
     * Check if this is an "everyone" rule
     */
    public function isPublic(): bool
    {
        return $this->rule === self::RULE_ALL;
    }

    /**
     * Check if this is an "authenticated" rule
     */
    public function isAuthenticated(): bool
    {
        return $this->rule === self::RULE_AUTHENTICATED;
    }

    /**
     * Check if this is an "assigned" rule
     */
    public function isAssigned(): bool
    {
        return str_starts_with($this->rule, self::RULE_ASSIGNED);
    }

    /**
     * Get the assignment type if this is an assigned rule
     */
    public function getAssignmentType(): ?string
    {
        if (! $this->isAssigned()) {
            return null;
        }

        if ($this->rule === self::RULE_ASSIGNED) {
            return null; // Any assignment type
        }

        // Extract type from @assigned:type
        $parts = explode(':', $this->rule, 2);

        return $parts[1] ?? null;
    }

    /**
     * Check if this is an "owner" rule
     */
    public function isOwner(): bool
    {
        return $this->rule === self::RULE_OWNER;
    }

    /**
     * Check if this is a role-based rule
     */
    public function isRole(): bool
    {
        return str_starts_with($this->rule, self::RULE_PREFIX_ROLE);
    }

    /**
     * Get the role names if this is a role rule
     */
    public function getRoles(): array
    {
        if (! $this->isRole()) {
            return [];
        }

        $roleString = substr($this->rule, strlen(self::RULE_PREFIX_ROLE));

        return array_map('trim', explode(',', $roleString));
    }

    /**
     * Check if this is a permission-based rule
     */
    public function isPermission(): bool
    {
        return str_starts_with($this->rule, self::RULE_PREFIX_PERMISSION);
    }

    /**
     * Get the permission name if this is a permission rule
     */
    public function getPermission(): ?string
    {
        if (! $this->isPermission()) {
            return null;
        }

        return substr($this->rule, strlen(self::RULE_PREFIX_PERMISSION));
    }

    /**
     * Scope: Only active rules
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Rules for a specific access type
     */
    public function scopeForAccessType($query, string $accessType)
    {
        return $query->where('access_type', $accessType);
    }

    /**
     * Scope: Rules for a specific state
     */
    public function scopeForState($query, int $stateId)
    {
        return $query->where('state_id', $stateId);
    }

    /**
     * Scope: Order by priority (highest first)
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }
}
