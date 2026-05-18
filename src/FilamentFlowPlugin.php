<?php

namespace RoBYCoNTe\FilamentFlow;

use BackedEnum;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Foundation\Auth\User;
use RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowNotificationResource;
use RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowResource;
use RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowStateResource;
use RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowTransitionResource;
use UnitEnum;

class FilamentFlowPlugin implements Plugin
{
    protected bool $hasWorkflowResource = true;

    protected bool $tenantAware = false;

    protected ?string $tenantModel = null;

    protected ?string $tenantColumn = null;

    protected ?string $navigationLabel = null;

    protected string|null|UnitEnum $navigationGroup = null;

    protected string|null|BackedEnum $navigationIcon = null;

    protected ?int $navigationSort = null;

    protected ?string $navigationParentItem = null;

    /** @var Closure|null Authorization callback: fn(User): bool */
    protected ?Closure $authorizeUsing = null;

    protected static ?self $instance = null;

    public function getId(): string
    {
        return 'filament-flow';
    }

    public function register(Panel $panel): void
    {
        static::$instance = $this;

        if ($this->hasWorkflowResource) {
            $panel->resources([
                WorkflowResource::class,
                WorkflowStateResource::class,
                WorkflowTransitionResource::class,
                WorkflowNotificationResource::class,
            ]);
        }

        if ($this->tenantModel) {
            config(['filament-flow.tenant_model' => $this->tenantModel]);
        }

        if ($this->tenantColumn) {
            config(['filament-flow.tenant_foreign_key' => $this->tenantColumn]);
        }
    }

    public function boot(Panel $panel): void {}

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public static function getInstance(): ?static
    {
        return static::$instance;
    }

    public function withoutWorkflowResource(): static
    {
        $this->hasWorkflowResource = false;

        return $this;
    }

    public function withWorkflowResource(): static
    {
        $this->hasWorkflowResource = true;

        return $this;
    }

    public function tenantAware(bool $enabled = true): static
    {
        $this->tenantAware = $enabled;

        return $this;
    }

    public function global(): static
    {
        return $this->tenantAware(false);
    }

    public function tenantModel(string $model): static
    {
        $this->tenantModel = $model;

        return $this;
    }

    public function tenantColumn(string $column): static
    {
        $this->tenantColumn = $column;

        return $this;
    }

    public function isTenantAware(): bool
    {
        return $this->tenantAware;
    }

    public function getTenantModel(): ?string
    {
        return $this->tenantModel ?? config('filament-flow.tenant_model');
    }

    public function getTenantColumn(): string
    {
        return $this->tenantColumn ?? config('filament-flow.tenant_foreign_key', 'tenant_id');
    }

    public function navigationLabel(string $label): static
    {
        $this->navigationLabel = $label;

        return $this;
    }

    public function navigationGroup(string|UnitEnum|null $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function navigationIcon(string|BackedEnum|null $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function navigationParentItem(?string $parentItem): static
    {
        $this->navigationParentItem = $parentItem;

        return $this;
    }

    public function getNavigationLabel(): ?string
    {
        return $this->navigationLabel;
    }

    public function getNavigationGroup(): string|UnitEnum|null
    {
        return $this->navigationGroup;
    }

    public function getNavigationIcon(): string|BackedEnum|null
    {
        return $this->navigationIcon;
    }

    public function getNavigationSort(): ?int
    {
        return $this->navigationSort;
    }

    public function getNavigationParentItem(): ?string
    {
        return $this->navigationParentItem;
    }

    /**
     * Set a callback to authorize access to workflow management resources.
     *
     * Example: ->authorizeUsing(fn (User $user) => $user->isSuperAdmin())
     */
    public function authorizeUsing(Closure $callback): static
    {
        $this->authorizeUsing = $callback;

        return $this;
    }

    /**
     * Check if the given user is authorized to access workflow resources.
     * Returns true if no authorization callback is set (default: open access).
     */
    public function isAuthorized(?User $user = null): bool
    {
        if (! $this->authorizeUsing) {
            return true;
        }

        $user ??= auth()->user();

        if (! $user) {
            return false;
        }

        return ($this->authorizeUsing)($user);
    }
}
