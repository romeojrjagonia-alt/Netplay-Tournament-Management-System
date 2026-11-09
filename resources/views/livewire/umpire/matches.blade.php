<?php

use App\Models\Matches;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.umpire', ['title' => 'Assigned Matches'])] class extends Component {
    use WithPagination;

    public const PER_PAGE = 8;

    public string $tab = 'all';

    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->isUmpire(), 403);
    }

    public function selectTab(string $tab): void
    {
        $this->tab = $tab;
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
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
     * @return \Illuminate\Pagination\LengthAwarePaginator<int, array<string, mixed>>
     */
    #[Computed]
    public function previousMatches()
    {
        $matches = $this->umpireMatches(['completed'])->sortByDesc('scheduled_at')->values();
        $page = min($this->getPage(), max(1, (int) ceil($matches->count() / self::PER_PAGE)));

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $matches->forPage($page, self::PER_PAGE)->map(fn (Matches $match) => $this->matchViewData($match))->values(),
            $matches->count(),
            self::PER_PAGE,
            $page,
            ['path' => request()->url()],
        );
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
            ->when(trim($this->search) !== '', function ($query) {
                $term = '%'.trim($this->search).'%';

                $query->where(fn ($query) => $query
                    ->where('court', 'like', $term)
                    ->orWhereHas('matchApplications.application.applicationPlayers.player', fn ($player) => $player->where('name', 'like', $term)));
            })
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
            'status' => $match->status,
            'ready' => $match->matchApplications->count() >= 2,
        ];
    }
}; ?>

<div class="flex flex-col gap-5">
    <div class="flex flex-wrap items-center justify-between gap-4">
    <div class="flex h-10 w-fit max-w-full items-center gap-2 overflow-x-auto rounded-full bg-white px-2.5 py-1.5 [scrollbar-width:none] sm:gap-4">
        <button type="button" wire:click="selectTab('all')"
                @class([
                    'flex h-full shrink-0 items-center justify-center rounded-full px-4 text-xs whitespace-nowrap',
                    'bg-[#0b4742] text-white' => $tab === 'all',
                    'cursor-pointer text-[#0b4742]' => $tab !== 'all',
                ])>
            All Matches
        </button>
        <button type="button" wire:click="selectTab('upcoming')"
                @class([
                    'flex h-full shrink-0 items-center justify-center rounded-full px-4 text-xs whitespace-nowrap',
                    'bg-[#0b4742] text-white' => $tab === 'upcoming',
                    'cursor-pointer text-[#0b4742]' => $tab !== 'upcoming',
                ])>
            Upcoming Matches
        </button>
        <button type="button" wire:click="selectTab('concluded')"
                @class([
                    'flex h-full shrink-0 items-center justify-center rounded-full px-4 text-xs whitespace-nowrap',
                    'bg-[#0b4742] text-white' => $tab === 'concluded',
                    'cursor-pointer text-[#0b4742]' => $tab !== 'concluded',
                ])>
            Concluded Matches
        </button>
    </div>

    <x-collapsible-search wire:model.live.debounce.300ms="search" placeholder="Search player or court" />
    </div>

    @if ($tab !== 'concluded')
        <div class="flex flex-col gap-2.5">
            <h2 class="text-sm font-bold text-white">Upcoming Matches</h2>

            <div class="flex flex-col gap-[17px]">
                @forelse ($this->upcomingMatches as $match)
                    <a @if ($match['ready']) href="{{ route('umpire.matches.scoring', $match['id']) }}" wire:navigate @endif
                       wire:key="upcoming-{{ $match['id'] }}"
                       @class(['block', 'cursor-not-allowed opacity-70' => ! $match['ready']])>
                    <x-player.match-card
                        :court="$match['court']"
                        :match-time="$match['time']"
                        :match-date="$match['date']"
                        :round="$match['round']"
                        :team-a-name="$match['teamAName']"
                        :team-a-players="$match['teamAPlayers']"
                        :team-b-name="$match['teamBName']"
                        :team-b-players="$match['teamBPlayers']"
                        :live="null"
                        :footer-note="! $match['ready']
                            ? 'Waiting for both teams to be confirmed before scoring can start.'
                            : ($match['status'] === 'in_progress' ? 'In progress. Tap to continue scoring this match.' : 'Tap to open the scoreboard and start scoring.')"
                    />
                    </a>
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
                    <flux:modal.trigger name="match-summary" wire:key="previous-{{ $match['id'] }}">
                    <div wire:click="$dispatch('open-match-summary', { matchId: {{ $match['id'] }} })" class="cursor-pointer" role="button">
                    <x-player.match-card
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
                    </div>
                    </flux:modal.trigger>
                @empty
                    <p class="py-10 text-center text-sm text-white">You have no match history yet.</p>
                @endforelse
            </div>

            @if ($this->previousMatches->hasPages())
                <div class="rounded-[5px] bg-white px-4 py-3">{{ $this->previousMatches->links() }}</div>
            @endif
        </div>
    @endif

    <livewire:umpire.match-summary />
</div>
