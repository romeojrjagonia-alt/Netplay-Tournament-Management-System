<?php

use App\Models\Matches;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

/**
 * Details of one upcoming match the signed-in umpire is assigned to, with a
 * button on to the scoreboard. Pages open it by dispatching
 * `open-match-details` with a `matchId`.
 */
new class extends Component {
    public ?int $matchId = null;

    #[On('open-match-details')]
    public function open(int $matchId): void
    {
        $this->matchId = $matchId;
        unset($this->match);
        $this->dispatch('match-details-loaded');
    }

    #[Computed]
    public function match(): ?Matches
    {
        if ($this->matchId === null) {
            return null;
        }

        return Matches::query()
            ->where('assigned_umpire_id', auth()->id())
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->with([
                'tournamentCategory.tournament',
                'tournamentCategory.category',
                'matchApplications.application.applicationPlayers.player',
            ])
            ->find($this->matchId);
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function teams(): array
    {
        $teams = [];

        foreach (['A', 'B'] as $side) {
            $teams[$side] = $this->match?->applicationForSide($side)
                ?->applicationPlayers->pluck('player.name')->filter()->implode(' / ') ?: 'To be determined';
        }

        return $teams;
    }

    #[Computed]
    public function ready(): bool
    {
        return $this->match !== null
            && $this->match->applicationForSide('A') !== null
            && $this->match->applicationForSide('B') !== null;
    }
}; ?>

<div x-data="{ loading: false }" @open-match-details.window="loading = true; setTimeout(() => loading = false, 10000)" @match-details-loaded.window="loading = false">
    <flux:modal name="match-details" class="w-full max-w-lg dark:bg-white! dark:ring-black/5!">
    {{-- Shown while the match is being fetched, so the previous match never flashes. --}}
    <div x-show="loading" x-cloak class="flex items-center justify-center gap-3 py-16 text-black/60">
        <span class="size-6 animate-spin rounded-full border-[3px] border-[#0d5750]/20 border-t-[#0d5750]" aria-hidden="true"></span>
        <span class="text-sm">Loading match details…</span>
    </div>

    <div x-show="! loading">
        @if ($this->match)
            @php $match = $this->match; @endphp

            <div wire:key="match-details-{{ $match->id }}" class="flex flex-col gap-5 text-black">
                <div>
                    <p class="text-xs text-black/60">
                        {{ $match->tournamentCategory?->tournament?->name }} &middot; {{ $match->tournamentCategory?->category_label }}
                    </p>
                    <h2 class="text-xl font-bold">Match Details</h2>
                </div>

                <dl class="grid grid-cols-2 gap-3 rounded-[5px] bg-[#11776e]/10 px-4 py-3 text-sm">
                    <div>
                        <dt class="text-[10px] font-bold text-black/50">ROUND</dt>
                        <dd>{{ $match->round ? 'Round '.$match->round : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold text-black/50">COURT</dt>
                        <dd>{{ $match->court ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold text-black/50">DATE</dt>
                        <dd>{{ $match->scheduled_at?->format('F j, Y') ?? 'Not scheduled' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold text-black/50">TIME</dt>
                        <dd>{{ $match->scheduled_at?->format('g:i A') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold text-black/50">STATUS</dt>
                        <dd>{{ $match->status === 'in_progress' ? 'In progress' : 'Scheduled' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold text-black/50">SCORING</dt>
                        <dd>{{ $match->tournamentCategory?->tournament?->scoring_system ?? '—' }}</dd>
                    </div>
                </dl>

                <div class="flex flex-col gap-2">
                    @foreach ($this->teams as $side => $names)
                        <div class="rounded-[5px] border border-[#d3d3d3] px-4 py-3">
                            <p class="text-[10px] font-bold text-black/50">TEAM {{ $side }}</p>
                            <p class="text-sm font-bold">{{ $names }}</p>
                        </div>
                    @endforeach
                </div>

                @if ($this->ready)
                    <div class="flex justify-end">
                        <a href="{{ route('umpire.matches.scoring', $match) }}" wire:navigate
                           class="rounded-[5px] bg-[#0d5750] px-5 py-2 text-sm text-white transition hover:bg-[#0b4742]">
                            {{ $match->status === 'in_progress' ? 'Continue to scoring' : 'Proceed to scoring' }}
                        </a>
                    </div>
                @else
                    <p class="rounded-[5px] bg-[#e9e0f8] px-4 py-3 text-xs text-[#5d3198]">
                        Waiting for both teams to be confirmed before scoring can start.
                    </p>
                @endif
            </div>
        @else
            <p class="py-6 text-center text-sm text-black/60">Match details are not available.</p>
        @endif
    </div>
    </flux:modal>
</div>
