<?php

namespace RoBYCoNTe\FilamentFlow\Support;

use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Contracts\FormulaCompletionProvider;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionSideEffect;

/**
 * Built-in scope 'workflow' — available variables in all filament-flow
 * formula fields (transition validation rules, scheduled check conditions, etc.).
 *
 * If a $context model is provided and belongs to a workflow, its states
 * are added to the string_values list so inside-string completions work.
 */
final class WorkflowFormulaScope implements FormulaCompletionProvider
{
    public function getCompletions(?Model $context): CompletionPayload
    {
        $states = $this->resolveStates($context);

        return new CompletionPayload(
            variables: [
                [
                    'name' => 'now',
                    'kind' => 'variable',
                    'type' => 'DateTime',
                    'description' => __('filament-flow::messages.formula_now'),
                    'properties' => [
                        ['name' => 'year', 'kind' => 'property', 'type' => 'int'],
                        ['name' => 'month', 'kind' => 'property', 'type' => 'int'],
                        ['name' => 'day', 'kind' => 'property', 'type' => 'int'],
                        ['name' => 'hour', 'kind' => 'property', 'type' => 'int'],
                        ['name' => 'minute', 'kind' => 'property', 'type' => 'int'],
                        ['name' => 'timestamp', 'kind' => 'property', 'type' => 'int'],
                    ],
                    'methods' => [
                        ['name' => 'format', 'kind' => 'method', 'signature' => 'format(string $format): string', 'description' => __('filament-flow::messages.formula_now_format')],
                        ['name' => 'addDays', 'kind' => 'method', 'signature' => 'addDays(int $days): DateTime', 'description' => __('filament-flow::messages.formula_now_add_days')],
                        ['name' => 'diffInDays', 'kind' => 'method', 'signature' => 'diffInDays(DateTime $other): int', 'description' => __('filament-flow::messages.formula_now_diff_in_days')],
                    ],
                ],
                [
                    'name' => 'state',
                    'kind' => 'variable',
                    'type' => 'string',
                    'description' => __('filament-flow::messages.formula_state'),
                ],
                [
                    'name' => 'user',
                    'kind' => 'variable',
                    'type' => 'User',
                    'description' => __('filament-flow::messages.formula_user'),
                    'properties' => [
                        ['name' => 'id', 'kind' => 'property', 'type' => 'int'],
                        ['name' => 'name', 'kind' => 'property', 'type' => 'string'],
                        ['name' => 'email', 'kind' => 'property', 'type' => 'string'],
                    ],
                ],
                [
                    'name' => 'record',
                    'kind' => 'variable',
                    'type' => 'Model',
                    'description' => __('filament-flow::messages.formula_record'),
                    'properties' => [
                        ['name' => 'id', 'kind' => 'property', 'type' => 'mixed'],
                        ['name' => 'created_at', 'kind' => 'property', 'type' => 'DateTime'],
                        ['name' => 'updated_at', 'kind' => 'property', 'type' => 'DateTime'],
                    ],
                ],
            ],
            stringValues: [
                'states' => $states,
            ],
        );
    }

    /** @return list<string> */
    private function resolveStates(?Model $context): array
    {
        if (! $context) {
            return [];
        }

        // WorkflowTransition → load states from its parent workflow
        $workflowId = match (true) {
            $context instanceof WorkflowTransition => $context->workflow_id,
            $context instanceof WorkflowTransitionSideEffect => $context->transition?->workflow_id,
            $context instanceof WorkflowState => $context->workflow_id,
            default => null,
        };

        if (! $workflowId) {
            return [];
        }

        return WorkflowState::where('workflow_id', $workflowId)
            ->pluck('name')
            ->toArray();
    }
}
