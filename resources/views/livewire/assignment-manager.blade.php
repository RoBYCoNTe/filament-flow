{{--
    The panel of the assignments, drawn with the components of Filament: the border, the
    background and the colours are the theme's, not our choices. The only classes of our own
    are typography and spacing.
--}}
<div class="space-y-3">
    @if($showExplanation)
        {{--
            What the panel is for, in the words of the office: who holds the record, who works
            on it, and the three readings of a permission.

            It is read once, so it stays folded for whoever acts — and opens for whoever may
            not: there the explanation is the whole content of the panel, and it is what tells
            them why the controls are not theirs.
        --}}
        <x-filament::section
            :heading="__('filament-flow::messages.explanation_heading')"
            icon="heroicon-m-information-circle"
            collapsible
            :collapsed="$this->explanationStartsCollapsed()"
            compact
        >
            <div class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                <p>{{ __('filament-flow::messages.explanation_intro') }}</p>

                <ul class="list-disc space-y-1 pl-5">
                    <li>{!! __('filament-flow::messages.explanation_ownership') !!}</li>
                    <li>{!! __('filament-flow::messages.explanation_assignments') !!}</li>
                    <li>{!! __('filament-flow::messages.explanation_permissions') !!}</li>
                </ul>

                @unless($canManage)
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ __('filament-flow::messages.explanation_read_only') }}
                    </p>
                @endunless
            </div>
        </x-filament::section>
    @endif

    @if($canManage && $hasOwnerField)
        <x-filament::section :heading="__('filament-flow::messages.ownership')" compact>
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('filament-flow::messages.current_owner') }}
                </span>

                @if($currentOwner)
                    <x-filament::badge color="primary" icon="heroicon-m-user-circle">
                        {{ $currentOwner['name'] }}
                    </x-filament::badge>
                @else
                    <x-filament::badge color="gray" icon="heroicon-m-user-minus">
                        {{ __('filament-flow::messages.no_owner') }}
                    </x-filament::badge>
                @endif

                @unless($showTransferForm)
                    <x-filament::button
                        wire:click="toggleTransferForm"
                        color="gray"
                        outlined
                        icon="heroicon-m-arrow-right-start-on-rectangle"
                        size="xs"
                    >
                        {{ __('filament-flow::messages.transfer_ownership') }}
                    </x-filament::button>
                @endunless
            </div>

            @if($showTransferForm)
                <div class="mt-3 space-y-3">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('filament-flow::messages.help_transfer_ownership') }}
                    </p>

                    {{ $this->transferForm }}

                    <div class="flex items-center gap-2">
                        <x-filament::button
                            wire:click="transferOwnership"
                            wire:loading.attr="disabled"
                            wire:target="transferOwnership"
                            icon="heroicon-m-arrow-right-start-on-rectangle"
                            size="sm"
                        >
                            {{ __('filament-flow::messages.transfer_ownership') }}
                        </x-filament::button>
                        <x-filament::button wire:click="toggleTransferForm" color="gray" size="sm">
                            {{ __('filament-flow::messages.cancel') }}
                        </x-filament::button>
                    </div>
                </div>
            @endif
        </x-filament::section>
    @endif

    @if($showHistory)
        {{-- The handovers, in order: what the column of the list says in one line is read here
             in full. Folded by default — the history answers when it is looked for, and does
             not take the scene. --}}
        <x-filament::section
            :heading="__('filament-flow::messages.ownership_history_label')"
            icon="heroicon-m-clock"
            collapsible
            collapsed
            compact
        >
            @include('filament-flow::infolists.partials.ownership-history', [
                'history' => $ownershipHistory,
                'timeline' => true,
            ])
        </x-filament::section>
    @endif

    @forelse($assignments as $assignment)
        @php
            $typeCfg = $typeConfig[$assignment['assignment_type']] ?? $typeConfig['primary'];
            $typeColor = match ($assignment['assignment_type']) {
                'primary' => 'primary',
                'secondary' => 'warning',
                default => 'gray',
            };
        @endphp

        <x-filament::section :heading="$assignment['name']" :description="$assignment['roles'] ?: null" compact>
            <div class="flex flex-wrap items-center gap-2">
                <x-filament::badge :color="$typeColor" :icon="$typeCfg['icon']">
                    {{ $typeCfg['label'] }}
                </x-filament::badge>

                @includeWhen($assignmentBadgesView, $assignmentBadgesView ?? '', ['assignment' => $assignment])

                @if($canManage)
                    @foreach(['view' => 'heroicon-m-eye', 'edit' => 'heroicon-m-pencil-square', 'transition' => 'heroicon-m-arrow-path'] as $kind => $icon)
                        @php
                            $state = $assignment['override_' . $kind];
                            $overrideColor = $state === true ? 'success' : ($state === false ? 'danger' : 'gray');
                            $mark = $state === true ? ' ✓' : ($state === false ? ' ✕' : '');
                        @endphp
                        <button
                            type="button"
                            wire:click="toggleOverride({{ $assignment['id'] }}, '{{ $kind }}')"
                            title="{{ __('filament-flow::messages.help_override_' . $kind) }}"
                        >
                            <x-filament::badge :color="$overrideColor" :icon="$icon">
                                {{ __('filament-flow::messages.' . $kind) . $mark }}
                            </x-filament::badge>
                        </button>
                    @endforeach
                @elseif($assignment['has_denial'])
                    <x-filament::badge color="danger" icon="heroicon-m-shield-exclamation">
                        {{ __('filament-flow::messages.denied') }}
                    </x-filament::badge>
                @elseif($assignment['has_overrides'])
                    <x-filament::badge color="warning" icon="heroicon-m-shield-exclamation">
                        {{ __('filament-flow::messages.override') }}
                    </x-filament::badge>
                @endif

                @if($canManage)
                    <x-filament::icon-button
                        icon="heroicon-m-x-mark"
                        color="danger"
                        size="sm"
                        :label="__('filament-flow::messages.remove')"
                        wire:click="removeAssignment({{ $assignment['id'] }})"
                        wire:confirm="{{ __('filament-flow::messages.confirm_remove_assignment') }}"
                    />
                @endif
            </div>
        </x-filament::section>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('filament-flow::messages.no_users_assigned') }}
        </p>
    @endforelse

    @if($canManage)
        @if($showAddForm)
            <x-filament::section :heading="__('filament-flow::messages.add_assignment')" compact>
                <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('filament-flow::messages.help_access_overrides') }}
                </p>

                {{ $this->addForm }}

                <x-slot name="footer">
                    <div class="flex items-center gap-2">
                        <x-filament::button
                            wire:click="addAssignment"
                            wire:loading.attr="disabled"
                            wire:target="addAssignment"
                            icon="heroicon-m-plus"
                            size="sm"
                        >
                            {{ __('filament-flow::messages.save') }}
                        </x-filament::button>
                        <x-filament::button wire:click="toggleAddForm" color="gray" size="sm">
                            {{ __('filament-flow::messages.cancel') }}
                        </x-filament::button>
                    </div>
                </x-slot>
            </x-filament::section>
        @else
            <x-filament::button wire:click="toggleAddForm" color="gray" outlined icon="heroicon-m-plus" size="sm">
                {{ __('filament-flow::messages.add_assignment') }}
            </x-filament::button>
        @endif
    @endif
</div>
