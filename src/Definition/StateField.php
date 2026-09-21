<?php

namespace RoBYCoNTe\FilamentFlow\Definition;

use Closure;
use RoBYCoNTe\FilamentFlow\Definition\Enums\Mutability;
use RoBYCoNTe\FilamentFlow\Definition\Enums\Visibility;

/**
 * Per-state visibility / mutability of a scheme field.
 */
final class StateField
{
    private string $visibility = Visibility::Visible->value;

    private string $mutability = Mutability::Editable->value;

    private bool $required = false;

    /** @var list<string> */
    private array $validationRules = [];

    private int $sort = 0;

    /** @var array<string,RoleOverride> keyed by role name */
    private array $roleOverrides = [];

    private function __construct(private readonly string $fieldName) {}

    public static function make(string $fieldName): self
    {
        return new self($fieldName);
    }

    /**
     * Refine this rule for a role.
     *
     * The callback receives the RoleOverride and the chain keeps returning the
     * StateField, so a role refinement never changes the type of the expression:
     *
     *     StateField::make('costs.amount')->locked()
     *         ->forRole('reviewer', fn (RoleOverride $r) => $r->editable())
     *         ->forRole('admin', fn (RoleOverride $r) => $r->visible()->required());
     */
    public function forRole(string $roleName, ?Closure $configure = null): self
    {
        $override = $this->roleOverrides[$roleName] ??= new RoleOverride($this, $roleName);

        if ($configure !== null) {
            $configure($override);
        }

        return $this;
    }

    /** @return array<int,RoleOverride> */
    public function roleOverrides(): array
    {
        $overrides = $this->roleOverrides;
        ksort($overrides);

        return array_values($overrides);
    }

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

    /** @param list<string> $rules */
    public function validationRules(array $rules): self
    {
        $this->validationRules = array_values($rules);

        return $this;
    }

    public function sort(int $sort): self
    {
        $this->sort = $sort;

        return $this;
    }

    public function name(): string
    {
        return $this->fieldName;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'field_name' => $this->fieldName,
            'visibility' => $this->visibility,
            'mutability' => $this->mutability,
            'is_required' => $this->required,
            'validation_rules' => $this->validationRules !== [] ? $this->validationRules : null,
            'sort_order' => $this->sort,
            'role_overrides' => array_map(
                static fn (RoleOverride $override): array => $override->toArray(),
                $this->roleOverrides(),
            ),
        ];
    }

    /**
     * Restore a state field from an exported array (inverse of toArray()).
     *
     * @param  array<string,mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $field = (new self((string) ($data['field_name'] ?? '')))
            ->required((bool) ($data['is_required'] ?? false))
            ->sort((int) ($data['sort_order'] ?? 0))
            ->validationRules(array_values((array) ($data['validation_rules'] ?? [])));

        if (($data['visibility'] ?? 'visible') === Visibility::Hidden->value) {
            $field->hidden();
        }

        match ((string) ($data['mutability'] ?? 'editable')) {
            Mutability::Readonly->value => $field->readonly(),
            Mutability::Locked->value => $field->locked(),
            default => $field->editable(),
        };

        foreach ($data['role_overrides'] ?? [] as $override) {
            $field->forRole((string) ($override['role_name'] ?? ''), function (RoleOverride $role) use ($override): void {
                if (($override['visibility'] ?? null) === Visibility::Hidden->value) {
                    $role->hidden();
                } elseif (($override['visibility'] ?? null) === Visibility::Visible->value) {
                    $role->visible();
                }

                match ($override['mutability'] ?? null) {
                    Mutability::Readonly->value => $role->readonly(),
                    Mutability::Locked->value => $role->locked(),
                    Mutability::Editable->value => $role->editable(),
                    default => null,
                };

                if (array_key_exists('is_required', $override) && $override['is_required'] !== null) {
                    $role->required((bool) $override['is_required']);
                }
            });
        }

        return $field;
    }
}
