<?php

namespace RoBYCoNTe\FilamentFlow\Tables\Columns;

use Closure;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Concerns\HasStateSorting;
use RoBYCoNTe\FilamentFlow\Services\StateService;

class StateColumn extends TextColumn
{
    use HasStateSorting;

    protected Closure|string|null $attribute = null;

    /**
     * In-memory cache for getStateMetadata results during a single render.
     *
     * @var array<string, array|null>
     */
    protected static array $metadataCache = [];

    /**
     * Flush the in-memory metadata cache (useful in tests).
     */
    public static function flushMetadataCache(): void
    {
        static::$metadataCache = [];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupStateDisplay();
        $this->setupStateSorting();
    }

    protected function setupStateDisplay(): void
    {
        $this->state(function ($record) {
            $stateInstance = $record->{$this->getAttribute()};

            if (! $stateInstance) {
                return null;
            }

            if (is_string($stateInstance)) {
                $metadata = $this->getCachedStateMetadata($record, $stateInstance);

                return $metadata['label'] ?? $stateInstance;
            }

            if (method_exists($stateInstance, 'getLabel')) {
                return $stateInstance->getLabel();
            }

            return $stateInstance::getMorphClass();
        });

        $this->badge()
            ->color(function ($record) {
                $stateInstance = $record->{$this->getAttribute()};

                if (! $stateInstance) {
                    return null;
                }

                if (is_string($stateInstance)) {
                    $metadata = $this->getCachedStateMetadata($record, $stateInstance);

                    return $metadata['color'] ?? null;
                }

                if (method_exists($stateInstance, 'getColor')) {
                    return $stateInstance->getColor();
                }

                return null;
            });

        $this->icon(function ($record) {
            $stateInstance = $record->{$this->getAttribute()};

            if (! $stateInstance) {
                return null;
            }

            if (is_string($stateInstance)) {
                $metadata = $this->getCachedStateMetadata($record, $stateInstance);

                return $metadata['icon'] ?? null;
            }

            if (method_exists($stateInstance, 'getIcon')) {
                return $stateInstance->getIcon();
            }

            return null;
        });
    }

    protected function getCachedStateMetadata(Model $record, string $stateValue): ?array
    {
        $class = get_class($record);
        $attribute = $this->getAttribute();
        $cacheKey = "{$class}:{$attribute}:{$stateValue}";

        if (array_key_exists($cacheKey, static::$metadataCache)) {
            return static::$metadataCache[$cacheKey];
        }

        $stateService = app(StateService::class);
        $metadata = $stateService->getStateMetadata($class, $stateValue, $attribute);

        return static::$metadataCache[$cacheKey] = $metadata;
    }

    public function getAttribute(?Model $model = null): string
    {
        if ($model === null) {
            $model = $this->getRecord();
        }

        if ($this->attribute !== null) {
            return $this->evaluate($this->attribute);
        }

        if (method_exists($model, 'getDefaultStates')) {
            $defaultStates = $model::getDefaultStates();
            if ($defaultStates && ! $defaultStates->isEmpty()) {
                return (string) array_key_first($defaultStates->toArray());
            }
        }

        return $this->getName();
    }

    public function attribute(string|Closure|null $attribute): static
    {
        $this->attribute = $attribute;

        return $this;
    }
}
