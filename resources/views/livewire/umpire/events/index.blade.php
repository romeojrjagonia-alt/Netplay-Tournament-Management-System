<?php

use App\Models\Tournament;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.umpire', ['title' => 'Events'])] class extends Component {
    public function mount(): void
    {
        abort_unless(auth()->user()->isUmpire(), 403);
    }

    public string $tab = 'all';

    public string $search = '';

    public function selectTab(string $tab): void
    {
        $this->tab = $tab;
    }

    /**
     * Tournaments this umpire has at least one assigned match in.
     *
     * @return Collection<int, Tournament>
     */
    #[Computed]
    public function tournaments(): Collection
    {
        return Tournament::query()
            ->whereHas(
                'tournamentCategories.matches',
                fn ($query) => $query->where('assigned_umpire_id', auth()->id())
            )
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->tab === 'upcoming', fn ($query) => $query->whereDate('date_end', '>=', now()))
            ->when($this->tab === 'concluded', fn ($query) => $query->whereDate('date_end', '<', now()))
            ->latest('date_start')
            ->get();
    }

    public function formatDateRange(Tournament $tournament): string
    {
        if ($tournament->date_start->isSameMonth($tournament->date_end)) {
            return $tournament->date_start->format('F j').' - '.$tournament->date_end->format('j, Y');
        }

        return $tournament->date_start->format('F j, Y').' - '.$tournament->date_end->format('F j, Y');
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex h-10 w-fit items-center gap-4 rounded-full bg-white px-2.5 py-1.5">
            <button type="button" wire:click="selectTab('all')" @class([
                'flex h-full items-center justify-center rounded-full px-4 text-xs',
                'bg-[#0b4742] text-white' => $tab === 'all',
                'cursor-pointer text-[#0b4742]' => $tab !== 'all',
            ])>
                All Events
            </button>
            <button type="button" wire:click="selectTab('upcoming')" @class([
                'flex h-full items-center justify-center rounded-full px-4 text-xs',
                'bg-[#0b4742] text-white' => $tab === 'upcoming',
                'cursor-pointer text-[#0b4742]' => $tab !== 'upcoming',
            ])>
                Upcoming Events
            </button>
            <button type="button" wire:click="selectTab('concluded')" @class([
                'flex h-full items-center justify-center rounded-full px-4 text-xs',
                'bg-[#0b4742] text-white' => $tab === 'concluded',
                'cursor-pointer text-[#0b4742]' => $tab !== 'concluded',
            ])>
                Concluded Events
            </button>
        </div>

        <div class="flex w-full items-center gap-2.5 rounded-[10px] bg-white px-5 py-2.5 sm:w-[265px]">
            <flux:icon.magnifying-glass class="size-[17px] shrink-0 text-black/50" />
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search"
                   class="w-full bg-transparent text-xs text-black placeholder:text-black/50 focus:outline-none">
        </div>
    </div>

    <div class="flex flex-col gap-[10px]">
        @forelse ($this->tournaments as $tournament)
            <a href="{{ route('umpire.events.show', $tournament) }}" wire:navigate>
                <x-player.event-card
                    :name="$tournament->name"
                    :poster-url="$tournament->poster_url"
                    :dates="$this->formatDateRange($tournament)"
                    :days="$tournament->specific_days"
                    :time="$tournament->time"
                />
            </a>
        @empty
            <p class="py-10 text-center text-sm text-white">You have no assigned tournaments matching this filter.</p>
        @endforelse
    </div>
</div>
