<?php

namespace RoBYCoNTe\FilamentFlow\Definition;

use RoBYCoNTe\FilamentFlow\Definition\Enums\NotificationTrigger;
use RoBYCoNTe\FilamentFlow\Definition\Enums\ValidationLevel;

/**
 * A step between two states: what it requires (conditions, validation rules), what it writes
 * (its side effects), and how it is presented (label, colour, confirmation, reason).
 *
 * A transition with no arrival state is an action that stays where it is.
 */
final class Transition
{
    private ?string $label = null;

    private ?string $description = null;

    private bool $confirmation = false;

    private bool $requiresReason = false;

    /** @var list<array<string,mixed>> */
    private array $conditions = [];

    /** @var list<SideEffect> */
    private array $sideEffects = [];

    /** @var list<ValidationRule> */
    private array $validationRules = [];

    /** @var list<Notification> */
    private array $notifications = [];

    /** @var array<string,mixed> */
    private array $metadata = [];

    private ValidationLevel $validationLevel = ValidationLevel::Full;

    private function __construct(
        private readonly string $name,
        private readonly ?string $from,
        private readonly ?string $to,
    ) {}

    public static function make(string $name, ?string $from, ?string $to = null): self
    {
        return new self($name, $from, $to);
    }

    /** Global action: available from any state, no state change. */
    public static function action(string $name, ?string $label = null): self
    {
        return (new self($name, null, null))->label($label ?? $name);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function description(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function confirm(bool $confirmation = true): self
    {
        $this->confirmation = $confirmation;

        return $this;
    }

    public function requiresReason(bool $requires = true): self
    {
        $this->requiresReason = $requires;

        return $this;
    }

    /** @param list<array<string,mixed>> $conditions */
    public function conditions(array $conditions): self
    {
        $this->conditions = array_values($conditions);

        return $this;
    }

    /** @param array<string,mixed> $condition */
    public function condition(array $condition): self
    {
        $this->conditions[] = $condition;

        return $this;
    }

    public function formulaCondition(string $expression, ?string $message = null): self
    {
        return $this->condition(array_filter([
            'type' => 'formula',
            'expression' => $expression,
            'message_template' => $message,
        ], static fn ($v) => $v !== null));
    }

    public function sideEffect(SideEffect $effect): self
    {
        $this->sideEffects[] = $effect;

        return $this;
    }

    /** @param list<SideEffect> $effects */
    public function sideEffects(array $effects): self
    {
        $this->sideEffects = array_values($effects);

        return $this;
    }

    public function validationRule(ValidationRule $rule): self
    {
        $this->validationRules[] = $rule;

        return $this;
    }

    /** @param list<ValidationRule> $rules */
    public function validationRules(array $rules): self
    {
        $this->validationRules = array_values($rules);

        return $this;
    }

    /** Notification sent when this transition is executed. */
    public function notification(Notification $notification): self
    {
        $notification->trigger(NotificationTrigger::OnTransition);

        $this->notifications[] = $notification;

        return $this;
    }

    /** @param list<Notification> $notifications */
    public function notifications(array $notifications): self
    {
        $this->notifications = array_values($notifications);

        return $this;
    }

    /**
     * How much the transition has to be validated. Defaults to every rule.
     */
    public function validationLevel(ValidationLevel $level): self
    {
        $this->validationLevel = $level;

        return $this;
    }

    /**
     * A "save and continue later" step: no rule of the workflow is enforced.
     */
    public function withoutValidation(): self
    {
        return $this->validationLevel(ValidationLevel::None);
    }

    public function getValidationLevel(): ValidationLevel
    {
        return $this->validationLevel;
    }

    /** @param array<string,mixed> $metadata */
    public function metadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function from(): ?string
    {
        return $this->from;
    }

    public function to(): ?string
    {
        return $this->to;
    }

    /** @return list<SideEffect> */
    public function effects(): array
    {
        return $this->sideEffects;
    }

    /** @return list<ValidationRule> */
    public function rules(): array
    {
        return $this->validationRules;
    }

    /** @return list<Notification> */
    public function transitionNotifications(): array
    {
        return $this->notifications;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'from' => $this->from,
            'to' => $this->to,
            'label' => $this->label ?? $this->name,
            'description' => $this->description,
            'requires_confirmation' => $this->confirmation,
            'requires_reason' => $this->requiresReason,
            'conditions' => $this->conditions,
            'metadata' => $this->metadata,
            'validation_level' => $this->validationLevel->value,
            'side_effects' => array_map(static fn (SideEffect $e): array => $e->toArray(), $this->sideEffects),
            'validation_rules' => array_map(static fn (ValidationRule $r): array => $r->toArray(), $this->validationRules),
            'notifications' => array_map(static fn (Notification $n): array => $n->toArray(), $this->notifications),
        ];
    }
}
