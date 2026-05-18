<?php

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property \Illuminate\Database\Eloquent\Collection $fields
 * @property int $id
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
        'conditions',
        'metadata',
    ];

    protected $casts = [
        'requires_confirmation' => 'boolean',
        'requires_reason' => 'boolean',
        'conditions' => 'array',
        'metadata' => 'array',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function fromState(): BelongsTo
    {
        return $this->belongsTo(WorkflowState::class, 'from_state_id');
    }

    public function toState(): BelongsTo
    {
        return $this->belongsTo(WorkflowState::class, 'to_state_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(WorkflowTransitionField::class, 'transition_id');
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(WorkflowTransitionPermission::class, 'transition_id');
    }

    public function validationRules(): HasMany
    {
        return $this->hasMany(WorkflowTransitionValidationRule::class, 'transition_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(WorkflowNotification::class, 'transition_id');
    }

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
        return $this->fields->filter(fn ($field) => (filled($field->name) || filled($field->field_name))
            && (filled($field->type) || filled($field->field_type)));
    }

    /**
     * @return array<string, array<string>> ['field_name' => ['rule1', 'rule2'], ...]
     */
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
}
