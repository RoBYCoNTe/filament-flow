<?php

namespace RoBYCoNTe\FilamentFlow\Concerns;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use RoBYCoNTe\FilamentFlow\Services\StateService;
use Spatie\ModelStates\State;

trait ResolvesActionAttributes
{
    private function resolveFromTransitionOrState(
        State|string|null $state,
        string $interface,
        string $method,
    ): mixed {
        $from = $state ?? $this->getFromState();
        $to = $this->getToStateClass();

        // If $to is a string (database-only state), get metadata from database
        if (is_string($to)) {
            return $this->resolveFromDatabase($to, $method);
        }

        // If $from is a string (database-only state), can't use Spatie's config
        if (is_string($from)) {
            // Try to get from $to State class directly
            if (is_subclass_of($to, $interface)) {
                return $to->{$method}();
            }

            return null;
        }

        $transitionClass = $from::config()
            ->resolveTransitionClass(
                $from::getMorphClass(),
                $to::getMorphClass(),
            );

        if (
            $transitionClass &&
            class_exists($transitionClass) &&
            is_subclass_of($transitionClass, $interface)
        ) {
            return app($transitionClass)->{$method}();
        }

        if (is_subclass_of($to, $interface)) {
            return $to->{$method}();
        }

        return null;
    }

    /**
     * Resolve metadata from database for database-only states
     */
    private function resolveFromDatabase(string $stateName, string $method): mixed
    {
        $record = $this->getRecord();
        if (! $record) {
            return null;
        }

        $stateService = app(StateService::class);
        $metadata = $stateService->getStateMetadata(
            get_class($record),
            $stateName,
            $this->getAttribute()
        );

        if (! $metadata) {
            return null;
        }

        // Map method names to metadata keys
        return match ($method) {
            'getLabel' => $metadata['label'] ?? null,
            'getColor' => $metadata['color'] ?? null,
            'getIcon' => $metadata['icon'] ?? null,
            'getDescription' => $metadata['description'] ?? null,
            default => null,
        };
    }

    private function resolveLabel(State|string|null $state): mixed
    {
        return $this->resolveFromTransitionOrState(
            $state,
            HasLabel::class,
            'getLabel',
        );
    }

    private function resolveColor(State|string|null $state): mixed
    {
        return $this->resolveFromTransitionOrState(
            $state,
            HasColor::class,
            'getColor',
        );
    }

    private function resolveIcon(State|string|null $state): mixed
    {
        return $this->resolveFromTransitionOrState(
            $state,
            HasIcon::class,
            'getIcon',
        );
    }

    private function resolveDescription(State|string|null $state): mixed
    {
        return $this->resolveFromTransitionOrState(
            $state,
            HasDescription::class,
            'getDescription',
        );
    }

    protected function setActionAttributes(): void
    {

        // Model
        $this->requiresConfirmation();
        $this->modalSubmitAction(fn ($action) => $action->outlined());
        $this->modalDescription(fn () => $this->getTooltip());
        $this->modalIcon(fn () => $this->getIcon());
        $this->modalIconColor(fn () => $this->getColor());

        // Form - DON'T call setupTransitionForm here, it will be called lazily
        // The schema is already set up lazily in setupTransitionFormFromDatabase
        // and setupTransitionFormFromClass handles its own lazy loading
    }
}
