@php
    $workflow = $getRecord();
    $states = $workflow->states()->orderBy('sort_order')->get();
    $transitions = $workflow->transitions()->with(['fromState', 'toState'])->get();
    $initialState = $states->firstWhere('is_initial', true);
    $finalStates = $states->where('is_final', true);
@endphp

<div class="p-4">
    @if($states->isEmpty())
        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
            <x-heroicon-o-cube-transparent class="w-12 h-12 mx-auto mb-4 opacity-50" />
            <p>{{ __('No states defined yet.') }}</p>
            <p class="text-sm">{{ __('Add states to visualize the workflow diagram.') }}</p>
        </div>
    @else
        <div class="space-y-6">
            {{-- Legend --}}
            <div class="flex flex-wrap gap-4 text-sm border-b border-gray-200 dark:border-gray-700 pb-4">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-green-500"></span>
                    <span class="text-gray-600 dark:text-gray-400">{{ __('Initial State') }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-red-500"></span>
                    <span class="text-gray-600 dark:text-gray-400">{{ __('Final State') }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-gray-400"></span>
                    <span class="text-gray-600 dark:text-gray-400">{{ __('Intermediate State') }}</span>
                </div>
            </div>

            {{-- States Grid --}}
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                @foreach($states as $state)
                    @php
                        $outgoing = $transitions->where('from_state_id', $state->id);
                        $incoming = $transitions->where('to_state_id', $state->id);
                        $colorClass = match($state->color) {
                            'primary' => 'bg-primary-100 border-primary-500 dark:bg-primary-900/30',
                            'success' => 'bg-green-100 border-green-500 dark:bg-green-900/30',
                            'warning' => 'bg-yellow-100 border-yellow-500 dark:bg-yellow-900/30',
                            'danger' => 'bg-red-100 border-red-500 dark:bg-red-900/30',
                            'info' => 'bg-blue-100 border-blue-500 dark:bg-blue-900/30',
                            default => 'bg-gray-100 border-gray-400 dark:bg-gray-800',
                        };
                        $textColorClass = match($state->color) {
                            'primary' => 'text-primary-700 dark:text-primary-300',
                            'success' => 'text-green-700 dark:text-green-300',
                            'warning' => 'text-yellow-700 dark:text-yellow-300',
                            'danger' => 'text-red-700 dark:text-red-300',
                            'info' => 'text-blue-700 dark:text-blue-300',
                            default => 'text-gray-700 dark:text-gray-300',
                        };
                    @endphp
                    <div class="relative p-4 rounded-lg border-2 {{ $colorClass }} transition-all hover:shadow-md">
                        {{-- State Type Indicator --}}
                        @if($state->is_initial)
                            <div class="absolute -top-2 -left-2 w-5 h-5 rounded-full bg-green-500 flex items-center justify-center" title="{{ __('Initial State') }}">
                                <x-heroicon-s-play class="w-3 h-3 text-white" />
                            </div>
                        @endif
                        @if($state->is_final)
                            <div class="absolute -top-2 -right-2 w-5 h-5 rounded-full bg-red-500 flex items-center justify-center" title="{{ __('Final State') }}">
                                <x-heroicon-s-stop class="w-3 h-3 text-white" />
                            </div>
                        @endif

                        {{-- State Content --}}
                        <div class="text-center">
                            @if($state->icon)
                                <x-dynamic-component :component="$state->icon" class="w-6 h-6 mx-auto mb-2 {{ $textColorClass }}" />
                            @endif
                            <h4 class="font-semibold {{ $textColorClass }}">{{ $state->label }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400 font-mono mt-1">{{ $state->name }}</p>
                        </div>

                        {{-- Transition Counts --}}
                        <div class="flex justify-center gap-3 mt-3 text-xs">
                            <span class="flex items-center gap-1 text-gray-500 dark:text-gray-400" title="{{ __('Incoming transitions') }}">
                                <x-heroicon-o-arrow-down-on-square class="w-3 h-3" />
                                {{ $incoming->count() }}
                            </span>
                            <span class="flex items-center gap-1 text-gray-500 dark:text-gray-400" title="{{ __('Outgoing transitions') }}">
                                <x-heroicon-o-arrow-up-on-square class="w-3 h-3" />
                                {{ $outgoing->count() }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Transitions List --}}
            @if($transitions->isNotEmpty())
                <div class="mt-6">
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                        {{ __('Transitions') }} ({{ $transitions->count() }})
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                        @foreach($transitions as $transition)
                            <div class="flex items-center gap-2 p-2 rounded bg-gray-50 dark:bg-gray-800/50 text-sm">
                                <span class="px-2 py-1 rounded text-xs font-medium" style="background-color: {{ $transition->fromState?->color ? "var(--{$transition->fromState->color}-100)" : '#e5e7eb' }}">
                                    {{ $transition->fromState?->label ?? '?' }}
                                </span>
                                <x-heroicon-o-arrow-right class="w-4 h-4 text-gray-400 flex-shrink-0" />
                                <span class="px-2 py-1 rounded text-xs font-medium" style="background-color: {{ $transition->toState?->color ? "var(--{$transition->toState->color}-100)" : '#e5e7eb' }}">
                                    {{ $transition->toState?->label ?? '?' }}
                                </span>
                                <span class="ml-auto text-gray-500 dark:text-gray-400 truncate" title="{{ $transition->label }}">
                                    {{ $transition->label }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
