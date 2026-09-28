@props([
    'court' => null,
    'matchTime' => null,
    'matchDate' => null,
    'round' => null,
    'teamAName' => 'Team A',
    'teamAPlayers' => [],
    'teamBName' => 'Team B',
    'teamBPlayers' => [],
    'footerNote' => null,
    'result' => null,
    'winner' => null,
])

<div {{ $attributes->class(['flex flex-col gap-[11px] rounded-[5px] border border-[#d3d3d3] bg-white px-5 pt-2.5 pb-5']) }}>
    <div class="flex flex-col gap-2.5">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-[15px]">
                @if ($court)
                    <span class="flex items-center gap-2 text-[10px] text-black">
                        <flux:icon.map-pin class="size-[15px] shrink-0" />
                        {{ $court }}
                    </span>
                @endif

                @if ($matchTime)
                    <span class="flex items-center gap-2 text-[10px] text-black">
                        <flux:icon.clock class="size-[15px] shrink-0" />
                        {{ $matchTime }}
                    </span>
                @endif

                @if ($matchDate)
                    <span class="flex items-center gap-2 text-[10px] text-black">
                        <flux:icon.calendar class="size-[15px] shrink-0" />
                        {{ $matchDate }}
                    </span>
                @endif
            </div>

            @if ($round)
                <span class="flex h-6 w-fit items-center justify-center rounded-[5px] bg-[#caa2ff] px-2.5 text-[11px] text-[#5d3198]">
                    Round {{ $round }}
                </span>
            @endif
        </div>

        <div class="flex flex-col gap-[15px] sm:flex-row sm:items-center">
            <x-player.team-panel :name="$teamAName" :players="$teamAPlayers" />
            <x-player.team-panel :name="$teamBName" :players="$teamBPlayers" />
        </div>
    </div>

    @if ($result)
        <div class="flex items-center gap-2.5 border-t border-[#d3d3d3] px-2.5 pt-[5px]">
            @if ($result['outcome'] === 'win')
                <span class="flex h-[15px] w-fit items-center justify-center rounded-[3px] bg-[#9cee69] px-2.5 text-[8px] font-bold text-[#3fa400]">WIN</span>
            @else
                <span class="flex h-[15px] w-fit items-center justify-center rounded-[3px] bg-[#bd6b6c] px-2.5 text-[8px] font-bold text-[#ffb8b8]">LOSE</span>
            @endif

            <p class="text-[13px] text-black">{{ $result['note'] }}</p>
        </div>
    @elseif ($winner)
        <div class="flex items-center border-t border-[#d3d3d3] px-2.5 pt-[5px]">
            <p class="text-[13px] text-black">Winner <span class="font-bold">{{ $winner }}</span></p>
        </div>
    @elseif ($footerNote)
        <div class="flex items-center border-t border-[#d3d3d3] px-2.5 pt-[5px]">
            <p class="text-[13px] text-black">{{ $footerNote }}</p>
        </div>
    @endif
</div>
