<?php

namespace RoBYCoNTe\FilamentFlow\Tables\Filters;

use Closure;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use RoBYCoNTe\FilamentFlow\Services\StateService;

class StateSelectFilter extends SelectFilter
{
    /** Owner of the scoped workflow (a scheme, for example). */
    protected int|Closure|null $tenantId = null;

    /**
     * Scope the options to the workflow of a tenant/owner: hosts that keep one
     * workflow per owner need it, otherwise the filter would find no state.
     */
    public function tenant(int|Closure|null $tenantId): static
    {
        $this->tenantId = $tenantId;

        return $this;
    }

    public function getTenantId(): ?int
    {
        $tenantId = $this->evaluate($this->tenantId);

        return $tenantId === null ? null : (int) $tenantId;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->options(function (Table $table) {
            $service = app(StateService::class);

            return $service->getAllStatesForModel($table->getModel(), $this->getAttribute(), $this->getTenantId());
        });
    }
}
