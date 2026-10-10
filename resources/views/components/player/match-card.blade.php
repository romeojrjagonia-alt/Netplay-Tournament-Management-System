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
    'live' => null,
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

            <div class="flex items-center gap-2">
                @if ($live)
                    <span class="flex h-6 w-fit items-center justify-center gap-1.5 rounded-[5px] bg-[#ffd9d9] px-2.5 text-[11px] font-bold text-[#c21f1f]">
                        <span class="size-1.5 animate-pulse rounded-full bg-[#c21f1f]"></span>
                        LIVE
                    </span>
                @endif

                @if ($round)
                    <span class="flex h-6 w-fit items-center justify-center rounded-[5px] bg-[#caa2ff] px-2.5 text-[11px] text-[#5d3198]">
                        Round {{ $round }}
                    </span>
                @endif
            </div>
        </div>

        <div class="flex flex-col gap-[15px] sm:flex-row sm:items-center">
            <x-player.team-panel
                :name="$teamAName"
                :players="$teamAPlayers"
                :score="$live ? $live['scoreA'] : null"
                :highlight="($result['winnerSide'] ?? null) === 'A'"
            />
            <x-player.team-panel
                :name="$teamBName"
                :players="$teamBPlayers"
                :score="$live ? $live['scoreB'] : null"
                :highlight="($result['winnerSide'] ?? null) === 'B'"
            />
        </div>
    </div>

    @if ($live)
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-[#d3d3d3] px-2.5 pt-[5px] text-[13px] text-black">
            <p>Game <span class="font-bold">{{ $live['currentGame'] }}</span> in progress</p>
            <p>Games won <span class="font-bold">{{ $live['gamesWonA'] }} - {{ $live['gamesWonB'] }}</span></p>

            @foreach ($live['finishedGames'] as $game)
                <p class="text-black/70">G{{ $game['number'] }}: {{ $game['A'] }}-{{ $game['B'] }}</p>
            @endforeach
        </div>
    @elseif ($result)
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-[#d3d3d3] px-2.5 pt-[5px]">
            @if ($result['outcome'] === 'win')
                <span class="flex h-[15px] w-fit items-center justify-center rounded-[3px] bg-[#9cee69] px-2.5 text-[8px] font-bold text-[#3fa400]">WIN</span>
            @elseif ($result['outcome'] === 'lose')
                <span class="flex h-[15px] w-fit items-center justify-center rounded-[3px] bg-[#bd6b6c] px-2.5 text-[8px] font-bold text-[#ffb8b8]">LOSE</span>
            @endif

            @if ($result['winnerName'])
                <p class="text-[13px] text-black">Winner <span class="font-bold">{{ $result['winnerName'] }}</span></p>
            @endif

            <p class="text-[13px] text-black">
                {{ $result['setsPlayed'] }} {{ \Illuminate\Support\Str::plural('set', $result['setsPlayed']) }} played
                ({{ $result['gamesWonA'] }}-{{ $result['gamesWonB'] }})
            </p>

            @foreach ($result['games'] as $game)
                @if ($game['A'] > 0 || $game['B'] > 0)
                    <p class="text-[13px] text-black/70">G{{ $game['number'] }}: {{ $game['A'] }}-{{ $game['B'] }}</p>
                @endif
            @endforeach
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
