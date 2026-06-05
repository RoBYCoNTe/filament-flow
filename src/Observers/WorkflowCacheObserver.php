<?php

namespace RoBYCoNTe\FilamentFlow\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Support\WorkflowCacheManager;

/**
 * Invalidates workflow-related caches when configuration models are modified
 * through the admin panel.
 *
 * Registered on: Workflow, WorkflowState, WorkflowTransition,
 *                 WorkflowStateAccessRule, WorkflowStateField,
 *                 WorkflowTransitionPermission, WorkflowTransitionField,
 *                 WorkflowTransitionValidationRule, WorkflowTransitionSideEffect.
 */
class WorkflowCacheObserver
{
    public function saved(Model $model): void
    {
        $this->invalidate($model);
    }

    public function deleted(Model $model): void
    {
        $this->invalidate($model);
    }

    protected function invalidate(Model $model): void
    {
        if (! config('filament-flow.cache.enabled', true)) {
            return;
        }

        $cache = new WorkflowCacheManager;
        $tags = $this->resolveTags($model);

        if (! empty($tags)) {
            $cache->flushTags($tags);
        }

        if ($model instanceof Workflow) {
            $this->forgetFindForModelKeys($model);
        }
    }

    /**
     * Directly forget findForModel cache keys for a given workflow.
     * These keys use model_type + state_column + tenant_id patterns
     * rather than workflow ID, so tag-based invalidation alone is not enough.
     */
    protected function forgetFindForModelKeys(Workflow $workflow): void
    {
        $prefix = config('filament-flow.cache.prefix', 'filament-flow');
        $tenantId = $workflow->tenant_id;
        $store = config('filament-flow.cache.store');

        $tenantKey = "{$prefix}:workflow:{$workflow->model_type}:{$workflow->state_column}:{$tenantId}";
        Cache::store($store)->forget($tenantKey);

        $globalKey = "{$prefix}:workflow:{$workflow->model_type}:{$workflow->state_column}:";
        Cache::store($store)->forget($globalKey);
    }

    /**
     * @return array<string>
     */
    protected function resolveTags(Model $model): array
    {
        $cache = new WorkflowCacheManager;

        if ($model instanceof Workflow) {
            return [$cache->workflowTag($model->id)];
        }

        if ($model instanceof WorkflowState) {
            return [
                $cache->workflowTag($model->workflow_id),
                $cache->stateTag($model->workflow_id),
                $cache->accessTag($model->id),
                $cache->fieldsTag($model->workflow_id),
            ];
        }

        if ($model instanceof WorkflowTransition) {
            return [
                $cache->workflowTag($model->workflow_id),
                $cache->stateTag($model->workflow_id),
            ];
        }

        $workflowId = $this->resolveWorkflowId($model);

        if ($workflowId !== null) {
            return [
                $cache->workflowTag($workflowId),
                $cache->accessTag($this->resolveStateId($model) ?? $workflowId),
                $cache->fieldsTag($workflowId),
            ];
        }

        return [];
    }

    protected function resolveWorkflowId(Model $model): ?int
    {
        return match (true) {
            method_exists($model, 'workflow_id') => $model->getAttribute('workflow_id') ?? null,
            $model->getAttribute('workflow_id') !== null => $model->getAttribute('workflow_id'),
            method_exists($model, 'workflow') && $model->relationLoaded('workflow') => $model->workflow?->id,
            method_exists($model, 'transition') && $model->relationLoaded('transition') => $model->transition?->workflow_id,
            method_exists($model, 'state') && $model->relationLoaded('state') => $model->state?->workflow_id,
            default => null,
        };
    }

    protected function resolveStateId(Model $model): ?int
    {
        return match (true) {
            $model->getAttribute('state_id') !== null => $model->getAttribute('state_id'),
            method_exists($model, 'transition') && $model->relationLoaded('transition') => $model->transition?->from_state_id ?? $model->transition?->to_state_id,
            default => null,
        };
    }
}
