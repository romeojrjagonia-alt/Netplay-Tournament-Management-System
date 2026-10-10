@props([
    'name',
    'players' => [],
    'score' => null,
    'highlight' => false,
])

<div {{ $attributes->class([
    'flex flex-1 items-center justify-between gap-2.5 rounded-[3px] border-[0.5px] bg-white px-2.5 py-[5px]',
    'border-[#a9a9a9]' => ! $highlight,
    'border-[#3fa400] bg-[#f1fbe9]' => $highlight,
]) }}>
    <div class="flex flex-col gap-[5px]">
        <span class="text-[11px] font-bold text-black">{{ $name }}</span>

        <div class="flex flex-wrap items-center gap-[3px]">
            @forelse ($players as $playerName)
                @php
                    $initials = collect(array_filter(explode(' ', trim($playerName))))
                        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
                        ->take(2)
                        ->implode('');
                @endphp

                <div class="flex h-[25px] items-center gap-2.5 pr-2.5">
                    <span class="flex size-[22px] shrink-0 items-center justify-center rounded-full bg-[#138177] text-[7px] font-bold text-white">
                        {{ $initials }}
                    </span>
                    <span class="text-[11px] text-black">{{ $playerName }}</span>
                </div>
            @empty
                <span class="text-[11px] text-black/50 italic">BYE</span>
            @endforelse
        </div>
    </div>

    @if ($score !== null)
        <span class="text-2xl font-bold tabular-nums text-[#0b4742]">{{ $score }}</span>
    @endif
</div>
