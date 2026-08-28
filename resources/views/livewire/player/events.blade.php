<?php

use App\Models\PlayerApplication;
use App\Models\Tournament;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.player', ['title' => 'Events'])] class extends Component {
    public string $tab = 'accepting';

    public string $search = '';

    public ?int $viewingTournamentId = null;

    public ?int $viewingApplicationId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->isPlayer(), 403);
    }

    public function selectTab(string $tab): void
    {
        $this->tab = $tab;
    }

    public function viewTournament(int $tournamentId): void
    {
        $this->viewingTournamentId = $tournamentId;
    }

    public function viewApplication(int $applicationId): void
    {
        $this->viewingApplicationId = $applicationId;
    }

    /**
     * Tournaments currently open for registration, optionally filtered by name.
     *
     * @return \Illuminate\Support\Collection<int, Tournament>
     */
    #[Computed]
    public function tournaments()
    {
        return Tournament::query()
            ->acceptingRegistrations()
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('registration_end')
            ->get();
    }

    #[Computed]
    public function viewingTournament(): ?Tournament
    {
        if (! $this->viewingTournamentId) {
            return null;
        }

        return Tournament::query()
            ->with('tournamentCategories.category')
            ->find($this->viewingTournamentId);
    }

    /**
     * This player's tournament applications, optionally filtered by tournament name.
     *
     * @return \Illuminate\Support\Collection<int, PlayerApplication>
     */
    #[Computed]
    public function myApplications()
    {
        return auth()->user()->player
            ->playerApplications()
            ->with(['tournamentCategory.tournament', 'tournamentCategory.category'])
            ->when(
                $this->search !== '',
                fn ($query) => $query->whereHas(
                    'tournamentCategory.tournament',
                    fn ($q) => $q->where('name', 'like', '%'.$this->search.'%')
                )
            )
            ->latest()
            ->get();
    }

    /**
     * The currently viewed application, scoped to this player so a viewer
     * can never load another player's application by guessing an ID.
     */
    #[Computed]
    public function viewingApplication(): ?PlayerApplication
    {
        if (! $this->viewingApplicationId) {
            return null;
        }

        return auth()->user()->player
            ->playerApplications()
            ->with(['tournamentCategory.tournament', 'tournamentCategory.category', 'paymentDetails'])
            ->find($this->viewingApplicationId);
    }

    public function formatDateRange(Tournament $tournament): string
    {
        if ($tournament->date_start->isSameMonth($tournament->date_end)) {
            return $tournament->date_start->format('F j').' - '.$tournament->date_end->format('j, Y');
        }

        return $tournament->date_start->format('F j, Y').' - '.$tournament->date_end->format('F j, Y');
    }
}; ?>

<div class="flex flex-col gap-5">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex h-10 items-center gap-4 rounded-full bg-white px-2.5 py-1.5">
            <button type="button" wire:click="selectTab('accepting')"
                    @class([
                        'flex h-full items-center justify-center rounded-full px-4 text-xs',
                        'bg-[#0b4742] text-white' => $tab === 'accepting',
                        'cursor-pointer text-[#0b4742]' => $tab !== 'accepting',
                    ])>
                Accepting Registrations
            </button>
            <button type="button" wire:click="selectTab('applications')"
                    @class([
                        'flex h-full items-center justify-center rounded-full px-4 text-xs',
                        'bg-[#0b4742] text-white' => $tab === 'applications',
                        'cursor-pointer text-[#0b4742]' => $tab !== 'applications',
                    ])>
                My Applications
            </button>
        </div>

        <div class="flex w-[265px] items-center gap-2.5 rounded-[10px] bg-white px-5 py-2.5">
            <flux:icon.magnifying-glass class="size-[17px] shrink-0 text-black/50" />
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search"
                   class="w-full bg-transparent text-xs text-black placeholder:text-black/50 focus:outline-none">
        </div>
    </div>

    @if ($tab === 'accepting')
        <div class="flex flex-col gap-[15px]">
            @forelse ($this->tournaments as $tournament)
                <flux:modal.trigger name="tournament-details" wire:key="tournament-{{ $tournament->id }}">
                    <x-player.event-card
                        wire:click="viewTournament({{ $tournament->id }})"
                        :name="$tournament->name"
                        :poster-url="$tournament->poster_url"
                        :dates="$this->formatDateRange($tournament)"
                        :days="$tournament->specific_days"
                        :time="$tournament->time"
                        :deadline="$tournament->registration_end->format('F j, Y')"
                    />
                </flux:modal.trigger>
            @empty
                <p class="py-10 text-center text-sm text-white">No tournaments are currently accepting registrations.</p>
            @endforelse
        </div>
    @else
        <div class="flex flex-col gap-[15px] rounded-[5px] bg-[#77b9b3]/60 p-5">
            @forelse ($this->myApplications as $application)
                <flux:modal.trigger name="application-details" wire:key="application-{{ $application->id }}">
                    <x-player.application-row
                        wire:click="viewApplication({{ $application->id }})"
                        class="cursor-pointer"
                        :name="$application->tournamentCategory->tournament->name.' — '.$application->tournamentCategory->category->name"
                        :status="$application->status_label"
                    />
                </flux:modal.trigger>
            @empty
                <p class="py-10 text-center text-sm text-white">You have no tournament applications yet.</p>
            @endforelse
        </div>
    @endif

    <flux:modal name="tournament-details" class="w-full max-w-2xl dark:bg-white! dark:ring-black/5!">
        @if ($this->viewingTournament)
            <div class="flex flex-col gap-6">
                <div class="flex flex-col gap-1">
                    <h2 class="text-xl font-bold text-black">{{ $this->viewingTournament->name }}</h2>
                    <p class="text-sm text-black/70">{{ $this->formatDateRange($this->viewingTournament) }}</p>
                </div>

                <div class="grid grid-cols-3 gap-4 text-sm">
                    <div>
                        <p class="text-xs font-bold text-black">Format</p>
                        <p class="text-black/70">{{ Str::headline($this->viewingTournament->format) }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-black">Sport</p>
                        <p class="text-black/70">{{ $this->viewingTournament->sport }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-black">Registration Deadline</p>
                        <p class="text-black/70">{{ $this->viewingTournament->registration_end->format('F j, Y') }}</p>
                    </div>
                </div>

                <div class="flex flex-col gap-2.5">
                    <p class="text-xs font-bold text-black">Categories</p>

                    <div class="flex flex-col gap-2.5">
                        @foreach ($this->viewingTournament->tournamentCategories as $tournamentCategory)
                            <div class="flex items-center justify-between rounded-[5px] border border-[#d3d3d3] px-5 py-3 text-sm">
                                <span class="font-bold text-black">{{ $tournamentCategory->category->name }}</span>
                                <span class="text-black/70">₱{{ number_format((float) $tournamentCategory->registration_fee, 2) }}</span>
                                <span class="text-black/70">{{ $tournamentCategory->max_slots }} slots</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="button" disabled class="cursor-not-allowed rounded-[5px] border border-[#d3d3d3] px-6 py-2 text-sm text-black/40">
                        Apply
                    </button>
                </div>
            </div>
        @endif
    </flux:modal>

    <flux:modal name="application-details" class="w-full max-w-2xl dark:bg-white! dark:ring-black/5!">
        @if ($this->viewingApplication)
            <div class="flex flex-col gap-6">
                <div class="flex flex-col gap-1">
                    <h2 class="text-xl font-bold text-black">{{ $this->viewingApplication->tournamentCategory->tournament->name }}</h2>
                    <p class="text-sm text-black/70">{{ $this->viewingApplication->tournamentCategory->category->name }}</p>
                </div>

                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-xs font-bold text-black">Status</p>
                        <p class="text-black/70">{{ $this->viewingApplication->status_label }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-black">Applied On</p>
                        <p class="text-black/70">{{ $this->viewingApplication->created_at->format('F j, Y') }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-black">Registration Fee</p>
                        <p class="text-black/70">₱{{ number_format((float) $this->viewingApplication->tournamentCategory->registration_fee, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-black">Payment</p>
                        <p class="text-black/70">{{ $this->viewingApplication->paymentDetails->last()?->payment_status ?? 'No payment submitted yet' }}</p>
                    </div>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
