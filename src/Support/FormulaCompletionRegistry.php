<?php

namespace RoBYCoNTe\FilamentFlow\Support;

use InvalidArgumentException;
use RoBYCoNTe\FilamentFlow\Contracts\FormulaCompletionProvider;

/**
 * The registry of the formula scopes: a scope is registered under a name, and it is asked for
 * the completions of the context the editor is working on.
 */
final class FormulaCompletionRegistry
{
    /** @var array<string, FormulaCompletionProvider> */
    private array $providers = [];

    public function register(string $scope, FormulaCompletionProvider $provider): void
    {
        $this->providers[$scope] = $provider;
    }

    /**
     * @throws InvalidArgumentException
     */
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
