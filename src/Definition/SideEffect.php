<?php

namespace RoBYCoNTe\FilamentFlow\Definition;

use RoBYCoNTe\FilamentFlow\Definition\Enums\SideEffectType;

/**
 * Something a transition writes when it runs: a field, a timestamp, an increment, a cleared
 * value, a child application, or a class the host owns.
 */
final class SideEffect
{
    private ?string $fieldName = null;

    private ?string $valueExpression = null;

    private int $sort = 0;

    private bool $active = true;

    private function __construct(private readonly string $type) {}

    public static function setField(string $fieldName, string $expression): self
    {
        return (new self(SideEffectType::SetField->value))
            ->forField($fieldName)
            ->value($expression);
    }

    public static function setTimestamp(string $fieldName, string $expression = 'now'): self
    {
        return (new self(SideEffectType::SetTimestamp->value))
            ->forField($fieldName)
            ->value($expression);
    }

    public static function clearField(string $fieldName): self
    {
        return (new self(SideEffectType::ClearField->value))->forField($fieldName);
    }

    public static function increment(string $fieldName, int $amount = 1): self
    {
        return (new self(SideEffectType::Increment->value))
            ->forField($fieldName)
            ->value((string) $amount);
    }

    public static function customClass(string $className): self
    {
        return (new self(SideEffectType::CustomClass->value))->value($className);
    }

    /** @param array<string,mixed> $config */
    public static function createChildApplication(array $config): self
    {
        return (new self(SideEffectType::CreateChildApplication->value))
            ->value(json_encode($config, JSON_THROW_ON_ERROR));
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return (new self((string) ($data['effect_type'] ?? SideEffectType::SetField->value)))
            ->forField((string) ($data['field_name'] ?? ''))
            ->value($data['value_expression'] ?? null)
            ->sort((int) ($data['sort_order'] ?? 0))
            ->active((bool) ($data['is_active'] ?? true));
    }

    public function forField(string $fieldName): self
    {
        $this->fieldName = $fieldName;

        return $this;
    }

    public function value(?string $expression): self
    {
        $this->valueExpression = $expression;

        return $this;
    }

    public function sort(int $sort): self
    {
        $this->sort = $sort;

        return $this;
    }

    public function active(bool $active = true): self
    {
        $this->active = $active;

        return $this;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'effect_type' => $this->type,
            'field_name' => $this->fieldName ?? '',
            'value_expression' => $this->valueExpression,
            'sort_order' => $this->sort,
            'is_active' => $this->active,
        ];
    }
}
