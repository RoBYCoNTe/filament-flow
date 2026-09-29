@php
    /**
     * A row that awaits an answer wears a chip with what is expected and by when; a row a
     * decision was taken on wears the colour of that decision. The note — too long for a cell —
     * is read on hover.
     */
    $request = $getRequest();
    $isMessage = $request?->isMessage() ?? false;
    $overdue = $request !== null && ! $isMessage && $request->isOpen() && $request->isOverdue();
    $days = $request?->daysRemaining();

    $tone = $isMessage
        ? ($request->color ?? 'gray')
        : ($overdue ? 'danger' : 'warning');

    $chipClasses = match ($tone) {
        'danger' => 'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-400 dark:ring-danger-400/30',
        'success' => 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/30',
        'info' => 'bg-info-50 text-info-700 ring-info-600/20 dark:bg-info-400/10 dark:text-info-400 dark:ring-info-400/30',
        'primary' => 'bg-primary-50 text-primary-700 ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/30',
        'gray' => 'bg-gray-50 text-gray-700 ring-gray-600/20 dark:bg-gray-700/40 dark:text-gray-300 dark:ring-gray-500/30',
        default => 'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-400 dark:ring-warning-400/30',
    };
@endphp

<div class="px-3 py-2">
    @if ($request === null)
        <span class="text-sm text-gray-400 dark:text-gray-500">—</span>
    @else
        <span
            class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $chipClasses }}"
            @if (filled($request->note)) title="{{ $request->note }}" @endif
        >
            <x-filament::icon
                :icon="$isMessage ? 'heroicon-m-megaphone' : 'heroicon-m-chat-bubble-left-right'"
                class="h-3.5 w-3.5"
                aria-hidden="true"
            />

            @if ($isMessage)
                {{ $request->toStateLabel ?? __('filament-flow::messages.open_requests_message_label') }}
            @elseif ($overdue)
                {{ __('filament-flow::messages.open_requests_overdue_short') }}
            @else
                {{ __('filament-flow::messages.open_requests_waiting') }}
                @if ($days !== null)
                    · {{ trans_choice('filament-flow::messages.open_requests_days_left', $days, ['count' => $days]) }}
                @endif
            @endif
        </span>
    @endif
</div>
