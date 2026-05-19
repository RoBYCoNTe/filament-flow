<?php

namespace RoBYCoNTe\FilamentFlow\Support;

class AssignmentTypeConfig
{
    /**
     * @return array<string, array{label: string, bg: string, icon: string}>
     */
    public static function all(): array
    {
        return [
            'primary' => [
                'label' => __('filament-flow::messages.assignment_type_primary'),
                'bg' => 'bg-primary-100 text-primary-700 dark:bg-primary-400/20 dark:text-primary-400',
                'icon' => 'heroicon-m-star',
            ],
            'secondary' => [
                'label' => __('filament-flow::messages.assignment_type_secondary'),
                'bg' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
                'icon' => 'heroicon-m-user',
            ],
            'viewer' => [
                'label' => __('filament-flow::messages.assignment_type_viewer'),
                'bg' => 'bg-info-100 text-info-700 dark:bg-info-400/20 dark:text-info-400',
                'icon' => 'heroicon-m-eye',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_map(fn (array $config) => $config['label'], static::all());
    }
}
