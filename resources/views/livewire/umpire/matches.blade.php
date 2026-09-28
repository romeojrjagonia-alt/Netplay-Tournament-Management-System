<?php

use App\Models\Matches;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.umpire', ['title' => 'Assigned Matches'])] class extends Component {
    public string $tab = 'all';

    public function mount(): void
    {
        abort_unless(auth()->user()->isUmpire(), 403);
    }

    public function selectTab(string $tab): void
    {
        $this->tab = $tab;
    }

    /**
     * This umpire's matches that are scheduled or in progress, soonest first.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function upcomingMatches()
    {
        return $this->umpireMatches(['scheduled', 'in_progress'])
            ->sortBy('scheduled_at')
            ->map(fn (Matches $match) => $this->matchViewData($match))
            ->values();
    }

    /**
     * This umpire's completed matches, most recent first.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function previousMatches()
    {
        return $this->umpireMatches(['completed'])
            ->sortByDesc('scheduled_at')
            ->map(fn (Matches $match) => $this->matchViewData($match))
            ->values();
    }

    /**
     * @param  array<int, string>  $statuses
     * @return \Illuminate\Support\Collection<int, Matches>
     */
    private function umpireMatches(array $statuses)
    {
        return Matches::query()
            ->where('assigned_umpire_id', auth()->id())
            ->whereIn('status', $statuses)
            ->with(['matchApplications.application.applicationPlayers.player'])
            ->get();
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

<div class="flex flex-col gap-5">
    <div class="flex h-10 w-fit items-center gap-4 rounded-full bg-white px-2.5 py-1.5">
        <button type="button" wire:click="selectTab('all')"
                @class([
                    'flex h-full items-center justify-center rounded-full px-4 text-xs',
                    'bg-[#0b4742] text-white' => $tab === 'all',
                    'cursor-pointer text-[#0b4742]' => $tab !== 'all',
                ])>
            All Matches
        </button>
        <button type="button" wire:click="selectTab('upcoming')"
                @class([
                    'flex h-full items-center justify-center rounded-full px-4 text-xs',
                    'bg-[#0b4742] text-white' => $tab === 'upcoming',
                    'cursor-pointer text-[#0b4742]' => $tab !== 'upcoming',
                ])>
            Upcoming Matches
        </button>
        <button type="button" wire:click="selectTab('concluded')"
                @class([
                    'flex h-full items-center justify-center rounded-full px-4 text-xs',
                    'bg-[#0b4742] text-white' => $tab === 'concluded',
                    'cursor-pointer text-[#0b4742]' => $tab !== 'concluded',
                ])>
            Concluded Matches
        </button>
    </div>

    @if ($tab !== 'concluded')
        <div class="flex flex-col gap-2.5">
            <h2 class="text-sm font-bold text-white">Upcoming Matches</h2>

            <div class="flex flex-col gap-[17px]">
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
                    <p class="py-10 text-center text-sm text-white">You have no upcoming assigned matches.</p>
                @endforelse
            </div>
        </div>
    @endif

    @if ($tab !== 'upcoming')
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
                        :winner="$match['winner']"
                    />
                @empty
                    <p class="py-10 text-center text-sm text-white">You have no match history yet.</p>
                @endforelse
            </div>
        </div>
    @endif
</div>
