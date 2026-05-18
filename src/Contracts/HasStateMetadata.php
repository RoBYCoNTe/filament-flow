<?php

namespace RoBYCoNTe\FilamentFlow\Contracts;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Define the contract for flow state attributes.
 * Combines color, description, icon, and label attributes.
 *
 * @example
 * class PublishedState extends State implements HasStateMetadata
 * {
 *     public function getColor(): string|array|null
 *     {
 *         return Color::Green;
 *     }
 *    public function getDescription(): string|Htmlable|null
 *    {
 *        return __("The content is published and visible to all users.");
 *    }
 *    public function getIcon(): string|BackedEnum|null
 *    {
 *       return Heroicon::CheckCircle;
 *    }
 *    public function getLabel(): string|Htmlable|null
 *    {
 *       return __("Published");
 *    }
 * }
 */
interface HasStateMetadata extends HasColor, HasDescription, HasIcon, HasLabel {}
