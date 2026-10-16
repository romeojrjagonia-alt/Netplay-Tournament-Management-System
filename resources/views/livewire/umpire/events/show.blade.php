<?php

use App\Models\Matches;
use App\Models\Tournament;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.umpire', ['title' => 'Event'])] class extends Component {
    public Tournament $tournament;

    public string $tab = 'all';

    public function mount(Tournament $tournament): void
    {
        abort_unless(auth()->user()->isUmpire(), 403);

        // Concluded events are archived and only visible to admins.
        abort_if($tournament->is_concluded, 404);

        abort_unless(
            Matches::whereIn('tournament_category_id', $tournament->tournamentCategories()->pluck('id'))
                ->where('assigned_umpire_id', auth()->id())
                ->exists(),
            403
        );

        $this->tournament = $tournament;
    }

    public function selectTab(string $tab): void
    {
        $this->tab = $tab;
    }

    /**
     * This umpire's assigned matches within this tournament, shaped for the
     * match card component.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function matches()
    {
        return Matches::query()
            ->whereIn('tournament_category_id', $this->tournament->tournamentCategories()->pluck('id'))
            ->where('assigned_umpire_id', auth()->id())
            ->when($this->tab === 'upcoming', fn ($query) => $query->whereIn('status', ['scheduled', 'in_progress']))
            ->when($this->tab === 'concluded', fn ($query) => $query->where('status', 'completed'))
            ->with(['matchApplications.application.applicationPlayers.player'])
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (Matches $match) => $this->matchViewData($match));
    }

    /**
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
        ];
    }

    public function formatDateRange(): string
    {
        if ($this->tournament->date_start->isSameMonth($this->tournament->date_end)) {
            return $this->tournament->date_start->format('F j').' - '.$this->tournament->date_end->format('j, Y');
        }

        return $this->tournament->date_start->format('F j, Y').' - '.$this->tournament->date_end->format('F j, Y');
    }
}; ?>

<div class="flex flex-col gap-5">
    <div class="flex flex-col gap-2 border-b border-white/20 pb-5">
        <h2 class="text-xl font-bold text-white sm:text-2xl">{{ $tournament->name }}</h2>

        <div class="flex flex-wrap items-center gap-4 text-xs text-white">
            <span class="flex items-center gap-2">
                <flux:icon.calendar class="size-[15px] shrink-0" />
                {{ $this->formatDateRange() }}
            </span>

            <span class="flex items-center gap-2">
                <flux:icon.clock class="size-[15px] shrink-0" />
                {{ $tournament->time }}
            </span>

            @if ($tournament->specific_days)
                <span class="flex flex-wrap items-center gap-1">
                    @foreach (explode(',', $tournament->specific_days) as $day)
                        <span class="rounded-[5px] bg-[#9cee69] px-2.5 py-0.5 text-[10px] font-bold text-[#051c1a]">{{ trim($day) }}</span>
                    @endforeach
                </span>
            @endif
        </div>
    </div>

    <div class="flex h-10 w-fit max-w-full items-center gap-2 overflow-x-auto rounded-full bg-white px-2.5 py-1.5 [scrollbar-width:none] sm:gap-4">
        <button type="button" wire:click="selectTab('all')" @class([
            'flex h-full shrink-0 items-center justify-center rounded-full px-4 text-xs whitespace-nowrap',
            'bg-[#0b4742] text-white' => $tab === 'all',
            'cursor-pointer text-[#0b4742]' => $tab !== 'all',
        ])>
            All Matches
        </button>
        <button type="button" wire:click="selectTab('upcoming')" @class([
            'flex h-full shrink-0 items-center justify-center rounded-full px-4 text-xs whitespace-nowrap',
            'bg-[#0b4742] text-white' => $tab === 'upcoming',
            'cursor-pointer text-[#0b4742]' => $tab !== 'upcoming',
        ])>
            Upcoming Matches
        </button>
        <button type="button" wire:click="selectTab('concluded')" @class([
            'flex h-full shrink-0 items-center justify-center rounded-full px-4 text-xs whitespace-nowrap',
            'bg-[#0b4742] text-white' => $tab === 'concluded',
            'cursor-pointer text-[#0b4742]' => $tab !== 'concluded',
        ])>
            Concluded Matches
        </button>
    </div>

    <div class="flex flex-col gap-[15px]">
        @forelse ($this->matches as $match)
            <a href="{{ $match['status'] === 'completed' ? '#' : route('umpire.matches.scoring', $match['id']) }}"
               @if ($match['status'] !== 'completed') wire:navigate @endif
               wire:key="match-{{ $match['id'] }}">
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
                    :footer-note="$match['status'] !== 'completed' ? 'Tap to open the scoreboard for this match.' : null"
                />
            </a>
        @empty
            <p class="py-10 text-center text-sm text-white">No matches in this tournament match your filter.</p>
        @endforelse
    </div>
</div>
