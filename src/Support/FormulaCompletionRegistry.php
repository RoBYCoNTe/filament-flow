<?php

namespace RoBYCoNTe\FilamentFlow\Support;

use InvalidArgumentException;
use RoBYCoNTe\FilamentFlow\Contracts\FormulaCompletionProvider;

final class FormulaCompletionRegistry
{
    /** @var array<string, FormulaCompletionProvider> */
    private array $providers = [];

    public function register(string $scope, FormulaCompletionProvider $provider): void
    {
        $this->providers[$scope] = $provider;
    }

    public function resolve(string $scope): FormulaCompletionProvider
    {
        if (! isset($this->providers[$scope])) {
            throw new InvalidArgumentException("No FormulaCompletionProvider registered for scope '{$scope}'.");
        }

        return $this->providers[$scope];
    }

    public function has(string $scope): bool
    {
        return isset($this->providers[$scope]);
    }

    /** @return list<string> */
    public function registeredScopes(): array
    {
        return array_keys($this->providers);
    }
}
