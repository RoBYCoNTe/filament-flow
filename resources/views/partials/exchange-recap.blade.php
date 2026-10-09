@props(['entry', 'exchanges', 'dateTimeFormat'])

{{--
    The quick look back at every exchange of a record — the request, the fields the requester opened,
    the documents it attached, the answer — folded away until the reader opens it, so the page
    stays on what is pending and the iterations are one click away.
--}}
@if ($exchanges->isNotEmpty())
    <details data-exchange-recap class="group mt-3 rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <summary class="flex cursor-pointer select-none list-none items-center gap-2 px-4 py-3 text-sm font-semibold text-gray-800 dark:text-gray-100">
            <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 shrink-0 text-gray-400 transition-transform group-open:rotate-90" aria-hidden="true" />
            {{ __('filament-flow::messages.exchange_recap_title') }}
            <span class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-semibold text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                {{ $exchanges->count() }}
            </span>
        </summary>

        <ol class="divide-y divide-gray-950/5 border-t border-gray-950/5 dark:divide-white/10 dark:border-white/10">
            @foreach ($exchanges as $exchange)
                @php
                    $labels = $exchange->isMessage() ? [] : $entry->scopeLabelsFor($exchange);
                    $documents = $exchange->isMessage() ? [] : $entry->attachmentsFor($exchange);
                    $status = match (true) {
                        $exchange->isMessage() => $exchange->toStateLabel ?? __('filament-flow::messages.open_requests_message_label'),
                        $exchange->isOpen() => __('filament-flow::messages.open_requests_waiting'),
                        default => __('filament-flow::messages.open_requests_answered'),
                    };
                @endphp

                <li data-exchange class="space-y-2 px-4 py-3 text-sm">
                    <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                        <span class="rounded-md bg-gray-100 px-2 py-0.5 font-semibold text-gray-700 dark:bg-gray-500/20 dark:text-gray-300">{{ $status }}</span>
                        @if ($exchange->label && ! $exchange->isMessage())
                            <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $exchange->label }}</span>
                        @endif
                        <span>
                            {{ $exchange->requestedBy ? $exchange->requestedBy.' · ' : '' }}{{ $exchange->requestedAt?->translatedFormat($dateTimeFormat) ?? '—' }}
                        </span>
                        @if ($exchange->answeredAt)
                            <span>→ {{ __('filament-flow::messages.open_requests_answered_on', ['date' => $exchange->answeredAt->translatedFormat($dateTimeFormat)]) }}</span>
                        @endif
                    </p>

                    @if (filled($exchange->note))
                        <p class="border-l-2 border-gray-200 pl-3 leading-relaxed text-gray-700 dark:border-gray-600 dark:text-gray-300">{{ $exchange->note }}</p>
                    @endif

                    @if ($labels !== [])
                        <ul class="grid gap-x-4 gap-y-0.5 text-xs text-gray-700 dark:text-gray-300 sm:grid-cols-2" aria-label="{{ __('filament-flow::messages.exchange_recap_fields') }}">
                            @foreach ($labels as $label)
                                <li data-exchange-field class="flex items-center gap-1.5">
                                    <x-filament::icon icon="heroicon-m-arrow-right" class="h-3 w-3 shrink-0 text-gray-400" aria-hidden="true" />
                                    <span class="min-w-0">{{ $label }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @include('filament-flow::partials.request-attachments', ['documents' => $documents])
                </li>
            @endforeach
        </ol>
    </details>
@endif
