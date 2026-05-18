<?php

namespace RoBYCoNTe\FilamentFlow\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use Spatie\ModelStates\State;
use Throwable;

class SyncStatesCommand extends Command
{
    protected $signature = 'filament-flow:sync-states
        {--workflow= : Sync a specific workflow by name}';

    protected $description = 'Sync PHP state classes with database workflow states';

    protected int $created = 0;

    protected int $updated = 0;

    public function handle(): int
    {
        $query = Workflow::query();

        if ($name = $this->option('workflow')) {
            $query->where('name', $name);
        }

        $workflows = $query->get();

        if ($workflows->isEmpty()) {
            $this->warn($name
                ? "No workflow found with name \"{$name}\"."
                : 'No workflows found.',
            );

            return self::SUCCESS;
        }

        foreach ($workflows as $workflow) {
            $this->syncWorkflow($workflow);
        }

        $this->newLine();
        $this->info("Sync complete: {$this->created} created, {$this->updated} updated.");

        return self::SUCCESS;
    }

    protected function syncWorkflow(Workflow $workflow): void
    {
        $this->info("Syncing workflow: {$workflow->name} (model: {$workflow->model_type})");

        $modelClass = $workflow->model_type;

        if (! class_exists($modelClass)) {
            $this->warn("  Model class {$modelClass} does not exist, skipping.");

            return;
        }

        $stateClass = $this->resolveStateClass($modelClass, $workflow->state_column ?? 'state');

        if (! $stateClass) {
            $this->warn("  No Spatie State cast found on {$modelClass}::{$workflow->state_column}, skipping.");

            return;
        }

        $concreteStates = $this->discoverConcreteStates($stateClass);

        if (empty($concreteStates)) {
            $this->warn("  No concrete state classes found for {$stateClass}.");

            return;
        }

        $stateConfig = $stateClass::config();
        $defaultState = $stateConfig->defaultStateClass;

        foreach ($concreteStates as $concreteState) {
            $morphClass = $concreteState::getMorphClass();
            $isDefault = $defaultState === $concreteState;

            $existing = WorkflowState::where('workflow_id', $workflow->id)
                ->where('name', $morphClass)
                ->first();

            if ($existing) {
                $changes = [];
                if ($existing->class_name !== $concreteState) {
                    $changes['class_name'] = $concreteState;
                }
                if ($existing->is_initial !== $isDefault) {
                    $changes['is_initial'] = $isDefault;
                }

                if (! empty($changes)) {
                    $existing->update($changes);
                    $this->updated++;
                    $this->line("  Updated: {$morphClass}");
                } else {
                    $this->line("  Unchanged: {$morphClass}");
                }
            } else {
                $label = class_basename($concreteState);
                $label = preg_replace('/State$/', '', $label);
                $label = preg_replace('/([a-z])([A-Z])/', '$1 $2', $label);

                WorkflowState::create([
                    'workflow_id' => $workflow->id,
                    'name' => $morphClass,
                    'label' => $label,
                    'class_name' => $concreteState,
                    'is_initial' => $isDefault,
                    'is_final' => false,
                    'sort_order' => 0,
                ]);

                $this->created++;
                $this->line("  Created: {$morphClass} (label: {$label})");
            }
        }
    }

    /**
     * Resolve the Spatie State base class from a model's cast definition.
     */
    protected function resolveStateClass(string $modelClass, string $stateColumn): ?string
    {
        try {
            /** @var Model $model */
            $model = new $modelClass;
            $casts = $model->getCasts();

            $cast = $casts[$stateColumn] ?? null;

            if (! $cast) {
                return null;
            }

            // Handle FlexibleStateCast:StateClass format
            if (str_contains($cast, ':')) {
                $parts = explode(':', $cast);
                $cast = end($parts);
            }

            if (class_exists($cast) && is_subclass_of($cast, State::class)) {
                return $cast;
            }

            return null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Discover all concrete state subclasses for a given abstract state class.
     *
     * @return array<string>
     */
    protected function discoverConcreteStates(string $stateClass): array
    {
        $states = [];

        try {
            $config = $stateClass::config();

            // Extract states from registered transitions
            $registeredStates = $config->registeredStates ?? [];

            if (! empty($registeredStates)) {
                foreach ($registeredStates as $state) {
                    if (class_exists($state) && is_subclass_of($state, State::class)) {
                        $reflection = new ReflectionClass($state);
                        if (! $reflection->isAbstract()) {
                            $states[] = $state;
                        }
                    }
                }
            }

            // If no registered states found, scan the directory of the base state class
            if (empty($states)) {
                $states = $this->discoverFromDirectory($stateClass);
            }
        } catch (Throwable) {
            $states = $this->discoverFromDirectory($stateClass);
        }

        return array_unique($states);
    }

    /**
     * Discover concrete state classes by scanning the directory of the base state class.
     *
     * @return array<string>
     */
    protected function discoverFromDirectory(string $stateClass): array
    {
        $states = [];

        try {
            $reflection = new ReflectionClass($stateClass);
            $directory = dirname($reflection->getFileName());
            $namespace = $reflection->getNamespaceName();

            foreach (glob($directory.'/*.php') as $file) {
                $className = $namespace.'\\'.pathinfo($file, PATHINFO_FILENAME);

                if (! class_exists($className)) {
                    continue;
                }

                $ref = new ReflectionClass($className);

                if (! $ref->isAbstract() && $ref->isSubclassOf(State::class)) {
                    $states[] = $className;
                }
            }
        } catch (Throwable) {
            // silently skip
        }

        return $states;
    }
}
