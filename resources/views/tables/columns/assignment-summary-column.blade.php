@php
    $assignedUsers = $getAssignedUsers();
    $limit = $getAvatarLimit();
    $showTooltip = $getWithAvatarTooltip();
    $visible = array_slice($assignedUsers, 0, $limit);
    $extra = count($assignedUsers) - $limit;
    $colors = ['bg-primary-500', 'bg-success-500', 'bg-warning-500', 'bg-danger-500', 'bg-info-500'];
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
                @endphp
                <div class="flex h-8 w-8 items-center justify-center rounded-full ring-2 ring-white dark:ring-gray-900 {{ $colors[$colorIndex] }} text-xs font-semibold text-white"
                     title="{{ $user['name'] }}"
                >
                    {{ $user['initials'] }}
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
