@php
    $owner = $getOwner();
    $changes = $getWithHistory() ? $getOwnerChanges() : [];
    $lastChange = $changes[0] ?? null;
    $count = count($changes);
    $limit = $getHistoryLimit();
    $shown = array_slice($changes, 0, $limit);
    $more = $count - count($shown);

    $avatarColors = ['bg-primary-500', 'bg-success-500', 'bg-warning-500', 'bg-danger-500', 'bg-info-500'];
    $avatarColor = $owner ? $avatarColors[crc32($owner['name']) % count($avatarColors)] : null;
@endphp

<div class="flex items-start gap-2 px-3 py-4">
    @if ($owner === null)
        <span class="text-sm text-gray-400 dark:text-gray-500">—</span>
    @else
        <div
            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white {{ $avatarColor }}"
            title="{{ $owner['name'] }}"
        >
            {{ $owner['initials'] }}
        </div>

        <div class="min-w-0">
            <div class="truncate text-sm font-medium text-gray-950 dark:text-white">
                {{ $owner['name'] }}
            </div>

            @if ($owner['roles'] !== '')
                <div class="truncate text-xs text-gray-500 dark:text-gray-400">
                    {{ $owner['roles'] }}
                </div>
            @endif

            @if ($lastChange !== null && $getInlinesLastChange())
                {{-- What the column is for: a handover is not silent. Who held the record
                     before, and when it passed on — under the name, not hidden away. --}}
                <div
                    class="mt-0.5 flex items-center gap-1 text-xs text-warning-600 dark:text-warning-400"
                    @if ($count > 0)
                        x-data
                        x-tooltip.raw="{{ collect($shown)->map(fn ($change) => trim(
                            ($change['from'] ? __('filament-flow::messages.owner_change_from', ['name' => $change['from']]) : __('filament-flow::messages.owner_change_first'))
                            .' · '.$change['at']
                            .' · '.$change['retention_label']
                            .($change['note'] ? ' — '.$change['note'] : '')
                        ))->implode(' | ') }}{{ $more > 0 ? ' | '.trans_choice('filament-flow::messages.owner_change_more', $more, ['count' => $more]) : '' }}"
                    @endif
                >
                    <x-filament::icon icon="heroicon-m-arrow-path-rounded-square" class="h-3.5 w-3.5 shrink-0" />

                    <span class="truncate">
                        @if ($lastChange['from'])
                            {{ __('filament-flow::messages.owner_change_from', ['name' => $lastChange['from']]) }}
                        @else
                            {{ __('filament-flow::messages.owner_change_first') }}
                        @endif
                        · {{ $lastChange['at'] }}
                    </span>

                    @if ($count > 1)
                        <x-filament::badge color="warning" size="xs">
                            {{ $count }}
                        </x-filament::badge>
                    @endif
                </div>
            @endif
        </div>
    @endif
</div>
