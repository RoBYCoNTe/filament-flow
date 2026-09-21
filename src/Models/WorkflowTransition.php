<?php

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use RoBYCoNTe\FilamentFlow\Definition\Enums\ValidationLevel;

/**
 * @property-read Collection<int, WorkflowTransitionField> $fields
 * @property int $id
 * @property int $workflow_id
 * @property int|null $from_state_id
 * @property int|null $to_state_id
 * @property string $name
 * @property string $label
 * @property string|null $description
 * @property string|null $class_name
 * @property bool $requires_confirmation
 * @property bool $requires_reason
 * @property ValidationLevel $validation_level
 * @property list<string>|null $conditions
 * @property array<int,array<string,mixed>>|null $conditions
 * @property array<string,mixed>|null $metadata
 * @property-read Workflow|null $workflow
 * @property-read WorkflowState|null $fromState
 * @property-read WorkflowState|null $toState
 * @property-read Collection<int, WorkflowTransitionPermission> $permissions
 * @property-read Collection<int, WorkflowTransitionValidationRule> $validationRules
 * @property-read Collection<int, WorkflowNotification> $notifications
 * @property-read Collection<int, WorkflowTransitionSideEffect> $sideEffects
 */
class WorkflowTransition extends Model
{
    protected $fillable = [
        'workflow_id',
        'from_state_id',
        'to_state_id',
        'name',
        'label',
        'description',
        'class_name',
        'requires_confirmation',
        'requires_reason',
        'validation_level',
        'conditions',
        'metadata',
    ];

    protected $casts = [
        'requires_confirmation' => 'boolean',
        'requires_reason' => 'boolean',
        'validation_level' => ValidationLevel::class,
        'conditions' => 'array',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<Workflow, $this> */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /** @return BelongsTo<WorkflowState, $this> */
    public function fromState(): BelongsTo
    {
        return $this->belongsTo(WorkflowState::class, 'from_state_id');
    }

    /** @return BelongsTo<WorkflowState, $this> */
    public function toState(): BelongsTo
    {
        return $this->belongsTo(WorkflowState::class, 'to_state_id');
    }

    /** @return HasMany<WorkflowTransitionField, $this> */
    public function fields(): HasMany
    {
        return $this->hasMany(WorkflowTransitionField::class, 'transition_id');
    }

    /** @return HasMany<WorkflowTransitionPermission, $this> */
    public function permissions(): HasMany
    {
        return $this->hasMany(WorkflowTransitionPermission::class, 'transition_id');
    }

    /** @return HasMany<WorkflowTransitionValidationRule, $this> */
    public function validationRules(): HasMany
    {
        return $this->hasMany(WorkflowTransitionValidationRule::class, 'transition_id');
    }

    /** @return HasMany<WorkflowNotification, $this> */
    public function notifications(): HasMany
    {
        return $this->hasMany(WorkflowNotification::class, 'transition_id');
    }

    /** @return HasMany<WorkflowTransitionSideEffect, $this> */
    public function sideEffects(): HasMany
    {
        return $this->hasMany(WorkflowTransitionSideEffect::class, 'transition_id');
    }

    public function activeSideEffects(): HasMany
    {
        return $this->sideEffects()->where('is_active', true)->orderBy('sort_order');
    }

    /**
     * Is this an in-state action (no state change)?
     */
    public function isAction(): bool
    {
        return $this->to_state_id === null;
    }

    /**
     * Is this available from any state?
     */
    public function isGlobal(): bool
    {
        return $this->from_state_id === null;
    }

    /**
     * Is this a state-changing transition?
     */
    public function isStateTransition(): bool
    {
        return $this->to_state_id !== null
            && $this->to_state_id !== $this->from_state_id;
    }

    /**
     * Check if this transition is available from a given state.
     */
    public function isAvailableFromState(?int $stateId): bool
    {
        if ($this->from_state_id === null) {
            return true;
        }

        return $this->from_state_id === $stateId;
    }

    public function hasValidFields(): bool
    {
        return $this->getValidFields()->isNotEmpty();
    }

    public function getValidFields(): Collection
    {
        return $this->fields->filter(fn (WorkflowTransitionField $field): bool => (filled($field->field_name))
            && (filled($field->field_type)));
    }

    /**
     * @return array<string, array<string>> ['field_name' => ['rule1', 'rule2'], ...]
     */
    /**
     * How much this transition has to be validated.
     */
    public function validationLevel(): ValidationLevel
    {
        $level = $this->validation_level;

        return $level instanceof ValidationLevel ? $level : ValidationLevel::from((string) $level ?: 'full');
    }

    public function getValidationRules(): array
    {
        return $this->validationRules()
            ->orderBy('sort_order')
            ->get()
            ->pluck('rules', 'field_name')
            ->all();
    }

    /**
     * @return array<string, string> ['field_name' => 'message', ...]
     */
    public function getValidationMessages(): array
    {
        return $this->validationRules()
            ->orderBy('sort_order')
            ->whereNotNull('custom_message')
            ->get()
            ->pluck('custom_message', 'field_name')
            ->all();
    }

    public function hasValidationRules(): bool
    {
        return $this->validationRules()->exists();
    }

    /**
     * Validation rules as the engine consumes them: one entry per rule row, with
     * its condition, type, message and field label.
     *
     * @return list<array{field_name:string,rules:list<string>,custom_message:string|null,rule_type:string,condition:string|null,label:string|null}>
     */
    public function validationRuleConfigs(): array
    {
        return $this->validationRules()
            ->orderBy('sort_order')
            ->get()
            ->map(static fn (WorkflowTransitionValidationRule $rule): array => [
                'field_name' => (string) $rule->field_name,
                'rules' => array_values(array_map('strval', (array) $rule->rules)),
                'custom_message' => $rule->custom_message !== null ? (string) $rule->custom_message : null,
                'rule_type' => (string) $rule->rule_type,
                'condition' => $rule->condition !== null ? (string) $rule->condition : null,
                'label' => $rule->label !== null ? (string) $rule->label : null,
            ])
            ->all();
    }
}
