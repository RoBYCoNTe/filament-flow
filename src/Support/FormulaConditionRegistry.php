<?php

namespace RoBYCoNTe\FilamentFlow\Support;

use RoBYCoNTe\FilamentFlow\Contracts\FormulaConditionProvider;
use RuntimeException;

/**
 * The registry of the hosts that know how to evaluate a formula condition: the package asks for
 * one by name and receives yes or no — or nothing at all when the host registered none.
 */
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

    /**
     * @throws RuntimeException
     */
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
