<?php

namespace RoBYCoNTe\FilamentFlow\Concerns;

use Spatie\ModelStates\State;
use Spatie\ModelStates\Transition;

trait HasStateActions
{
    protected string|State|null $toState = null;

    public function transitionTo(string|State|null $toState): static
    {
        $this->toState = $toState;

        return $this;
    }

    public function getToState(): string|State|null
    {
        return $this->evaluate($this->toState);
    }

    public function getFromState(): string|State|null
    {
        return $this->evaluate($this->getRecord()->{$this->getAttribute()});
    }

    public function getToStateClass(): string|State|null
    {
        $toState = $this->getToState();

        if ($toState === null) {
            return null;
        }

        // If it's already a string (database-only state), return it as is
        if (is_string($toState)) {
            return $toState;
        }

        // If it's already a State instance, return it
        if ($toState instanceof State) {
            return $toState;
        }

        // Otherwise, instantiate the State class
        if (class_exists($toState)) {
            return $this->evaluate(new $toState($this->getModel()));
        }

        return $toState;
    }

    public function getFromStateClass(): ?string
    {
        $fromState = $this->getFromState();
        if (! $fromState) {
            return null;
        }

        return is_string($fromState) ? $fromState : get_class($fromState);
    }

    /**
     * Get the transition class name if it exists.
     */
    public function getTransitionClass(): ?string
    {
        $fromState = $this->getFromState();
        $toState = $this->getToState();

        if (! $fromState || ! $toState) {
            return null;
        }

        // Convert to class string if needed
        $fromStateClass = is_string($fromState) ? $fromState : get_class($fromState);
        $toStateClass = is_string($toState) ? $toState : get_class($toState);

        // If either state is database-only (not a class), return null
        // Database-only states don't have transition classes
        if (! class_exists($fromStateClass) || ! class_exists($toStateClass)) {
            return null;
        }

        /** @var class-string<State> $fromStateClass */
        /** @var class-string<State> $toStateClass */
        return $this->evaluate($fromStateClass::config()->resolveTransitionClass($fromStateClass::getMorphClass(), $toStateClass::getMorphClass()));
    }

    public function hasTransitionClass(): bool
    {
        $transitionClass = $this->getTransitionClass();

        return $transitionClass && class_exists($transitionClass);
    }

    public function getClassInstance(): string|State|Transition|null
    {
        if ($this->hasTransitionClass()) {
            $transitionClass = $this->getTransitionClass();
            $modelClass = $this->getModel();

            if ($transitionClass && class_exists($transitionClass) && $modelClass && class_exists($modelClass)) {
                return $this->evaluate(new $transitionClass(new $modelClass));
            }
        }

        return $this->getToStateClass();
    }
}
