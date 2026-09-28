{{--
    The component of the handover history: it reads the transfers of the record and shows them
    in order, most recent first. The view is a shell: the drawing lives in the partial, because
    the panel of the assignments shows the same history.
--}}
@include('filament-flow::infolists.partials.ownership-history', [
    'history' => $getHistory(),
    'timeline' => $getWithTimeline(),
])
