@php
    $assignedUsers = $getAssignedUsers();
    $limit = $getAvatarLimit();
    $showTooltip = $getWithAvatarTooltip();
    $visible = array_slice($assignedUsers, 0, $limit);
    $extra = count($assignedUsers) - $limit;
    $colors = ['bg-primary-500', 'bg-success-500', 'bg-warning-500', 'bg-danger-500', 'bg-info-500'];
    $avatarDecorator = $getAvatarDecorator();
    $typeRingColors = [
        'primary'   => 'ring-primary-400 dark:ring-primary-600',
        'secondary' => 'ring-warning-400 dark:ring-warning-600',
        'viewer'    => 'ring-gray-300 dark:ring-gray-500',
    ];
@endphp

<div class="flex items-center px-3 py-4">
    @if(empty($assignedUsers))
        <span class="text-sm text-gray-400 dark:text-gray-500">—</span>
    @else
        <div
            class="flex -space-x-2"
            @if($showTooltip)
                x-data
                x-tooltip.raw="{{ collect($assignedUsers)->map(fn ($u) => $u['name'] . ($u['roles'] ? ' (' . $u['roles'] . ')' : ''))->implode(', ') }}"
            @endif
        >
            @foreach($visible as $user)
                @php
                    $colorIndex = crc32($user['name']) % count($colors);
                    $ringColor = $typeRingColors[$user['assignment_type']] ?? 'ring-white dark:ring-gray-900';
                    $decorator = $avatarDecorator ? $avatarDecorator($user) : null;
                @endphp
                <div class="relative flex h-8 w-8 items-center justify-center rounded-full ring-2 {{ $ringColor }} {{ $colors[$colorIndex] }} text-xs font-semibold text-white"
                     title="{{ $user['name'] }}"
                >
                    {{ $user['initials'] }}
                    @if($decorator)
                        <div class="absolute -bottom-0.5 -right-0.5 flex h-3.5 w-3.5 items-center justify-center rounded-full ring-1 ring-white dark:ring-gray-900 {{ $decorator['class'] ?? '' }}">
                            @if(!empty($decorator['icon']))
                                <x-filament::icon :icon="$decorator['icon']" class="h-2.5 w-2.5 text-white" />
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach

            @if($extra > 0)
                <div class="flex h-8 w-8 items-center justify-center rounded-full ring-2 ring-white dark:ring-gray-900 bg-gray-200 dark:bg-gray-600 text-xs font-medium text-gray-600 dark:text-gray-300">
                    +{{ $extra }}
                </div>
            @endif
        </div>
    @endif
</div>
