<?php

namespace RoBYCoNTe\FilamentFlow\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;

class FormulaEditorComponent extends Field
{
    protected string $view = 'filament-flow::forms.components.formula-editor';

    protected string $scope = 'workflow';

    protected ?string $contextType = null;

    protected string|int|Closure|null $contextId = null;

    protected string $height = '120px';

    public function scope(string $scope): static
    {
        $this->scope = $scope;

        return $this;
    }

    public function contextType(string $modelClass): static
    {
        $this->contextType = $modelClass;

        return $this;
    }

    public function contextId(string|int|Closure|null $id): static
    {
        $this->contextId = $id;

        return $this;
    }

    public function height(string $height): static
    {
        $this->height = $height;

        return $this;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function getContextType(): ?string
    {
        return $this->contextType;
    }

    public function getContextId(): string|int|null
    {
        return $this->evaluate($this->contextId);
    }

    public function getHeight(): string
    {
        return $this->height;
    }

    public function getCompletionsUrl(): string
    {
        return route('filament-flow.formula-completions', [
            'scope' => $this->getScope(),
            'context_type' => $this->getContextType() ?? '',
            'context_id' => $this->getContextId() ?? '',
        ]);
    }
}
