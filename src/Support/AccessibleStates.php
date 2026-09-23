<?php

namespace RoBYCoNTe\FilamentFlow\Support;

/**
 * The states of a model that a user may see — and **what it means** when there are none.
 *
 * An empty pair was ambiguous: the platform administrator sees everything, whoever has no
 * workflow sees nothing, and the two cases were indistinguishable — the reader had to guess by
 * looking at the roles. Now the outcome says it.
 */
final class AccessibleStates
{
    /**
     * @param  list<string>  $free  states a role opens: everyone sees them
     * @param  list<string>  $assigned  states that ask to be the owner or an assignee
     */
    private function __construct(
        public readonly bool $unrestricted,
        public readonly array $free = [],
        public readonly array $assigned = [],
    ) {}

    /** The engine does not filter this user (platform administrator). */
    public static function unrestricted(): self
    {
        return new self(true);
    }

    /** No state: there is no workflow to read, or there is no user. */
    public static function none(): self
    {
        return new self(false);
    }

    /**
     * @param  list<string>  $free
     * @param  list<string>  $assigned
     */
    public static function of(array $free, array $assigned): self
    {
        return new self(false, $free, $assigned);
    }

    /** No state allowed: the reader is left with their own rows. */
    public function isNone(): bool
    {
        return ! $this->unrestricted && $this->free === [] && $this->assigned === [];
    }
}
