<?php

namespace RoBYCoNTe\FilamentFlow\Support;

use RoBYCoNTe\FilamentFlow\Contracts\FormulaConditionProvider;
use RuntimeException;

final class FormulaConditionRegistry
{
    private ?FormulaConditionProvider $provider = null;

    public function register(FormulaConditionProvider $provider): void
    {
        $this->provider = $provider;
    }

    public function has(): bool
    {
        return $this->provider !== null;
    }

    public function get(): FormulaConditionProvider
    {
        if ($this->provider === null) {
            throw new RuntimeException('No FormulaConditionProvider has been registered.');
        }

        return $this->provider;
    }

    public function clear(): void
    {
        $this->provider = null;
    }
}
