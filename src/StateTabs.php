<?php

namespace RoBYCoNTe\FilamentFlow;

use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Services\StateService;

/**
 * The tabs of a table that group the records by state: the attribute they read, the badge they
 * draw, and the query behind each tab.
 */
class StateTabs
{
    protected ?string $attribute = null;

    protected bool $includeBadge = true;

    protected bool $includeAll = false;

    protected ?Builder $baseQuery = null;

    protected ?int $tenantId = null;

    public function __construct(
        protected Model|string $model,
    ) {}

    public static function make(Model|string $model): self
    {
        return new self($model);
    }

    public function attribute(string $attribute): self
    {
        $this->attribute = $attribute;

        return $this;
    }

    public function badge(bool $includeBadge = true): self
    {
        $this->includeBadge = $includeBadge;

        return $this;
    }

    /**
     * Set a base query for badge counts (e.g. tenant-scoped).
     *
     * Without this, badge counts use an unscoped query which may include
     * records from other tenants in multi-tenant applications.
     */
    public function query(Builder $query): self
    {
        $this->baseQuery = $query;

        return $this;
    }

    public function includeAll(bool $includeAll = true): self
    {
        $this->includeAll = $includeAll;

        return $this;
    }

    /**
     * Owner of the scoped workflow (a scheme, for example): without it only the
     * global workflow would be found.
     */
    public function tenant(?int $tenantId): self
    {
        $this->tenantId = $tenantId;

        return $this;
    }

    /** Model class of the tabs, resolved from the class or the instance. */
    protected function getModelClass(): string
    {
        return $this->model instanceof Model ? get_class($this->model) : $this->model;
    }

    /** Tenant of the workflow: explicit, or read from the model instance. */
    protected function getTenantId(): ?int
    {
        if ($this->tenantId !== null) {
            return $this->tenantId;
        }

        return $this->model instanceof Model && method_exists($this->model, 'getWorkflowTenantId')
            ? $this->model->getWorkflowTenantId()
            : null;
    }

    /** @noinspection PhpUndefinedMethodInspection */
    public function getAttribute(): string
    {
        if ($this->attribute !== null) {
            return $this->attribute;
        }

        if (is_string($this->model) && method_exists($this->model, 'getDefaultStates')) {
            $defaultStates = $this->model::getDefaultStates();
            if ($defaultStates?->isNotEmpty()) {
                return (string) array_key_first($defaultStates->toArray());
            }
        }

        return 'state';
    }

    /**
     * @return array<int, Tab>
     *
     * @noinspection PhpUndefinedMethodInspection
     */
    protected function generateTabs(): array
    {
        $tabs = [];

        if ($this->includeAll) {
            $allTab = Tab::make()
                ->label(__('filament-flow.tabs.all'));

            if ($this->includeBadge) {
                $allTab->badge($this->getBaseQuery()->count());
            }

            $tabs[] = $allTab;
        }

        $stateService = app(StateService::class);
        $allStates = $stateService->getAllStatesForModel($this->getModelClass(), $this->getAttribute(), $this->getTenantId());

        $phpStates = $this->resolvePhpStates();

        foreach ($allStates as $stateKey => $stateLabel) {
            $tabs[] = isset($phpStates[$stateKey])
                ? $this->buildPhpStateTab($stateKey, $phpStates[$stateKey], $stateService)
                : $this->buildDatabaseStateTab($stateKey, $stateLabel, $stateService);
        }

        return $tabs;
    }

    private function resolvePhpStates(): array
    {
        try {
            $stateClass = $this->getAbstractStateClass();

            return class_exists($stateClass) ? $stateClass::all() : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function buildPhpStateTab(string $stateKey, string $stateClass, StateService $stateService): Tab
    {
        $state = new $stateClass(null);

        $tab = Tab::make($stateKey)
            ->key($stateKey)
            ->label($state->getLabel())
            ->icon($state->getIcon())
            ->modifyQueryUsing(fn (Builder $query) => $query->whereState($this->getAttribute(), $stateKey));

        if ($this->includeBadge) {
            $tab->badgeColor($state->getColor())
                ->badgeTooltip($state->getDescription())
                ->badge($this->getBaseQuery()->whereState($this->getAttribute(), $stateKey)->count());
        }

        return $tab;
    }

    private function buildDatabaseStateTab(string $stateKey, string $stateLabel, StateService $stateService): Tab
    {
        $metadata = $stateService->getStateMetadata($this->getModelClass(), $stateKey, $this->getAttribute(), $this->getTenantId());

        // The key is what identifies the state: hosts that render the tabs
        // themselves (a plain page with a table) need it to filter the query.
        $tab = Tab::make($stateKey)
            ->key($stateKey)
            ->label($metadata['label'] ?? $stateLabel)
            ->icon($metadata['icon'] ?? null)
            ->modifyQueryUsing(fn (Builder $query) => $query->where($this->getAttribute(), $stateKey));

        if ($this->includeBadge && $metadata) {
            $tab->badgeColor($metadata['color'] ?? 'gray')
                ->badgeTooltip($metadata['description'] ?? null)
                ->badge($this->getBaseQuery()->where($this->getAttribute(), $stateKey)->count());
        }

        return $tab;
    }

    protected function getAbstractStateClass(): string
    {
        $cast = app($this->model)->getCasts()[$this->getAttribute()];

        if (str_contains($cast, 'FlexibleStateCast:')) {
            return explode(':', $cast)[1] ?? $cast;
        }

        return $cast;
    }

    protected function getBaseQuery(): Builder
    {
        return $this->baseQuery ? clone $this->baseQuery : $this->getModelClass()::query();
    }

    /** @return array<int, Tab> */
    public function toArray(): array
    {
        return $this->generateTabs();
    }

    /**
     * The tabs as plain arrays (state, label, color, icon, count).
     *
     * Hosts that render the tabs themselves — a page with a table, which filters
     * its own query — can use this instead of the Filament Tab objects, whose
     * accessors need a mounted container. The counts use the base query, so they
     * respect the scope of the caller.
     *
     * @return list<array{state:string,label:string,color:?string,icon:?string,count:?int}>
     */
    public function data(): array
    {
        $service = app(StateService::class);
        $attribute = $this->getAttribute();
        $modelClass = $this->getModelClass();
        $tenantId = $this->getTenantId();

        $tabs = [];

        foreach ($service->getAllStatesForModel($modelClass, $attribute, $tenantId) as $state => $label) {
            $metadata = $service->getStateMetadata($modelClass, (string) $state, $attribute, $tenantId);

            $tabs[] = [
                'state' => (string) $state,
                'label' => (string) ($metadata['label'] ?? $label),
                'color' => $metadata['color'] ?? null,
                'icon' => $metadata['icon'] ?? null,
                'count' => $this->includeBadge
                    ? $this->getBaseQuery()->where($attribute, $state)->count()
                    : null,
            ];
        }

        return $tabs;
    }
}
