<?php

use App\Models\Matches;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.player', ['title' => 'My Matches'])] class extends Component {
    public string $tab = 'all';

    public function mount(): void
    {
        abort_unless(auth()->user()->isPlayer(), 403);

        auth()->user()->ensurePlayer();
    }

    public function selectTab(string $tab): void
    {
        $this->tab = $tab;
    }

    /**
     * This player's ongoing match(es) first, then those not started yet, soonest first.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function upcomingMatches()
    {
        return $this->playerMatches(['in_progress', 'scheduled'])
            ->sortBy([
                fn (Matches $a, Matches $b) => ($b->status === 'in_progress') <=> ($a->status === 'in_progress'),
                fn (Matches $a, Matches $b) => $a->scheduled_at <=> $b->scheduled_at,
            ])
            ->map(fn (Matches $match) => $this->matchViewData($match))
            ->values();
    }

    /**
     * This player's completed matches, most recent first.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function previousMatches()
    {
        return $this->playerMatches(['completed'])
            ->sortByDesc('scheduled_at')
            ->map(fn (Matches $match) => $this->matchViewData($match))
            ->values();
    }

    /**
     * @param  array<int, string>  $statuses
     * @return \Illuminate\Support\Collection<int, Matches>
     */
    private function playerMatches(array $statuses)
    {
        $playerId = auth()->user()->player->id;

        return Matches::query()
            ->whereIn('status', $statuses)
            ->whereHas(
                'matchApplications.application.applicationPlayers',
                fn ($query) => $query->where('player_id', $playerId)
            )
            ->with([
                'matchApplications.application.applicationPlayers.player',
                'games.rallies',
                'tournamentCategory.tournament',
            ])
            ->get();
    }

    /**
     * Shape a match into the fields the match card component expects:
     * the live score for an ongoing match, or the winner, sets played and
     * per-game scores (with win/lose from this player's side) once done.
     *
     * @return array<string, mixed>
     */
    private function matchViewData(Matches $match): array
    {
        $playerId = auth()->user()->player->id;

        $applicationA = $match->applicationForSide('A');
        $applicationB = $match->applicationForSide('B');
        $mySide = $match->playerSideMap()[$playerId] ?? null;

        $games = $match->gameScores();
        $gamesWonA = collect($games)->where('winner', 'A')->count();
        $gamesWonB = collect($games)->where('winner', 'B')->count();

        $live = null;
        $result = null;

        if ($match->status === 'in_progress') {
            $current = collect($games)->first(fn (array $game) => $game['winner'] === null)
                ?? collect($games)->last();

            $live = [
                'currentGame' => $current['number'] ?? 1,
                'scoreA' => $current['A'] ?? 0,
                'scoreB' => $current['B'] ?? 0,
                'gamesWonA' => $gamesWonA,
                'gamesWonB' => $gamesWonB,
                'finishedGames' => collect($games)->filter(fn (array $game) => $game['winner'] !== null)->values()->all(),
            ];
        }

        if ($match->status === 'completed' && $match->winner_application_id) {
            $winnerSide = null;

            if ($match->winner_application_id === $applicationA?->id) {
                $winnerSide = 'A';
            } elseif ($match->winner_application_id === $applicationB?->id) {
                $winnerSide = 'B';
            }

            $result = [
                'outcome' => $mySide === null || $winnerSide === null ? null : ($winnerSide === $mySide ? 'win' : 'lose'),
                'winnerSide' => $winnerSide,
                'winnerName' => $winnerSide ? $match->sideLabel($winnerSide) : null,
                'walkover' => $match->isWalkover(),
                'setsPlayed' => count($games),
                'gamesWonA' => $gamesWonA,
                'gamesWonB' => $gamesWonB,
                'games' => $games,
            ];
        }

        return [
            'id' => $match->id,
            'court' => $match->court,
            'time' => $match->scheduled_at?->format('g:i A'),
            'date' => $match->scheduled_at?->format('F j, Y'),
            'round' => $match->round,
            'teamAName' => 'Team A',
            'teamAPlayers' => $applicationA?->applicationPlayers->pluck('player.name')->all() ?? [],
            'teamBName' => 'Team B',
            'teamBPlayers' => $applicationB?->applicationPlayers->pluck('player.name')->all() ?? [],
            'live' => $live,
            'result' => $result,
        ];
    }
}; ?>

<div class="flex flex-col gap-5" wire:poll.5s.visible>
    <div class="flex h-10 w-fit max-w-full items-center gap-4 overflow-x-auto rounded-full bg-white px-2.5 py-1.5">
        @foreach (['all' => 'All Matches', 'upcoming' => 'Upcoming Matches', 'concluded' => 'Concluded Matches'] as $key => $label)
            <button type="button" wire:click="selectTab('{{ $key }}')"
                    @class([
                        'flex h-full shrink-0 items-center justify-center rounded-full px-4 text-xs whitespace-nowrap',
                        'bg-[#0b4742] text-white' => $tab === $key,
                        'cursor-pointer text-[#0b4742]' => $tab !== $key,
                    ])>
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($tab === 'all' || $tab === 'upcoming')
        @php
            $ongoing = $this->upcomingMatches->filter(fn (array $match) => $match['live'] !== null);
            $upcoming = $this->upcomingMatches->reject(fn (array $match) => $match['live'] !== null);
        @endphp

        @if ($ongoing->isNotEmpty())
            <div class="flex flex-col gap-2.5">
                <h2 class="text-sm font-bold text-white">Ongoing Match</h2>

                <div class="flex flex-col gap-[17px]">
                    @foreach ($ongoing as $match)
                    <x-player.match-card
                        wire:key="ongoing-{{ $match['id'] }}"
                        :court="$match['court']"
                        :match-time="$match['time']"
                        :match-date="$match['date']"
                        :round="$match['round']"
                        :team-a-name="$match['teamAName']"
                        :team-a-players="$match['teamAPlayers']"
                        :team-b-name="$match['teamBName']"
                        :team-b-players="$match['teamBPlayers']"
                        :live="$match['live']"
                    />
                    @endforeach
                </div>
            </div>
        @endif

        <div class="flex flex-col gap-2.5">
            <h2 class="text-sm font-bold text-white">Upcoming Matches</h2>

            <div class="flex flex-col gap-[17px]">
                @forelse ($upcoming as $match)
                    <x-player.match-card
                        wire:key="upcoming-{{ $match['id'] }}"
                        :court="$match['court']"
                        :match-time="$match['time']"
                        :match-date="$match['date']"
                        :round="$match['round']"
                        :team-a-name="$match['teamAName']"
                        :team-a-players="$match['teamAPlayers']"
                        :team-b-name="$match['teamBName']"
                        :team-b-players="$match['teamBPlayers']"
                        footer-note="Prepare for this match."
                    />
                @empty
                    <p class="py-10 text-center text-sm text-white">You have no upcoming matches.</p>
                @endforelse
            </div>
        </div>
    @endif

    @if ($tab === 'all' || $tab === 'concluded')
        <div class="flex flex-col gap-2.5">
            <h2 class="text-sm font-bold text-white">Previous Matches</h2>

            <div class="flex flex-col gap-[17px]">
                @forelse ($this->previousMatches as $match)
                    <x-player.match-card
                        wire:key="previous-{{ $match['id'] }}"
                        :court="$match['court']"
                        :match-time="$match['time']"
                        :match-date="$match['date']"
                        :round="$match['round']"
                        :team-a-name="$match['teamAName']"
                        :team-a-players="$match['teamAPlayers']"
                        :team-b-name="$match['teamBName']"
                        :team-b-players="$match['teamBPlayers']"
                        :result="$match['result']"
                    />
                @empty
                    <p class="py-10 text-center text-sm text-white">You have no match history yet.</p>
                @endforelse
            </div>
        </div>
    @endif
</div>
