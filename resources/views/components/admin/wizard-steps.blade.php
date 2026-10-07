@props(['current' => 1])

@php
    $steps = [1 => 'Event Details', 2 => 'Confirm Details'];
@endphp

<div class="flex flex-wrap items-center gap-x-3 gap-y-3">
    @foreach ($steps as $number => $label)
        <div @class(['flex items-center gap-3', 'opacity-65' => $number > $current])>
            <div class="flex size-[35px] shrink-0 items-center justify-center rounded-full border-2 border-white bg-[#81bfba] text-base font-bold text-white">
                {{ $number }}
            </div>
            <span class="text-sm text-white">{{ $label }}</span>
        </div>

        @if ($number < count($steps))
            <div class="hidden h-px w-16 bg-white/50 sm:block"></div>
        @endif
    @endforeach
</div>
