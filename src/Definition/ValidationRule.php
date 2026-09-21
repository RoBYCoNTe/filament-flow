<?php

namespace RoBYCoNTe\FilamentFlow\Definition;

final class ValidationRule
{
    public const TYPE_LARAVEL = 'laravel';

    public const TYPE_REGISTRY = 'registry';

    public const TYPE_EXPRESSION = 'expression';

    /** @var list<string> */
    private array $rules = [];

    private ?string $message = null;

    private int $sort = 0;

    private string $type = self::TYPE_LARAVEL;

    /** Formula evaluated against the record (and the live form state): the rule applies only when it is true. */
    private ?string $condition = null;

    /** Human readable label of the target field, used by the error summary. */
    private ?string $label = null;

    private function __construct(private readonly string $fieldName) {}

    public static function make(string $fieldName): self
    {
        return new self($fieldName);
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return (new self((string) ($data['field_name'] ?? '')))
            ->rules($data['rules'] ?? [])
            ->message((string) ($data['custom_message'] ?? ''))
            ->sort((int) ($data['sort_order'] ?? 0))
            ->type((string) ($data['rule_type'] ?? self::TYPE_LARAVEL))
            ->when($data['condition'] ?? null)
            ->label($data['label'] ?? null);
    }

    /** @param list<string>|string $rules */
    public function rules(array|string $rules): self
    {
        $this->rules = is_array($rules) ? array_values($rules) : [$rules];

        return $this;
    }

    public function message(?string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function sort(int $sort): self
    {
        $this->sort = $sort;

        return $this;
    }

    /**
     * How the rules are interpreted: Laravel rules, names resolved by the
     * validation rule registry, or a single formula expression.
     */
    public function type(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Formula that must be true for the rule to be evaluated at all: the field
     * is only validated when the condition holds (conditional validation).
     */
    public function when(?string $condition): self
    {
        $this->condition = $condition;

        return $this;
    }

    /** Declares the rule as a single formula expression. */
    public function expression(string $expression): self
    {
        $this->type = self::TYPE_EXPRESSION;
        $this->rules = [$expression];

        return $this;
    }

    /** Label shown next to the field in the error summary. */
    public function label(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function condition(): ?string
    {
        return $this->condition;
    }

    public function ruleType(): string
    {
        return $this->type;
    }

    public function fieldLabel(): ?string
    {
        return $this->label;
    }

    /** @return list<string> */
    public function ruleList(): array
    {
        return $this->rules;
    }

    public function customMessage(): ?string
    {
        return $this->message;
    }

    public function sortOrder(): int
    {
        return $this->sort;
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
            'rules' => $this->rules,
            'custom_message' => $this->message,
            'sort_order' => $this->sort,
            'rule_type' => $this->type,
            'condition' => $this->condition,
            'label' => $this->label,
        ];
    }
}
