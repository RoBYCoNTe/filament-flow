<?php

namespace RoBYCoNTe\FilamentFlow\Actions;

use Exception;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use RoBYCoNTe\FilamentFlow\Exceptions\WorkflowValidationException;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use Spatie\ModelStates\State;
use Throwable;

/**
 * StateBulkActionGroup generates bulk actions for all possible state transitions.
 *
 * This helper class automatically creates StateBulkAction instances for each valid
 * state transition defined in the workflow. Actions are only shown when ALL selected
 * records share the same state and can perform that transition.
 *
 * @example
 * ```php
 * use RoBYCoNTe\FilamentFlow\Actions\StateBulkActionGroup;
 *
 * BulkActionGroup::make([
 *     ...StateBulkActionGroup::make('state', OrderState::class),
 *     DeleteBulkAction::make(),
 * ])
 * ```
 */
class StateBulkActionGroup
{
    /**
     * Generate StateBulkAction instances for all possible state transitions.
     *
     * This method returns an array of BulkAction that can be spread into
     * a BulkActionGroup.
     *
     * @param  string  $columnName  The state column name
     * @param  string  $stateClass  The base state class (e.g., OrderState)
     * @return array Array of BulkAction instances
     */
    public static function make(string $columnName, string $stateClass): array
    {
        return static::generateStateBulkActions($stateClass, $columnName);
    }

    /**
     * Bulk transitions of a database-first workflow (states stored as strings,
     * no Spatie state class).
     *
     * Every transition becomes a BulkAction; the records it does not apply to are
     * skipped, and the ones that fail (access rules or validation) are counted
     * and reported, never silently ignored.
     *
     * @return array<int, BulkAction>
     */
    public static function forDatabaseRecord(string $modelClass, string $columnName = 'state', ?int $tenantId = null): array
    {
        $workflow = Workflow::findForModel($modelClass, $columnName, $tenantId);

        if ($workflow === null) {
            return [];
        }

        $states = $workflow->states()->get()->keyBy('id');

        return WorkflowTransition::query()
            ->where('workflow_id', $workflow->id)
            ->whereNotNull('to_state_id')
            ->get()
            ->map(function (WorkflowTransition $transition) use ($states, $columnName): ?BulkAction {
                $from = $states->get($transition->from_state_id);
                $to = $states->get($transition->to_state_id);

                if ($to === null) {
                    return null;
                }

                $fromName = $from?->name;
                $toName = $to->class_name ?: $to->name;

                return BulkAction::make(Str::slug('transition-'.$transition->name))
                    ->label($transition->label ?: $to->label)
                    ->icon($to->icon)
                    ->color($to->color ?: 'primary')
                    ->requiresConfirmation()
                    ->action(function (Collection $records) use ($fromName, $toName, $columnName): void {
                        $updated = 0;
                        $failures = [];

                        foreach ($records as $record) {
                            if (! method_exists($record, 'transitionTo')) {
                                continue;
                            }

                            if ($fromName !== null && $record->{$columnName} !== $fromName) {
                                continue;
                            }

                            try {
                                $record->transitionTo($toName);
                                $updated++;
                            } catch (WorkflowValidationException $exception) {
                                $failures[] = $record->getKey().': '.collect($exception->summary())
                                    ->map(static fn (array $error): string => $error['message'])
                                    ->implode(', ');
                            } catch (Throwable $exception) {
                                $failures[] = $record->getKey().': '.$exception->getMessage();
                            }
                        }

                        $failed = count($failures);

                        Notification::make()
                            ->color($failed === 0 ? 'success' : ($updated > 0 ? 'warning' : 'danger'))
                            ->title($failed === 0
                                ? __('filament-flow.bulk_action.notification.title.success')
                                : ($updated > 0
                                    ? __('filament-flow.bulk_action.notification.title.partial_success')
                                    : __('filament-flow.bulk_action.notification.title.failure')
                                ))
                            ->body($failed === 0
                                ? trans_choice('filament-flow.bulk_action.notification.body', $updated, [
                                    'count' => $updated,
                                    'total' => $records->count(),
                                ])
                                : collect($failures)->take(5)->implode("\n"))
                            ->send();
                    });
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Generate a single BulkActionGroup containing all state transition actions.
     * Alternative to using make() with spread operator.
     *
     * @param  string  $columnName  The state column name
     * @param  string  $stateClass  The base state class (e.g., OrderState)
     */
    public static function group(string $columnName, string $stateClass): BulkActionGroup
    {
        return BulkActionGroup::make(
            static::generateStateBulkActions($stateClass, $columnName)
        )
            ->label(__('Change Status'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('primary');
    }

    /**
     * Generate StateBulkAction instances for all possible state transitions.
     *
     * This method:
     * 1. Retrieves all transitions from the workflow configuration
     * 2. Creates a StateBulkAction for each transition
     * 3. Configures each action to only show when ALL selected records can perform it
     *
     * @param  string  $stateClass  The base state class
     * @param  string  $attribute  The state attribute name
     * @return array Array of StateBulkAction instances
     */
    protected static function generateStateBulkActions(string $stateClass, string $attribute): array
    {
        $actions = [];

        if (! config('filament-flow.enabled', true)) {
            return $actions;
        }

        try {
            // Get the namespace of the state class to match against
            $stateClassNamespace = (new ReflectionClass($stateClass))->getNamespaceName();

            // Find workflow by loading candidates and filtering in PHP (avoids SQLite LIKE
            // backslash issues)
            $workflow = Workflow::where('state_column', $attribute)
                ->where('is_active', true)
                ->with('states')
                ->get()
                ->first(function ($w) use ($stateClassNamespace) {
                    return $w->states->contains(fn ($s) => str_starts_with($s->class_name ?? '', $stateClassNamespace.'\\'));
                });

            if (! $workflow) {
                return $actions;
            }

            // Get all transitions for this workflow
            $transitions = WorkflowTransition::where('workflow_id', $workflow->id)
                ->with(['fromState', 'toState'])
                ->get();

            // Group transitions by destination state to detect duplicates
            $transitionsByToState = [];
            foreach ($transitions as $transition) {
                $toState = $transition->toState;
                if ($toState) {
                    $toStateId = $toState->id;
                    if (! isset($transitionsByToState[$toStateId])) {
                        $transitionsByToState[$toStateId] = [];
                    }
                    $transitionsByToState[$toStateId][] = $transition;
                }
            }

            foreach ($transitions as $transition) {
                $fromState = $transition->fromState;
                $toState = $transition->toState;

                if (! $fromState || ! $toState) {
                    continue;
                }

                // Determine the from and to state identifiers
                // For PHP states, use the class name; for database states, use the name
                $fromStateIdentifier = $fromState->class_name ?: $fromState->name;
                $toStateIdentifier = $toState->class_name ?: $toState->name;

                // Store values in variables to use in closures
                $toIcon = $toState->icon;
                $toColor = $toState->color;

                // Create label: add "from" state only if there are multiple transitions to the
                // same destination Example: "Processing" (unique) vs "Cancelled (from Pending)"
                // (duplicate)
                $hasDuplicateDestination = count($transitionsByToState[$toState->id]) > 1;
                $toLabel = $hasDuplicateDestination
                    ? $toState->label.' ('.__('from').' '.$fromState->label.')'
                    : $toState->label;

                // Create a simple BulkAction instead of StateBulkAction
                $action = BulkAction::make(Str::slug($fromState->name.'-to-'.$toState->name))
                    ->label($toLabel)
                    ->icon($toIcon)
                    ->color($toColor ?: 'primary')
                    ->requiresConfirmation()
                    ->action(function (Collection $records) use ($fromStateIdentifier, $toStateIdentifier, $attribute) {
                        $updatedCount = 0;
                        $totalCount = $records->count();

                        foreach ($records as $record) {
                            $currentState = $record->{$attribute};

                            // Check if current state matches the "from" state (handle both
                            // State objects and strings)
                            $isMatchingState = false;
                            if (is_string($currentState) && is_string($fromStateIdentifier)) {
                                $isMatchingState = $currentState === $fromStateIdentifier;
                            } elseif ($currentState instanceof State && $fromStateIdentifier instanceof State) {
                                $isMatchingState = $currentState->equals($fromStateIdentifier);
                            } elseif ($currentState instanceof State && is_string($fromStateIdentifier)) {
                                $isMatchingState = get_class($currentState) === $fromStateIdentifier;
                            } elseif (is_string($currentState) && $fromStateIdentifier instanceof State) {
                                $isMatchingState = $currentState === get_class($fromStateIdentifier);
                            }

                            if ($isMatchingState) {
                                // Check when can transition
                                $canTransition = false;

                                if (method_exists($record, 'canTransitionTo')) {
                                    $canTransition = $record->canTransitionTo($toStateIdentifier, $attribute);
                                }

                                if ($canTransition) {
                                    try {
                                        if (method_exists($record, 'transitionTo')) {
                                            $record->transitionTo($toStateIdentifier);
                                            $updatedCount++;
                                        }
                                    } catch (Exception $e) {
                                        report($e);
                                    }
                                }
                            }
                        }

                        // Determine notification status
                        $status = $updatedCount === $totalCount ? 'success' : ($updatedCount > 0 ? 'warning' : 'danger');

                        Notification::make()
                            ->color($status)
                            ->title($status === 'success'
                                ? __('filament-flow.bulk_action.notification.title.success')
                                : ($status === 'warning'
                                    ? __('filament-flow.bulk_action.notification.title.partial_success')
                                    : __('filament-flow.bulk_action.notification.title.failure')
                                ))
                            ->body(trans_choice('filament-flow.bulk_action.notification.body', $updatedCount, [
                                'count' => $updatedCount,
                                'total' => $totalCount,
                            ]))
                            ->send();
                    });

                $actions[] = $action;
            }
        } catch (Exception $e) {
            // If we can't determine transitions, return empty array
            report($e);
        }

        return $actions;
    }
}
