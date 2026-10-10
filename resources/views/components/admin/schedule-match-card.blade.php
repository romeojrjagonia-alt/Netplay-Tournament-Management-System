@props(['match'])

@php
    // Expects games.rallies and matchApplications.application.applicationPlayers.player
    // eager-loaded by the caller, so a full draw doesn't N+1.
    $playerSideMap = $match->playerSideMap();
    $games = $match->games->sortBy('game_number')->values();
    $gameResults = $games->map(fn ($game) => [
        'scores' => $game->scores($playerSideMap),
        'winner' => $game->winningSide($playerSideMap),
    ]);
    $winnerSide = $match->status === 'completed' ? $match->winnerSide() : null;

    [$badgeLabel, $badgeClasses] = match ($match->status) {
        'in_progress' => ['ONGOING', 'bg-[#9cee69] text-[#051c1a]'],
        'completed' => ['FINAL', 'bg-[#3e948d] text-white'],
        default => ['SCHEDULED', 'bg-white/20 text-white'],
    };
@endphp

<div class="flex flex-col overflow-hidden rounded-[10px] bg-white shadow-[0px_6px_2px_rgba(0,0,0,0.25)]">
    <div class="flex items-center justify-between gap-2 bg-[#051c1a] px-4 py-2.5">
        @if ($match->court || $match->scheduled_at)
            <span class="text-[11px] font-bold text-white">
                {{ $match->court ? 'Court '.$match->court : 'Court TBA' }}
                @if ($match->scheduled_at)
                    · {{ $match->scheduled_at->format('M j') }} · {{ $match->scheduled_at->format('g:i A') }}
                @endif
            </span>
        @else
            <span class="text-[11px] text-white/60 italic">Not yet scheduled</span>
        @endif

        <span class="rounded-full px-2.5 py-0.5 text-[9px] font-bold {{ $badgeClasses }}">{{ $badgeLabel }}</span>
    </div>

    <div class="flex flex-col divide-y divide-black/10">
        @foreach (['A', 'B'] as $side)
            <div class="flex items-center gap-3 px-4 py-3">
                <span @class([
                    'flex size-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold',
                    'bg-[#9cee69] text-[#051c1a]' => $winnerSide === $side,
                    'bg-[#eaf4f3] text-[#051c1a]' => $winnerSide !== $side,
                ])>{{ $loop->iteration }}</span>

                <span @class(['flex-1 text-sm text-black', 'font-bold' => $winnerSide === $side])>
                    {{ $match->sideLabel($side) ?: ($match->status === 'completed' ? 'BYE' : 'TBD') }}
                </span>

                @foreach ($gameResults as $result)
                    <span @class([
                        'flex size-7 items-center justify-center rounded-[5px] text-xs font-bold',
                        'bg-[#9cee69] text-[#051c1a]' => $result['winner'] === $side,
                        'bg-[#d9d9d9] text-black/70' => $result['winner'] !== $side,
                    ])>{{ $result['scores'][$side] }}</span>
                @endforeach
            </div>

            @if ($loop->first)
                <div class="bg-[#eaf4f3] py-0.5 text-center text-[9px] font-bold tracking-wider text-[#3e948d]">VS</div>
            @endif
        @endforeach
    </div>
</div>
