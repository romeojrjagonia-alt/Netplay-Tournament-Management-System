<?php

use App\Models\PlayerApplication;
use App\Models\Tournament;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.player', ['title' => 'Dashboard'])] class extends Component {
    public function mount(): void
    {
        abort_unless(auth()->user()->isPlayer(), 403);
    }

    /**
     * The next tournament this player has a confirmed entry in, if any.
     *
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function upcomingTournament(): ?array
    {
        $tournament = Tournament::query()
            ->whereIn('id', $this->applications->where('status', 'approved')->pluck('tournamentCategory.tournament_id'))
            ->whereDate('date_end', '>=', now())
            ->orderBy('date_start')
            ->first();

        return $tournament ? $this->cardFor($tournament, 'UPCOMING TOURNAMENT', 'You are registered') : null;
    }

    /**
     * Tournaments whose registration window is currently open.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function openTournaments(): Collection
    {
        return Tournament::query()
            ->acceptingRegistrations()
            ->orderBy('registration_end')
            ->limit(4)
            ->get()
            ->map(fn (Tournament $tournament) => $this->cardFor(
                $tournament,
                'OPEN FOR REGISTRATION',
                'Accepting Registrations till '.$tournament->registration_end->format('F j, Y'),
            ));
    }

    /**
     * This player's applications, newest first, with the state of each.
     *
     * @return Collection<int, PlayerApplication>
     */
    #[Computed]
    public function applications(): Collection
    {
        $player = auth()->user()->player;

        if (! $player) {
            return collect();
        }

        return $player->playerApplications()
            ->with(['tournamentCategory.tournament', 'tournamentCategory.category'])
            ->latest('player_applications.created_at')
            ->get();
    }

    /**
     * What the player has to do next for an application, if anything.
     */
    public function noteFor(PlayerApplication $application): ?string
    {
        return match ($application->status) {
            'payment_pending' => 'Pay the registration fee with your reference code '.$application->reference_code.'.',
            'rejected' => $application->rejection_reason ? 'Reason: '.$application->rejection_reason : null,
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function cardFor(Tournament $tournament, string $label, string $badge): array
    {
        return [
            'label' => $label,
            'name' => $tournament->name,
            'dates' => $tournament->date_range_label,
            'time' => $tournament->time,
            'days' => $tournament->specific_days ? array_map('trim', explode(',', $tournament->specific_days)) : [],
            'badge' => $badge,
        ];
    }
}; ?>

<div class="flex flex-col gap-5">
    @if ($this->upcomingTournament)
        <x-player.tournament-card
            class="h-[204px] px-10"
            :label="$this->upcomingTournament['label']"
            :name="$this->upcomingTournament['name']"
            :dates="$this->upcomingTournament['dates']"
            :time="$this->upcomingTournament['time']"
            :days="$this->upcomingTournament['days']"
            :badge="$this->upcomingTournament['badge']"
        />
    @endif

    <div class="flex flex-col gap-2.5">
        <h2 class="text-sm font-bold text-white">Currently Open for Registrations</h2>

        <div class="grid gap-[30px] lg:grid-cols-2">
            @forelse ($this->openTournaments as $tournament)
                <x-player.tournament-card
                    wire:key="open-{{ $loop->index }}"
                    class="h-[203px]"
                    badge-placement="top"
                    :label="$tournament['label']"
                    :name="$tournament['name']"
                    :dates="$tournament['dates']"
                    :time="$tournament['time']"
                    :days="$tournament['days']"
                    :badge="$tournament['badge']"
                />
            @empty
                <p class="py-4 text-xs text-white">No tournaments are accepting registrations right now.</p>
            @endforelse
        </div>
    </div>

    <div class="flex flex-col gap-2.5">
        <h2 class="text-sm font-bold text-white">Registration Progress</h2>

        <div class="flex flex-col gap-[15px] rounded-[5px] bg-[#77b9b3]/60 p-5">
            @forelse ($this->applications as $application)
                <x-player.application-row
                    wire:key="application-{{ $application->id }}"
                    :name="$application->tournamentCategory->tournament->name.' — '.$application->tournamentCategory->category_label"
                    :status="$application->status_label"
                    :note="$this->noteFor($application)"
                />
            @empty
                <p class="py-4 text-center text-xs text-white">You have no tournament applications in progress.</p>
            @endforelse
        </div>
    </div>
</div>
