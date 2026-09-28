<?php

use App\Models\Matches;
use App\Models\Tournament;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.umpire', ['title' => 'Dashboard'])] class extends Component {
    public function mount(): void
    {
        abort_unless(auth()->user()->isUmpire(), 403);
    }

    /**
     * The tournament behind this umpire's soonest upcoming assigned match,
     * falling back to their most recently assigned tournament if none are
     * upcoming.
     */
    #[Computed]
    public function assignedTournament(): ?Tournament
    {
        $match = $this->umpireMatches(['scheduled', 'in_progress'])
            ->with('tournamentCategory.tournament')
            ->orderBy('scheduled_at')
            ->first()
            ?? $this->umpireMatches()->with('tournamentCategory.tournament')->orderByDesc('scheduled_at')->first();

        return $match?->tournamentCategory?->tournament;
    }

    #[Computed]
    public function assignedMatchesCount(): int
    {
        return $this->umpireMatches(['scheduled', 'in_progress'])->count();
    }

    #[Computed]
    public function accomplishedMatchesCount(): int
    {
        return $this->umpireMatches(['completed'])->count();
    }

    /**
     * This umpire's scheduled/in-progress matches, soonest first.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function upcomingMatches()
    {
        return $this->umpireMatches(['scheduled', 'in_progress'])
            ->with(['matchApplications.application.applicationPlayers.player'])
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (Matches $match) => $this->matchViewData($match));
    }

    /**
     * The most recent match this umpire has officiated to completion.
     *
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function previousMatch(): ?array
    {
        $match = $this->umpireMatches(['completed'])
            ->with(['matchApplications.application.applicationPlayers.player'])
            ->orderByDesc('scheduled_at')
            ->first();

        return $match ? $this->matchViewData($match) : null;
    }

    /**
     * @param  array<int, string>  $statuses
     */
    private function umpireMatches(array $statuses = []): \Illuminate\Database\Eloquent\Builder
    {
        $query = Matches::query()->where('assigned_umpire_id', auth()->id());

        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        return $query;
    }

    /**
     * Shape a match into the fields the match card component expects.
     *
     * @return array<string, mixed>
     */
    private function matchViewData(Matches $match): array
    {
        $teamApplications = $match->matchApplications->pluck('application');
        $teamA = $teamApplications->get(0);
        $teamB = $teamApplications->get(1);

        $winner = null;

        if ($match->status === 'completed' && $match->winner_application_id) {
            $winner = $match->winner_application_id === $teamA?->id ? 'Team A' : 'Team B';
        }

        return [
            'id' => $match->id,
            'court' => $match->court,
            'time' => $match->scheduled_at?->format('g:i A'),
            'date' => $match->scheduled_at?->format('F j, Y'),
            'round' => $match->round,
            'teamAName' => 'Team A',
            'teamAPlayers' => $teamA?->applicationPlayers->pluck('player.name')->all() ?? [],
            'teamBName' => 'Team B',
            'teamBPlayers' => $teamB?->applicationPlayers->pluck('player.name')->all() ?? [],
            'winner' => $winner,
        ];
    }
}; ?>

<div class="flex flex-col gap-[17px]">
    <div class="flex flex-col gap-2.5">
        <p class="text-xs text-white">Current Tournament Assigned</p>

        <div class="flex flex-col gap-5 lg:flex-row lg:items-center">
            @if ($this->assignedTournament)
                <x-player.tournament-card
                    class="h-[118px] w-full px-5 lg:max-w-[572px] lg:shrink-0"
                    label="ASSIGNED TOURNAMENT"
                    :name="$this->assignedTournament->name"
                    :dates="$this->assignedTournament->date_start->format('F j').'- '.$this->assignedTournament->date_end->format('j, Y')"
                    :time="$this->assignedTournament->time"
                />
            @else
                <div class="flex h-[118px] w-full items-center justify-center rounded-[5px] bg-white/10 px-5 lg:max-w-[572px] lg:shrink-0">
                    <p class="text-xs text-white">You have no assigned tournament yet.</p>
                </div>
            @endif

            <div class="flex flex-col gap-5 sm:flex-row">
                <x-admin.analytics-card class="shrink-0" label="ASSIGNED MATCHES" :value="$this->assignedMatchesCount" />
                <x-admin.analytics-card class="shrink-0" label="ACCOMPLISHED MATCHES" :value="$this->accomplishedMatchesCount" />
            </div>
        </div>
    </div>

    <div class="flex flex-col gap-[27px] lg:flex-row lg:items-start">
        <div class="flex flex-1 flex-col gap-[19px]">
            <p class="text-xs font-bold text-white">Upcoming Assigned Tournaments</p>

            <div class="flex max-h-[516px] flex-col gap-[15px] overflow-y-auto rounded-[5px] bg-[#4ad8cc]/20 p-[25px]">
                @forelse ($this->upcomingMatches as $match)
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
                        footer-note="You are assigned as umpire for this match."
                    />
                @empty
                    <p class="w-full py-10 text-center text-sm text-white">You have no upcoming assigned matches.</p>
                @endforelse
            </div>
        </div>

        <div class="flex w-full flex-col gap-[23px] lg:w-[530px] lg:shrink-0">
            <p class="text-xs font-bold text-white">Previous Match Result</p>

            @if ($this->previousMatch)
                <x-player.match-card
                    :court="$this->previousMatch['court']"
                    :match-time="$this->previousMatch['time']"
                    :match-date="$this->previousMatch['date']"
                    :round="$this->previousMatch['round']"
                    :team-a-name="$this->previousMatch['teamAName']"
                    :team-a-players="$this->previousMatch['teamAPlayers']"
                    :team-b-name="$this->previousMatch['teamBName']"
                    :team-b-players="$this->previousMatch['teamBPlayers']"
                    :winner="$this->previousMatch['winner']"
                />
            @else
                <p class="py-10 text-center text-sm text-white">You have no completed matches yet.</p>
            @endif
        </div>
    </div>
</div>
