<?php

namespace RoBYCoNTe\FilamentFlow\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RoBYCoNTe\FilamentFlow\Models\WorkflowScheduledCheck;
use RoBYCoNTe\FilamentFlow\Models\WorkflowScheduledCheckLog;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use Throwable;

class ScheduledCheckRunner
{
    protected int $processed = 0;

    protected int $triggered = 0;

    protected int $errors = 0;

    public function __construct(
        protected ConditionEvaluator $conditionEvaluator,
        protected SideEffectExecutor $sideEffectExecutor,
    ) {}

    /**
     * Run all active, due scheduled checks.
     *
     * @return array{processed: int, triggered: int, errors: int}
     */
    public function runAll(): array
    {
        $this->processed = 0;
        $this->triggered = 0;
        $this->errors = 0;

        $checks = WorkflowScheduledCheck::where('is_active', true)
            ->with(['workflow', 'state'])
            ->get();

        foreach ($checks as $check) {
            if (! $check->isDue()) {
                continue;
            }

            $this->processCheck($check);

            $check->update(['last_checked_at' => now()]);
        }

        return [
            'processed' => $this->processed,
            'triggered' => $this->triggered,
            'errors' => $this->errors,
        ];
    }

    protected function processCheck(WorkflowScheduledCheck $check): void
    {
        $workflow = $check->workflow;

        if (! $workflow || ! $workflow->is_active) {
            return;
        }

        $modelClass = $workflow->model_type;

        if (! class_exists($modelClass)) {
            Log::warning("ScheduledCheckRunner: model class {$modelClass} not found for check #{$check->id}");

            return;
        }

        $stateColumn = $workflow->state_column ?? 'state';

        // Build query for records in the relevant state
        $query = $modelClass::query();

        if ($check->state_id) {
            $stateName = $check->state?->name;
            if ($stateName) {
                $query->where($stateColumn, $stateName);
            }
        }

        // Process each matching record
        $query->chunkById(100, function ($records) use ($check, $modelClass) {
            foreach ($records as $record) {
                $this->processRecord($check, $record, $modelClass);
            }
        });
    }

    protected function processRecord(WorkflowScheduledCheck $check, Model $record, string $modelClass): void
    {
        $this->processed++;

        // Check once_per_record constraint
        if ($check->hasAlreadyExecutedFor($modelClass, $record->getKey())) {
            $this->logExecution($check, $record, 'already_executed');

            return;
        }

        try {
            // Evaluate condition
            $conditionMet = $this->evaluateCondition($check, $record);

            if (! $conditionMet) {
                $this->logExecution($check, $record, 'skipped');

                return;
            }

            // Execute action
            $this->executeAction($check, $record);

            $this->triggered++;
            $this->logExecution($check, $record, 'triggered');
        } catch (Throwable $e) {
            $this->errors++;
            $this->logExecution($check, $record, 'error', ['error' => $e->getMessage()]);
            report($e);
        }
    }

    protected function evaluateCondition(WorkflowScheduledCheck $check, Model $record): bool
    {
        $config = $check->condition_config;

        return match ($check->condition_type) {
            'date_offset' => $this->evaluateDateOffset($record, $config),
            'field_compare' => $this->evaluateFieldCompare($record, $config),
            'custom_class' => $this->evaluateCustomClass($record, $config),
            default => false,
        };
    }

    /**
     * Evaluate a date_offset condition.
     *
     * Config: {"field": "due_date", "offset_days": -2, "operator": "<="}
     * Example: triggers when due_date - 2 days <= now (i.e., 2 days before due_date)
     */
    protected function evaluateDateOffset(Model $record, array $config): bool
    {
        $field = $config['field'] ?? null;
        $offsetDays = $config['offset_days'] ?? 0;
        $operator = $config['operator'] ?? '<=';

        if (! $field || ! $record->{$field}) {
            return false;
        }

        $targetDate = Carbon::parse($record->{$field})->addDays($offsetDays);

        return match ($operator) {
            '<=' => now()->gte($targetDate),
            '>=' => now()->lte($targetDate),
            '=' => now()->isSameDay($targetDate),
            '<' => now()->gt($targetDate),
            '>' => now()->lt($targetDate),
            default => false,
        };
    }

    /**
     * Evaluate a field_compare condition using the ConditionEvaluator.
     *
     * Config: {"conditions": [{"field": "status", "operator": "=", "value": "open"}]}
     */
    protected function evaluateFieldCompare(Model $record, array $config): bool
    {
        $conditions = $config['conditions'] ?? [];

        return $this->conditionEvaluator->evaluate($record, $conditions);
    }

    /**
     * Evaluate a custom_class condition.
     *
     * Config: {"class": "App\\Checks\\CustomCondition"}
     * The class must implement an evaluate(Model $record): bool method.
     */
    protected function evaluateCustomClass(Model $record, array $config): bool
    {
        $className = $config['class'] ?? null;

        if (! $className || ! class_exists($className)) {
            return false;
        }

        $instance = app($className);

        if (method_exists($instance, 'evaluate')) {
            return $instance->evaluate($record);
        }

        return false;
    }

    protected function executeAction(WorkflowScheduledCheck $check, Model $record): void
    {
        $config = $check->action_config;

        match ($check->action_type) {
            'notification' => $this->executeNotificationAction($record, $config),
            'transition' => $this->executeTransitionAction($record, $config),
            'side_effect' => $this->executeSideEffectAction($record, $config, $check),
            default => null,
        };
    }

    /**
     * Execute a notification action.
     *
     * Config: {"notification_id": 5} or {"channel": "mail", "template": "..."}
     */
    protected function executeNotificationAction(Model $record, array $config): void
    {
        $notificationService = app(NotificationService::class);

        $notificationId = $config['notification_id'] ?? null;
        if ($notificationId) {
            $notificationService->triggerById($notificationId, $record);
        }
    }

    /**
     * Execute a transition action.
     *
     * Config: {"to_state": "expired", "force": true}
     */
    protected function executeTransitionAction(Model $record, array $config): void
    {
        $toState = $config['to_state'] ?? null;
        $force = $config['force'] ?? true;

        if (! $toState || ! method_exists($record, 'transitionTo')) {
            return;
        }

        if ($force && method_exists($record, 'forceTransitionTo')) {
            $record->forceTransitionTo($toState);
        } else {
            $record->transitionTo($toState);
        }
    }

    /**
     * Execute a side_effect action using a transition's configured side effects.
     *
     * Config: {"transition_id": 10}
     */
    protected function executeSideEffectAction(Model $record, array $config, WorkflowScheduledCheck $check): void
    {
        $transitionId = $config['transition_id'] ?? null;

        if (! $transitionId) {
            return;
        }

        $transition = WorkflowTransition::find($transitionId);

        if ($transition) {
            $this->sideEffectExecutor->execute($record, $transition);
        }
    }

    protected function logExecution(WorkflowScheduledCheck $check, Model $record, string $result, ?array $metadata = null): void
    {
        WorkflowScheduledCheckLog::create([
            'check_id' => $check->id,
            'model_type' => get_class($record),
            'model_id' => $record->getKey(),
            'result' => $result,
            'metadata' => $metadata,
            'executed_at' => now(),
        ]);
    }

    public function getProcessed(): int
    {
        return $this->processed;
    }

    public function getTriggered(): int
    {
        return $this->triggered;
    }

    public function getErrors(): int
    {
        return $this->errors;
    }
}
