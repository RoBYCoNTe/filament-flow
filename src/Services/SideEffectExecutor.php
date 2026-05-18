<?php

namespace RoBYCoNTe\FilamentFlow\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use Throwable;

class SideEffectExecutor
{
    /**
     * Execute all active side effects for a transition on the given model.
     */
    public function execute(Model $model, WorkflowTransition $transition): void
    {
        $sideEffects = $transition->activeSideEffects()->get();

        if ($sideEffects->isEmpty()) {
            return;
        }

        $this->applySideEffects($model, $sideEffects);
    }

    protected function applySideEffects(Model $model, Collection $sideEffects): void
    {
        $dirty = false;

        foreach ($sideEffects as $effect) {
            try {
                $changed = $this->applyEffect($model, $effect);
                $dirty = $dirty || $changed;
            } catch (Throwable $e) {
                report($e);
            }
        }

        if ($dirty) {
            $model->saveQuietly();
        }
    }

    protected function applyEffect(Model $model, $effect): bool
    {
        return match ($effect->effect_type) {
            'set_field' => $this->setField($model, $effect->field_name, $effect->value_expression),
            'set_timestamp' => $this->setTimestamp($model, $effect->field_name, $effect->value_expression),
            'clear_field' => $this->clearField($model, $effect->field_name),
            'increment' => $this->increment($model, $effect->field_name, $effect->value_expression),
            'custom_class' => $this->executeCustomClass($model, $effect->value_expression),
            default => false,
        };
    }

    protected function setField(Model $model, string $fieldName, ?string $valueExpression): bool
    {
        if ($valueExpression === null) {
            return false;
        }

        // Check if value_expression references another field (prefixed with "field:")
        if (str_starts_with($valueExpression, 'field:')) {
            $sourceField = substr($valueExpression, 6);
            $model->{$fieldName} = $model->{$sourceField};
        } else {
            $model->{$fieldName} = $valueExpression;
        }

        return true;
    }

    protected function setTimestamp(Model $model, string $fieldName, ?string $valueExpression): bool
    {
        $model->{$fieldName} = match ($valueExpression) {
            'now', null => now(),
            default => $valueExpression,
        };

        return true;
    }

    protected function clearField(Model $model, string $fieldName): bool
    {
        $model->{$fieldName} = null;

        return true;
    }

    protected function increment(Model $model, string $fieldName, ?string $valueExpression): bool
    {
        $amount = (int) ($valueExpression ?? 1);
        $model->{$fieldName} = ($model->{$fieldName} ?? 0) + $amount;

        return true;
    }

    protected function executeCustomClass(Model $model, ?string $className): bool
    {
        if (! $className || ! class_exists($className)) {
            return false;
        }

        $instance = app($className);

        if (method_exists($instance, 'execute')) {
            $instance->execute($model);

            return true;
        }

        return false;
    }
}
