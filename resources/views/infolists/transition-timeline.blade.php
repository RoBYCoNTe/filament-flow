<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    @php
        $timeline = $getTimeline();
        $totalCount = $getTotalCount();
        $limit = $getLimit();
        $expandable = $isExpandable();
        $showIpAddress = $showsIpAddress();
        $dateFormat = $getDateTimeFormat();

        // When the fold is off, the timeline ends at the limit: the rows past it
        // are not drawn at all, and no button pretends otherwise.
        $entries = $expandable ? $timeline : $timeline->take($limit);
        $hiddenCount = max($entries->count() - $limit, 0);
        $notLoadedCount = max($totalCount - $timeline->count(), 0);
    @endphp

    <div {{ $attributes }} x-data="{ expanded: false }" class="filament-flow-timeline">
        <ol>
            @forelse($entries as $item)
                @php
                    $marker = $getMarkerFor($item);
                    $isAction = $item->isAction();
                    $markerColor = $marker['color'] ?? ($isAction ? 'gray' : 'primary');
                    $markerIcon = $marker['icon'] ?? ($isAction ? 'heroicon-m-pencil-square' : 'heroicon-m-arrow-right-circle');
                    $markerClasses = match ($markerColor) {
                        'success' => 'bg-success-100 text-success-700 dark:bg-success-400/10 dark:text-success-400',
                        'danger' => 'bg-danger-100 text-danger-700 dark:bg-danger-400/10 dark:text-danger-400',
                        'warning' => 'bg-warning-100 text-warning-700 dark:bg-warning-400/10 dark:text-warning-400',
                        'info' => 'bg-info-100 text-info-700 dark:bg-info-400/10 dark:text-info-400',
                        'gray' => 'bg-gray-100 text-gray-600 dark:bg-gray-700/40 dark:text-gray-300',
                        default => 'bg-primary-100 text-primary-700 dark:bg-primary-400/10 dark:text-primary-400',
                    };
                    $metadata = $showsMetadata() ? $item->metadata : null;
                    $validationErrors = $metadata?->validation_errors ?? [];
                    // What the history leads with is what the engine compared: the fields that
                    // moved. An entry nobody compared has no answer of its own, and the values
                    // it carried are what there is — under a fold, never as "changed fields".
                    $changed = $getChangedFields($item);
                    $compared = $wasCompared($item);
                    $quiet = $compared && $changed['fields'] === [];
                    $submitted = (! $compared && $changed['fields'] === [] && $showsSubmittedData())
                        ? $getSubmittedFields($item)
                        : ['fields' => [], 'hidden' => 0];
                    $snapshotDiff = $getSnapshotDiff($item);
                    $duration = $formatDuration($item->duration_seconds);
                    $hasTechnicalDetails = $showIpAddress && ($item->ip_address || $item->user_agent);
                    $hasDetails = $changed['fields'] !== []
                        || $submitted['fields'] !== []
                        || $quiet
                        || $validationErrors !== []
                        || $snapshotDiff !== []
                        || $hasTechnicalDetails;
                @endphp

                <li
                    class="relative flex gap-3"
                    @if($loop->index >= $limit) x-cloak x-show="expanded" @endif
                >
                    @unless($loop->last)
                        <span class="absolute bottom-0 left-4 top-9 w-px bg-gray-200 dark:bg-gray-700" aria-hidden="true"></span>
                    @endunless

                    {{-- The marker wears the colour the workflow gave its state --}}
                    <span class="relative z-[1] flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $markerClasses }}">
                        <x-filament::icon :icon="$markerIcon" class="h-4 w-4" />
                    </span>

                    <div class="min-w-0 flex-1 {{ $loop->last ? '' : 'pb-5' }}">
                        <div class="flex items-start justify-between gap-x-3 gap-y-0.5">
                            <h4 class="flex flex-wrap items-center gap-x-1.5 text-sm font-medium text-gray-950 dark:text-white">
                                @if($isAction)
                                    {{ $item->transition?->label ?? __('filament-flow::messages.action') }}
                                @else
                                    <span>{{ $item->from_state_label ?? $item->from_state }}</span>
                                    <x-filament::icon icon="heroicon-m-arrow-long-right" class="h-4 w-4 shrink-0 text-gray-400" aria-hidden="true" />
                                    <span>{{ $item->to_state_label ?? $item->to_state }}</span>
                                @endif
                                @if($item->is_visible === false)
                                    <span class="ml-1 inline-flex items-center rounded-md bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-gray-500">
                                        {{ __('filament-flow::messages.hidden_entry') }}
                                    </span>
                                @endif
                            </h4>
                            <time
                                class="shrink-0 pt-0.5 text-right text-xs text-gray-500 dark:text-gray-400"
                                datetime="{{ $item->created_at->toIso8601String() }}"
                            >
                                {{ $item->created_at->translatedFormat($dateFormat) }}
                                <span class="text-gray-400 dark:text-gray-500">· {{ $item->created_at->diffForHumans() }}</span>
                            </time>
                        </div>

                        <p class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-gray-500 dark:text-gray-400">
                            @if($item->user_name)
                                <span class="inline-flex items-center gap-1">
                                    <x-filament::icon icon="heroicon-m-user-circle" class="h-3.5 w-3.5" aria-hidden="true" />
                                    <span class="font-medium text-gray-600 dark:text-gray-300">{{ $item->user_name }}</span>
                                    @if($item->user_email)
                                        <span class="text-gray-400 dark:text-gray-500">· {{ $item->user_email }}</span>
                                    @endif
                                </span>
                            @endif

                            @if($duration && $item->from_state_label)
                                <span class="inline-flex items-center gap-1">
                                    <x-filament::icon icon="heroicon-m-clock" class="h-3.5 w-3.5" aria-hidden="true" />
                                    {{ __('filament-flow::messages.duration_in_state', ['duration' => $duration, 'state' => $item->from_state_label]) }}
                                </span>
                            @endif

                            @if($changedFieldsCount = $countFieldChanges($item))
                                <span class="inline-flex items-center gap-1">
                                    <x-filament::icon icon="heroicon-m-pencil-square" class="h-3.5 w-3.5" aria-hidden="true" />
                                    {{ trans_choice('filament-flow::messages.changed_fields_count', $changedFieldsCount, ['count' => $changedFieldsCount]) }}
                                </span>
                            @endif
                        </p>

                        @if($item->reason)
                            <p class="mt-2 flex items-start gap-1.5 rounded-md bg-warning-50 px-2.5 py-1.5 text-xs leading-relaxed text-warning-800 dark:bg-warning-400/10 dark:text-warning-400">
                                <x-filament::icon icon="heroicon-m-exclamation-triangle" class="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                                <span>
                                    <span class="font-semibold">{{ __('filament-flow::messages.reason') }}:</span>
                                    {{ $item->reason }}
                                </span>
                            </p>
                        @endif

                        @if($item->notes)
                            <p class="mt-2 border-l-2 border-gray-200 pl-2.5 text-sm italic leading-relaxed text-gray-700 dark:border-gray-700 dark:text-gray-300">
                                {{ $item->notes }}
                            </p>
                        @endif

                        @if($hasDetails)
                            <details class="group mt-2">
                                <summary class="inline-flex list-none cursor-pointer select-none items-center gap-1 text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">
                                    <x-filament::icon
                                        icon="heroicon-m-chevron-right"
                                        class="h-3.5 w-3.5 transition-transform group-open:rotate-90"
                                        aria-hidden="true"
                                    />
                                    {{ __('filament-flow::messages.details') }}
                                </summary>
                                <div class="mt-2 space-y-3 rounded-lg bg-gray-50 p-3 dark:bg-gray-800/60">
                                    @if($changed['fields'] !== [])
                                        @php $foldChanged = $foldsGroups($changed['fields']); @endphp
                                        <div data-flow-section>
                                            <div class="mb-1 flex items-center justify-between gap-2">
                                                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                                    {{ __('filament-flow::messages.field_changes') }}
                                                </p>
                                                @if($foldChanged)
                                                    @include('filament-flow::infolists.partials.group-toggle')
                                                @endif
                                            </div>
                                            @foreach($groupFields($changed['fields']) as $group => $rows)
                                                @if($foldChanged)
                                                    @include('filament-flow::infolists.partials.field-group', [
                                                        'group' => $group,
                                                        'rows' => $rows,
                                                        'body' => 'filament-flow::infolists.partials.change-rows',
                                                    ])
                                                @else
                                                    @if($group !== '')
                                                        <p class="mb-1 mt-2 text-[11px] font-medium text-gray-500 dark:text-gray-400">{{ $group }}</p>
                                                    @endif
                                                    @include('filament-flow::infolists.partials.change-rows', ['rows' => $rows])
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif

                                    @if($quiet)
                                        <p class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                                            <x-filament::icon icon="heroicon-m-minus-circle" class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                                            {{ __('filament-flow::messages.no_field_changed') }}
                                        </p>
                                    @endif

                                    @if($submitted['fields'] !== [])
                                        @php
                                            $foldSubmitted = $foldsGroups($submitted['fields']);
                                            $submittedCount = trans_choice('filament-flow::messages.fields_count', count($submitted['fields']), ['count' => count($submitted['fields'])]);
                                        @endphp
                                        {{-- The values the transition carried, for the entries nobody
                                             compared: a fold of its own, because it is not the
                                             answer to "what changed?" — it is what there is. --}}
                                        <details class="group/field-section">
                                            <summary class="flex cursor-pointer select-none list-none items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                                <x-filament::icon
                                                    icon="heroicon-m-chevron-right"
                                                    class="h-3.5 w-3.5 shrink-0 transition-transform group-open/field-section:rotate-90"
                                                    aria-hidden="true"
                                                />
                                                {{ __('filament-flow::messages.form_data') }}
                                                <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold normal-case text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                                    {{ $submittedCount }}
                                                </span>
                                            </summary>
                                            <div class="mt-2" data-flow-section>
                                                <div class="mb-1 flex items-center justify-end gap-2">
                                                    @if($foldSubmitted)
                                                        @include('filament-flow::infolists.partials.group-toggle')
                                                    @endif
                                                </div>
                                                @foreach($groupFields($submitted['fields']) as $group => $rows)
                                                    @if($foldSubmitted)
                                                        @include('filament-flow::infolists.partials.field-group', [
                                                            'group' => $group,
                                                            'rows' => $rows,
                                                            'body' => 'filament-flow::infolists.partials.value-rows',
                                                        ])
                                                    @else
                                                        @if($group !== '')
                                                            <p class="mb-1 mt-2 text-[11px] font-medium text-gray-500 dark:text-gray-400">{{ $group }}</p>
                                                        @endif
                                                        @include('filament-flow::infolists.partials.value-rows', ['rows' => $rows])
                                                    @endif
                                                @endforeach
                                                @if($submitted['hidden'] > 0)
                                                    <p class="mt-2 text-[11px] text-gray-400 dark:text-gray-500">
                                                        {{ trans_choice('filament-flow::messages.hidden_fields_count', $submitted['hidden'], ['count' => $submitted['hidden']]) }}
                                                    </p>
                                                @endif
                                            </div>
                                        </details>
                                    @endif

                                    @if($validationErrors !== [])
                                        <div>
                                            <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                                {{ __('filament-flow::messages.validation_errors') }}
                                            </p>
                                            <ul class="list-inside list-disc space-y-0.5 text-danger-600 dark:text-danger-400">
                                                @foreach($validationErrors as $field => $messages)
                                                    @php
                                                        $messages = is_array($messages) ? $messages : [$messages];
                                                    @endphp
                                                    @foreach($messages as $message)
                                                        <li>{{ $field }}: {{ $formatValue($message) }}</li>
                                                    @endforeach
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif

                                    @if($snapshotDiff !== [])
                                        <details class="group/field-section">
                                            <summary class="flex cursor-pointer select-none list-none items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                                <x-filament::icon
                                                    icon="heroicon-m-chevron-right"
                                                    class="h-3.5 w-3.5 shrink-0 transition-transform group-open/field-section:rotate-90"
                                                    aria-hidden="true"
                                                />
                                                {{ __('filament-flow::messages.record_changes') }}
                                                <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold normal-case text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                                    {{ trans_choice('filament-flow::messages.fields_count', count($snapshotDiff), ['count' => count($snapshotDiff)]) }}
                                                </span>
                                            </summary>
                                            <ul class="mt-1.5 space-y-1">
                                                @foreach(array_slice($snapshotDiff, 0, 15) as $change)
                                                    <li class="flex flex-wrap items-center gap-x-1.5">
                                                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ $change['field'] }}:</span>
                                                        <span class="text-gray-400 line-through decoration-gray-300 dark:text-gray-500">{{ $formatValue($change['before']) }}</span>
                                                        <x-filament::icon icon="heroicon-m-arrow-long-right" class="h-3 w-3 text-gray-400" aria-hidden="true" />
                                                        <span class="font-mono text-gray-900 dark:text-white">{{ $formatValue($change['after']) }}</span>
                                                    </li>
                                                @endforeach
                                                @if(count($snapshotDiff) > 15)
                                                    <li class="text-gray-400 dark:text-gray-500">
                                                        {{ trans_choice('filament-flow::messages.more_fields', count($snapshotDiff) - 15, ['count' => count($snapshotDiff) - 15]) }}
                                                    </li>
                                                @endif
                                            </ul>
                                        </details>
                                    @endif

                                    @if($hasTechnicalDetails)
                                        <details class="group/field-section">
                                            <summary class="flex cursor-pointer select-none list-none items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                                <x-filament::icon
                                                    icon="heroicon-m-chevron-right"
                                                    class="h-3.5 w-3.5 shrink-0 transition-transform group-open/field-section:rotate-90"
                                                    aria-hidden="true"
                                                />
                                                {{ __('filament-flow::messages.technical_details') }}
                                            </summary>
                                            <dl class="mt-1.5 grid grid-cols-[minmax(0,10rem)_minmax(0,1fr)] gap-x-3 gap-y-1">
                                                @if($item->ip_address)
                                                    <dt class="text-gray-500 dark:text-gray-400">{{ __('filament-flow::messages.ip_address') }}</dt>
                                                    <dd class="break-words font-mono text-gray-700 dark:text-gray-300">{{ $item->ip_address }}</dd>
                                                @endif
                                                @if($item->user_agent)
                                                    <dt class="text-gray-500 dark:text-gray-400">{{ __('filament-flow::messages.user_agent') }}</dt>
                                                    <dd class="break-words font-mono text-gray-700 dark:text-gray-300" title="{{ $item->user_agent }}">
                                                        {{ Str::limit($item->user_agent, 80) }}
                                                    </dd>
                                                @endif
                                            </dl>
                                        </details>
                                    @endif
                                </div>
                            </details>
                        @endif
                    </div>
                </li>
            @empty
                <li class="flex list-none flex-col items-center gap-1.5 py-6 text-center">
                    <x-filament::icon icon="heroicon-o-clock" class="h-6 w-6 text-gray-300 dark:text-gray-600" aria-hidden="true" />
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('filament-flow::messages.no_history_yet') }}
                    </p>
                </li>
            @endforelse
        </ol>

        @if($expandable && $hiddenCount > 0)
            <div class="pt-2 text-center">
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-semibold text-primary-600 transition hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-400/10"
                    x-show="! expanded"
                    @click="expanded = true"
                >
                    <x-filament::icon icon="heroicon-m-chevron-down" class="h-3.5 w-3.5" aria-hidden="true" />
                    {{ trans_choice('filament-flow::messages.show_more_entries', $hiddenCount, ['count' => $hiddenCount]) }}
                </button>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-semibold text-gray-500 transition hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800"
                    x-cloak
                    x-show="expanded"
                    @click="expanded = false"
                >
                    <x-filament::icon icon="heroicon-m-chevron-up" class="h-3.5 w-3.5" aria-hidden="true" />
                    {{ __('filament-flow::messages.show_less') }}
                </button>
            </div>
        @endif

        @if($notLoadedCount > 0)
            <p class="pt-1 text-center text-xs text-gray-400 dark:text-gray-500">
                {{ trans_choice('filament-flow::messages.count_more_entries', $notLoadedCount, ['count' => $notLoadedCount]) }}
            </p>
        @endif
    </div>
</x-dynamic-component>
