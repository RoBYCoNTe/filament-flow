<?php

namespace RoBYCoNTe\FilamentFlow\Contracts;

use Spatie\ModelStates\State;
use Spatie\ModelStates\Transition;

interface HasStateAction
{
    public function transitionTo(string|State|null $state): static;

    public function getToState(): string|State|null;

    public function getFromState(): string|State|null;

    public function getToStateClass(): string|State|null;

    public function getTransitionClass(): ?string;

    public function hasTransitionClass(): bool;

    public function getClassInstance(): string|State|Transition|null;
}
