<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.player', ['title' => 'Dashboard'])] class extends Component {
    public function mount(): void
    {
        abort_unless(auth()->user()->isPlayer(), 403);
    }

    /**
     * The next tournament this player is entered in.
     *
     * TODO: replace with the player's next Entry once tournaments/entries exist.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function upcomingTournament(): array
    {
        return [
            'label' => 'UPCOMING TOURNAMENT',
            'name' => 'Inato Tournament',
            'dates' => 'July 1 - 31, 2026',
            'time' => '9:00 AM - 5:00 PM',
            'days' => ['Sat', 'Sun'],
            'badge' => 'Accepting Registrations Starting Sept. 28, 2026',
        ];
    }

    /**
     * Tournaments whose registration window is currently open.
     *
     * TODO: replace with Tournament::openForRegistration()->get().
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function openTournaments(): array
    {
        return [
            [
                'label' => 'ONGOING TOURNAMENT',
                'name' => 'Inato Tournament',
                'dates' => 'July 1 - 31, 2026',
                'time' => '9:00 AM - 5:00 PM',
                'days' => ['Sat', 'Sun'],
                'badge' => 'Accepting Registrations till August 12, 2026',
            ],
            [
                'label' => 'ONGOING TOURNAMENT',
                'name' => 'Inato Tournament',
                'dates' => 'July 1 - 31, 2026',
                'time' => '9:00 AM - 5:00 PM',
                'days' => ['Sat', 'Sun'],
                'badge' => 'Accepting Registrations till August 12, 2026',
            ],
        ];
    }

    /**
     * This player's in-flight applications and where each one sits.
     *
     * TODO: replace with the player's applications, deriving `status` from
     * the approval state and the latest verified payment row.
     *
     * @return array<int, array<string, string|null>>
     */
    #[Computed]
    public function applications(): array
    {
        return [
            [
                'name' => 'Inato Tournament',
                'status' => 'Waiting for organizers verification',
                'note' => null,
            ],
            [
                'name' => 'Inato Tournament',
                'status' => 'Verification confirmed, proceed to payment',
                'note' => 'Proceed to payment procedures within 5 days.',
            ],
        ];
    }
}; ?>

<div class="flex flex-col gap-5">
    <x-player.tournament-card
        class="h-[204px] px-10"
        :label="$this->upcomingTournament['label']"
        :name="$this->upcomingTournament['name']"
        :dates="$this->upcomingTournament['dates']"
        :time="$this->upcomingTournament['time']"
        :days="$this->upcomingTournament['days']"
        :badge="$this->upcomingTournament['badge']"
    />

    <div class="flex flex-col gap-2.5">
        <h2 class="text-sm font-bold text-white">Currently Open for Registrations</h2>

        <div class="grid gap-[30px] lg:grid-cols-2">
            @foreach ($this->openTournaments as $tournament)
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
            @endforeach
        </div>
    </div>

    <div class="flex flex-col gap-2.5">
        <h2 class="text-sm font-bold text-white">Registration Progress</h2>

        <div class="flex flex-col gap-[15px] rounded-[5px] bg-[#77b9b3]/60 p-5">
            @forelse ($this->applications as $application)
                <x-player.application-row
                    wire:key="application-{{ $loop->index }}"
                    :name="$application['name']"
                    :status="$application['status']"
                    :note="$application['note']"
                />
            @empty
                <p class="py-4 text-center text-xs text-white">You have no tournament applications in progress.</p>
            @endforelse
        </div>
    </div>
</div>
