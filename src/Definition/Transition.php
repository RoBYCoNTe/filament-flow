<?php

namespace RoBYCoNTe\FilamentFlow\Definition;

use InvalidArgumentException;
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

    /**
     * The transition asks something of the other side of the workflow — the applicant,
     * usually: a note to read, a document to attach. The request stays open until a
     * transition that answers it runs, and the flow tells whoever opens the record what is
     * expected of them (see `OpenRequests`).
     *
     * The flag lives in the metadata the transition already carries: nothing else is stored,
     * and a workflow that never marks a transition behaves exactly as before.
     */
    public function opensRequest(bool $opens = true): self
    {
        return $this->withMetadata('opens_request', $opens);
    }

    /**
     * The transition answers the request an earlier one opened: the exchange is closed, and
     * the flow stops asking.
     */
    public function answersRequest(bool $answers = true): self
    {
        return $this->withMetadata('answers_request', $answers);
    }

    /**
     * Where the note and the term of the request live, in the words of the host: the engine
     * reads them from here, so the components that tell what is expected need no path of their
     * own — the call declares them once, on the transition that asks.
     */
    public function withRequestFields(?string $noteField = null, ?string $deadlineField = null): self
    {
        if ($noteField !== null && $noteField !== '') {
            $this->withMetadata('note_field', $noteField);
        }

        if ($deadlineField !== null && $deadlineField !== '') {
            $this->withMetadata('deadline_field', $deadlineField);
        }

        return $this;
    }

    /**
     * What the office may add to the request this transition opens: documents, and the fields
     * the answering side will be allowed to change. Declared once here, it is carried in the
     * metadata like the rest of the request (see `RequestScope`).
     */
    public function withRequestScope(RequestScope $scope): self
    {
        return $this->withMetadata('request_scope', $scope->toArray());
    }

    /**
     * The scope the transition declares, or null when it declares none.
     */
    public function requestScope(): ?RequestScope
    {
        $declared = $this->metadata['request_scope'] ?? null;

        return is_array($declared) ? RequestScope::fromArray($declared) : null;
    }

    /**
     * Whether this transition opens a request (the flag the DSL writes).
     */
    public function isRequestOpening(): bool
    {
        return (bool) ($this->metadata['opens_request'] ?? false);
    }

    /**
     * Whether this transition answers an open request (the flag the DSL writes).
     */
    public function isRequestAnswering(): bool
    {
        return (bool) ($this->metadata['answers_request'] ?? false);
    }

    /**
     * The transition leaves a message for the other side of the workflow: a decision with its
     * reason, a note that closes the exchange. It is read like a request — same history, same
     * note — but it expects no answer, and the flow shows it as what the workflow said rather
     * than as something to do. The note is named here, once.
     */
    public function leavesMessage(?string $noteField = null): self
    {
        $this->withMetadata('leaves_message', true);

        if ($noteField !== null && $noteField !== '') {
            $this->withMetadata('note_field', $noteField);
        }

        return $this;
    }

    /** Whether this transition leaves a message (the flag the DSL writes). */
    public function isMessageLeaving(): bool
    {
        return (bool) ($this->metadata['leaves_message'] ?? false);
    }

    private function withMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;

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
        if (isset($this->metadata['request_scope']) && ! $this->isRequestOpening()) {
            throw new InvalidArgumentException("The transition [{$this->name}] declares a request scope but does not open a request: call opensRequest().");
        }

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
