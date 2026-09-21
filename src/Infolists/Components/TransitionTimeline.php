<?php

namespace RoBYCoNTe\FilamentFlow\Infolists\Components;

use Filament\Infolists\Components\Entry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateTransition;
use RoBYCoNTe\FilamentFlow\Support\AccessRuleEvaluator;

class TransitionTimeline extends Entry
{
    protected string $view = 'filament-flow::infolists.transition-timeline';

    protected int $limit = 10;

    protected bool $showAllForAdmins = true;

    protected bool $filterByAccess = true;

    public static function make(?string $name = 'transition-timeline'): static
    {
        return parent::make($name ?? 'transition-timeline');
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function showAllForAdmins(bool $show = true): static
    {
        $this->showAllForAdmins = $show;

        return $this;
    }

    public function filterByAccess(bool $filter = true): static
    {
        $this->filterByAccess = $filter;

        return $this;
    }

    public function getTimeline(): Collection
    {
        $record = $this->getRecord();

        if (! $record instanceof Model) {
            return collect();
        }

        $query = WorkflowStateTransition::where('transitionable_type', get_class($record))
            ->where('transitionable_id', $record->getKey())
            ->visible()
            ->with(['transition'])
            ->orderByDesc('created_at');

        $entries = $query->limit($this->limit)->get();

        // If access filtering is enabled, filter based on user role
        if ($this->filterByAccess && ! $this->isAdmin()) {
            $entries = $entries->filter(fn ($entry) => $entry->is_visible);
        }

        return $entries;
    }

    public function getTotalCount(): int
    {
        $record = $this->getRecord();

        if (! $record instanceof Model) {
            return 0;
        }

        return WorkflowStateTransition::where('transitionable_type', get_class($record))
            ->where('transitionable_id', $record->getKey())
            ->visible()
            ->count();
    }

    protected function isAdmin(): bool
    {
        if (! $this->showAllForAdmins) {
            return false;
        }

        $user = Auth::user();

        if (! $user) {
            return false;
        }

        // Same resolver as the access rules: a host that keeps super admins
        // elsewhere (tenant roles, custom resolver) is honoured here too.
        return app(AccessRuleEvaluator::class)->isSuperAdmin($user);
    }

    public function getLimit(): int
    {
        return $this->limit;
    }
}
