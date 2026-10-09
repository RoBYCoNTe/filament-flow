@php
    /**
     * What the workflow says and waits for: the requests opened and not yet answered, and the
     * messages a transition left with no answer expected (a decision with its reason).
     *
     * A request tells the reader what is expected and by when, while the buttons that answer stay
     * in the toolbar of the state actions. A message tells what the workflow said: it wears the
     * colour of the state it moved to, and asks for nothing.
     */
    $requests = $getOpenRequests();
    $forOwner = $getIsForOwner();
    $dateTimeFormat = $getDateTimeFormat();
    $dateFormat = $getDateFormat();
@endphp

<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    @if ($requests->isNotEmpty())
        <div class="space-y-3">
            @foreach ($requests as $request)
                @php
                    $isMessage = $request->isMessage();
                    $isOpen = $request->isOpen();
                    $isOverdue = $isOpen && $request->isOverdue();
                    $isDueSoon = $isOpen && ! $isOverdue && $request->isDueSoon();
                    $remaining = $request->daysRemaining();

                    // A request tells its urgency; a message the colour of the state it moved to.
                    $tone = $isMessage
                        ? ($request->color ?? 'gray')
                        : match (true) {
                            $isOverdue => 'danger',
                            $isDueSoon => 'warning',
                            ! $isOpen => 'success',
                            default => 'warning',
                        };

                    // A request reads on the soft tint of its urgency; a decision on the marked
                    // tint of the state it moved to — never white, like an exchange.
                    $cardClasses = $isMessage
                        ? match ($tone) {
                            'danger' => 'border-danger-300 bg-danger-100/50 dark:border-danger-500/30 dark:bg-danger-500/10',
                            'success' => 'border-success-300 bg-success-100/50 dark:border-success-500/30 dark:bg-success-500/10',
                            'info' => 'border-info-300 bg-info-100/50 dark:border-info-500/30 dark:bg-info-500/10',
                            'primary' => 'border-primary-300 bg-primary-100/50 dark:border-primary-500/30 dark:bg-primary-500/10',
                            'gray' => 'border-gray-300 bg-gray-100/50 dark:border-gray-600/40 dark:bg-gray-500/10',
                            default => 'border-warning-300 bg-warning-100/50 dark:border-warning-500/30 dark:bg-warning-500/10',
                        }
                        : match ($tone) {
                            'danger' => 'border-danger-300 bg-danger-50/60 dark:border-danger-500/30 dark:bg-danger-500/5',
                            'success' => 'border-success-300 bg-success-50/50 dark:border-success-500/30 dark:bg-success-500/5',
                            'info' => 'border-info-300 bg-info-50/50 dark:border-info-500/30 dark:bg-info-500/5',
                            'primary' => 'border-primary-300 bg-primary-50/50 dark:border-primary-500/30 dark:bg-primary-500/5',
                            'gray' => 'border-gray-300 bg-gray-50/60 dark:border-gray-600/40 dark:bg-gray-500/5',
                            default => 'border-warning-300 bg-warning-50/60 dark:border-warning-500/30 dark:bg-warning-500/5',
                        };

                    $iconClasses = match ($tone) {
                        'danger' => 'text-danger-600 dark:text-danger-400',
                        'success' => 'text-success-600 dark:text-success-400',
                        'info' => 'text-info-600 dark:text-info-400',
                        'primary' => 'text-primary-600 dark:text-primary-400',
                        'gray' => 'text-gray-500 dark:text-gray-400',
                        default => 'text-warning-600 dark:text-warning-400',
                    };

                    $chipClasses = match ($tone) {
                        'danger' => 'bg-danger-100 text-danger-700 dark:bg-danger-500/20 dark:text-danger-300',
                        'success' => 'bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-300',
                        'info' => 'bg-info-100 text-info-700 dark:bg-info-500/20 dark:text-info-300',
                        'primary' => 'bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300',
                        'gray' => 'bg-gray-100 text-gray-700 dark:bg-gray-500/20 dark:text-gray-300',
                        default => 'bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-300',
                    };

                    // The words of whoever spoke, set apart from the frame that carries them:
                    // a bar to lean on and room to breathe, so a long note reads as a note.
                    $quoteClasses = match ($tone) {
                        'danger' => 'border-danger-400 bg-danger-100/70 dark:border-danger-500 dark:bg-danger-500/15',
                        'success' => 'border-success-400 bg-success-100/70 dark:border-success-500 dark:bg-success-500/15',
                        'info' => 'border-info-400 bg-info-100/70 dark:border-info-500 dark:bg-info-500/15',
                        'primary' => 'border-primary-400 bg-primary-100/70 dark:border-primary-500 dark:bg-primary-500/15',
                        'gray' => 'border-gray-400 bg-gray-100/70 dark:border-gray-500 dark:bg-gray-500/15',
                        default => 'border-warning-400 bg-warning-100/70 dark:border-warning-500 dark:bg-warning-500/15',
                    };

                    $statusLabel = match (true) {
                        $isMessage => $request->toStateLabel ?? __('filament-flow::messages.open_requests_message_label'),
                        ! $isOpen => __('filament-flow::messages.open_requests_answered'),
                        $forOwner => __('filament-flow::messages.open_requests_action_required'),
                        default => __('filament-flow::messages.open_requests_waiting'),
                    };
                @endphp

                <div class="rounded-xl border p-4 {{ $cardClasses }}" role="status">
                    <div class="flex items-start gap-3">
                        <x-filament::icon
                            :icon="$isMessage ? 'heroicon-m-megaphone' : 'heroicon-m-chat-bubble-left-right'"
                            @class(['mt-0.5 h-5 w-5 shrink-0', $iconClasses])
                        />

                        <div class="min-w-0 flex-1 space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-semibold {{ $chipClasses }}">
                                    {{ $statusLabel }}
                                </span>

                                {{-- A request is read for what it asks; a message for what it says. --}}
                                @if (! $isMessage && $request->label)
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ $request->label }}
                                    </span>
                                @endif
                            </div>

                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                @if ($request->requestedBy)
                                    {{ __($isMessage
                                        ? 'filament-flow::messages.open_requests_message_by'
                                        : 'filament-flow::messages.open_requests_asked_by', [
                                            'name' => $request->requestedBy,
                                            'date' => $request->requestedAt?->translatedFormat($dateTimeFormat) ?? '—',
                                        ]) }}
                                @else
                                    {{ __($isMessage
                                        ? 'filament-flow::messages.open_requests_message_on'
                                        : 'filament-flow::messages.open_requests_asked_on', [
                                            'date' => $request->requestedAt?->translatedFormat($dateTimeFormat) ?? '—',
                                        ]) }}
                                @endif
                            </p>

                            @if (filled($request->note))
                                <blockquote class="rounded-r-lg border-l-4 py-3 pl-4 pr-3 text-sm leading-relaxed text-gray-700 dark:text-gray-200 {{ $quoteClasses }}">
                                    {{ $request->note }}
                                </blockquote>
                            @endif

                            @if (! $isMessage)
                                @include('filament-flow::partials.request-attachments', ['documents' => $entry->attachmentsFor($request)])
                            @endif

                            @if ($isOpen && $request->deadline)
                                <p @class([
                                    'text-sm font-medium',
                                    'text-danger-600 dark:text-danger-400' => $isOverdue,
                                    'text-warning-600 dark:text-warning-400' => $isDueSoon,
                                    'text-gray-600 dark:text-gray-300' => ! $isOverdue && ! $isDueSoon,
                                ])>
                                    @if ($isOverdue)
                                        {{ __('filament-flow::messages.open_requests_overdue', [
                                            'date' => $request->deadline->translatedFormat($dateFormat),
                                        ]) }}
                                    @else
                                        {{ __('filament-flow::messages.open_requests_deadline', [
                                            'date' => $request->deadline->translatedFormat($dateFormat),
                                        ]) }}
                                        @if ($remaining !== null)
                                            <span class="font-normal">
                                                ({{ trans_choice('filament-flow::messages.open_requests_days_left', $remaining, ['count' => $remaining]) }})
                                            </span>
                                        @endif
                                    @endif
                                </p>
                            @endif

                            @if (! $isMessage && ! $isOpen && $request->answeredAt)
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ __('filament-flow::messages.open_requests_answered_on', [
                                        'date' => $request->answeredAt->translatedFormat($dateTimeFormat),
                                    ]) }}
                                </p>
                            @endif

                            @if ($isOpen)
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $forOwner
                                        ? __('filament-flow::messages.open_requests_reply_hint')
                                        : __('filament-flow::messages.open_requests_office_hint') }}
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @include('filament-flow::partials.exchange-recap', [
        'entry' => $entry,
        'exchanges' => $getExchangeRecap(),
        'dateTimeFormat' => $dateTimeFormat,
    ])
</x-dynamic-component>
