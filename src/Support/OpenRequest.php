<?php

namespace RoBYCoNTe\FilamentFlow\Support;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * One exchange between the sides of a workflow: a transition that asked something (who, when,
 * with which note and which term) and the transition that answered it, when it has one.
 *
 * It is a reading of the history, not a row of its own: nothing is stored for it, and it
 * exists only while a component asks.
 */
final readonly class OpenRequest
{
    /** A request: the workflow waits for an answer. */
    public const KIND_REQUEST = 'request';

    /** A message: the workflow said something, and waits for nothing. */
    public const KIND_MESSAGE = 'message';

    public function __construct(
        public string $transitionName,
        public ?string $label,
        public ?string $fromState,
        public ?string $toState,
        public ?string $toStateLabel,
        public ?string $note,
        public ?Carbon $deadline,
        public ?Carbon $requestedAt,
        public ?string $requestedBy,
        public ?Carbon $answeredAt = null,
        public ?string $answeredBy = null,
        public ?string $answeredByTransition = null,
        public string $kind = self::KIND_REQUEST,
        public ?string $color = null,
    ) {}

    /** Whether the workflow said something and walks away: a decision, a closing note. */
    public function isMessage(): bool
    {
        return $this->kind === self::KIND_MESSAGE;
    }

    public function isOpen(): bool
    {
        return ! $this->isMessage() && $this->answeredAt === null;
    }

    public function isOverdue(?Carbon $now = null): bool
    {
        if ($this->deadline === null) {
            return false;
        }

        return $this->deadline->lessThan($now ?? Carbon::now());
    }

    /** Whole days left before the term: negative once it is past. */
    public function daysRemaining(?Carbon $now = null): ?int
    {
        if ($this->deadline === null) {
            return null;
        }

        return (int) ($now ?? Carbon::now())->startOfDay()->diffInDays($this->deadline->startOfDay(), false);
    }

    /** Whether the term is close enough to be worth warning about. */
    public function isDueSoon(int $days = 3, ?Carbon $now = null): bool
    {
        $remaining = $this->daysRemaining($now);

        return $remaining !== null && $remaining >= 0 && $remaining <= $days;
    }

    /** A deadline the history carried as text, when it parses. */
    public static function parseDate(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'transition_name' => $this->transitionName,
            'label' => $this->label,
            'kind' => $this->kind,
            'color' => $this->color,
            'from_state' => $this->fromState,
            'to_state' => $this->toState,
            'to_state_label' => $this->toStateLabel,
            'note' => $this->note,
            'deadline' => $this->deadline?->toDateTimeString(),
            'requested_at' => $this->requestedAt?->toDateTimeString(),
            'requested_by' => $this->requestedBy,
            'is_open' => $this->isOpen(),
            'answered_at' => $this->answeredAt?->toDateTimeString(),
            'answered_by' => $this->answeredBy,
            'answered_by_transition' => $this->answeredByTransition,
            'days_remaining' => $this->daysRemaining(),
        ];
    }
}
