<?php

namespace RoBYCoNTe\FilamentFlow\Definition\Enums;

/**
 * Classification of a definition mutation:
 *
 * - Safe:      labels, help texts, ordering — no data or structure impact.
 * - Additive:  new optional nodes — no impact on existing data.
 * - Breaking:  rename, type change, removal — affects existing data and formulas.
 */
enum MutationClass: string
{
    case Safe = 'safe';
    case Additive = 'additive';
    case Breaking = 'breaking';

    public function label(): string
    {
        return match ($this) {
            self::Safe => __('Safe'),
            self::Additive => __('Additive'),
            self::Breaking => __('Breaking'),
        };
    }
}
