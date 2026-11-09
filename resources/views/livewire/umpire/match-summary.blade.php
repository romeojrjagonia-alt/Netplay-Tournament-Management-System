<?php

use App\Models\Matches;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

/**
 * Result summary of one concluded match the signed-in umpire officiated:
 * who won, the game scores, and links to print or download the scoresheet.
 * Pages open it by dispatching `open-match-summary` with a `matchId`.
 */
new class extends Component {
    public ?int $matchId = null;

    #[On('open-match-summary')]
    public function open(int $matchId): void
    {
        $this->matchId = $matchId;
        unset($this->match);
    }

    #[Computed]
    public function match(): ?Matches
    {
        if ($this->matchId === null) {
            return null;
        }

        return Matches::query()
            ->where('assigned_umpire_id', auth()->id())
            ->where('status', 'completed')
            ->with([
                'tournamentCategory.tournament',
                'tournamentCategory.category',
                'matchApplications.application.applicationPlayers.player',
                'games.rallies',
            ])
            ->find($this->matchId);
    }

    /**
     * @return array<string, array{names: string, won: bool}>
     */
    #[Computed]
    public function teams(): array
    {
        $teams = [];

        foreach (['A', 'B'] as $side) {
            $application = $this->match?->applicationForSide($side);

            $teams[$side] = [
                'names' => $application?->applicationPlayers->pluck('player.name')->filter()->implode(' / ') ?: 'To be determined',
                'won' => $this->match?->winnerSide() === $side,
            ];
        }

        return $teams;
    }
}; ?>

<div>
    <flux:modal name="match-summary" class="w-full max-w-lg dark:bg-white! dark:ring-black/5!">
        @if ($this->match)
            @php
                $match = $this->match;
                $games = collect($match->gameScores())->filter(fn (array $game) => $game['A'] > 0 || $game['B'] > 0);
            @endphp

            <div wire:key="match-summary-{{ $match->id }}" class="flex flex-col gap-5 text-black">
                <div>
                    <p class="text-xs text-black/60">
                        {{ $match->tournamentCategory?->tournament?->name }} &middot; {{ $match->tournamentCategory?->category_label }}
                    </p>
                    <h2 class="text-xl font-bold">Match Summary</h2>
                    <p class="text-xs text-black/60">
                        @if ($match->round) Round {{ $match->round }} @endif
                        @if ($match->court) &middot; {{ $match->court }} @endif
                        @if ($match->scheduled_at) &middot; {{ $match->scheduled_at->format('F j, Y g:i A') }} @endif
                    </p>
                </div>

                <div class="flex flex-col gap-2">
                    @foreach ($this->teams as $side => $team)
                        <div @class([
                            'flex items-center justify-between gap-3 rounded-[5px] border px-4 py-3',
                            'border-[#3fa400] bg-[#9cee69]/25' => $team['won'],
                            'border-[#d3d3d3]' => ! $team['won'],
                        ])>
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold text-black/50">TEAM {{ $side }}</p>
                                <p class="truncate text-sm font-bold">{{ $team['names'] }}</p>
                            </div>
                            @if ($team['won'])
                                <span class="shrink-0 rounded-[3px] bg-[#9cee69] px-2.5 py-0.5 text-[10px] font-bold text-[#3fa400]">WINNER</span>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if ($match->isWalkover())
                    <p class="rounded-[5px] bg-[#e9e0f8] px-4 py-3 text-xs text-[#5d3198]">
                        Walkover &mdash; the other team did not show up, so no points were played.
                    </p>
                @elseif ($games->isNotEmpty())
                    <div class="flex flex-col gap-1.5">
                        <p class="text-xs font-bold">Game scores</p>
                        @foreach ($games as $game)
                            <div class="flex items-center justify-between rounded-[5px] bg-[#11776e]/10 px-4 py-2 text-sm">
                                <span>Game {{ $game['number'] }}</span>
                                <span class="font-bold">{{ $game['A'] }} &ndash; {{ $game['B'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="flex flex-wrap justify-end gap-3">
                    <a href="{{ route('umpire.matches.scoresheet.download', $match) }}"
                       class="rounded-[5px] border border-[#178279] px-5 py-2 text-sm text-[#178279] transition hover:bg-[#178279]/10">
                        Download (.doc)
                    </a>
                    <a href="{{ route('umpire.matches.scoresheet', $match) }}?print=1" target="_blank"
                       class="rounded-[5px] bg-[#0d5750] px-5 py-2 text-sm text-white transition hover:bg-[#0b4742]">
                        Print Scoresheet
                    </a>
                </div>
            </div>
        @else
            <p class="py-6 text-center text-sm text-black/60">Match summary is not available.</p>
        @endif
    </flux:modal>
</div>
