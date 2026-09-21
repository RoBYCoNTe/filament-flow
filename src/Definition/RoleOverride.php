<?php

namespace RoBYCoNTe\FilamentFlow\Definition;

use RoBYCoNTe\FilamentFlow\Definition\Enums\Mutability;
use RoBYCoNTe\FilamentFlow\Definition\Enums\Visibility;

/**
 * Role-specific refinement of a StateField rule.
 *
 * Returned by StateField::forRole(); every setter records the value for that
 * role and returns the override, so the chain keeps reading naturally:
 *
 *     StateField::make('costs.amount')->locked()
 *         ->forRole('reviewer')->editable()
 *         ->forRole('admin')->visible()->editable();
 *
 * Only the attributes explicitly set are persisted: `null` means "no override,
 * keep the state field value". At runtime the last matching role wins.
 */
final class RoleOverride
{
    private ?string $visibility = null;

    private ?string $mutability = null;

    private ?bool $required = null;

    public function __construct(
        private readonly StateField $field,
        private readonly string $roleName,
    ) {}

    public function visible(): self
    {
        $this->visibility = Visibility::Visible->value;

        return $this;
    }

    public function hidden(): self
    {
        $this->visibility = Visibility::Hidden->value;

        return $this;
    }

    public function readonly(): self
    {
        $this->mutability = Mutability::Readonly->value;

        return $this;
    }

    public function editable(): self
    {
        $this->mutability = Mutability::Editable->value;

        return $this;
    }

    public function locked(): self
    {
        $this->mutability = Mutability::Locked->value;

        return $this;
    }

    public function required(bool $required = true): self
    {
        $this->required = $required;

        return $this;
    }

    /** Continue with another role on the same state field. */
    public function field(): StateField
    {
        return $this->field;
    }

    public function roleName(): string
    {
        return $this->roleName;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'role_name' => $this->roleName,
            'visibility' => $this->visibility,
            'mutability' => $this->mutability,
            'is_required' => $this->required,
        ];
    }
}
