<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    @php
        $label = $getStateLabel();
        $color = $getStateColor();
        $icon  = $getStateIcon();

        $colorClasses = match($color) {
            'primary' => 'bg-primary-50 text-primary-700 ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/30',
            'success' => 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/30',
            'warning' => 'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-400 dark:ring-warning-400/30',
            'danger'  => 'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-400 dark:ring-danger-400/30',
            'info'    => 'bg-info-50 text-info-700 ring-info-600/20 dark:bg-info-400/10 dark:text-info-400 dark:ring-info-400/30',
            'gray'    => 'bg-gray-50 text-gray-700 ring-gray-600/20 dark:bg-gray-700/40 dark:text-gray-300 dark:ring-gray-500/30',
            default   => 'bg-gray-50 text-gray-700 ring-gray-600/20 dark:bg-gray-700/40 dark:text-gray-300 dark:ring-gray-500/30',
        };
    @endphp

    @if($label)
        <span class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $colorClasses }}">
            @if($icon)
                <x-filament::icon :icon="$icon" class="h-3.5 w-3.5" />
            @endif
            {{ $label }}
        </span>
    @endif
</x-dynamic-component>
