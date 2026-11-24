<?php

use App\Models\PlayerLinkRequest;
use App\Support\Players\GuestPlayerMatcher;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    /** @var array<int, int|string> Guest player ids ticked by the player. */
    public array $selected = [];

    /**
     * Guest records registered under this account's verified email that it
     * has not yet answered. Only names and tournaments are shown, never
     * contact details.
     *
     * @return Collection<int, array{id: int, name: string, tournaments: string}>
     */
    #[Computed]
    public function candidates(): Collection
    {
        return app(GuestPlayerMatcher::class)->candidatesFor(auth()->user())
            ->map(fn ($player) => [
                'id' => $player->id,
                'name' => $player->name,
                'tournaments' => $player->applicationPlayers
                    ->map(fn ($applicationPlayer) => $applicationPlayer->application?->tournamentCategory?->tournament?->name)
                    ->filter()
                    ->unique()
                    ->implode(', ') ?: 'No tournaments yet',
            ]);
    }

    #[Computed]
    public function pendingCount(): int
    {
        return auth()->user()->playerLinkRequests()->pending()->count();
    }

    public function requestLink(): void
    {
        $ids = $this->claimedIds();

        if ($ids->isEmpty()) {
            $this->addError('selected', 'Tick the records that are yours, or choose "Not me".');

            return;
        }

        $user = auth()->user();

        foreach ($ids as $playerId) {
            PlayerLinkRequest::firstOrCreate(
                ['user_id' => $user->id, 'player_id' => $playerId],
                ['status' => PlayerLinkRequest::PENDING],
            );
        }

        $this->afterAnswer();
        $this->dispatch('system-message', title: 'Request sent', message: 'An organizer will review it and link your earlier registrations to your account.', variant: 'success');
    }

    /**
     * "Not me": remembered so the same records are never offered again.
     */
    public function decline(): void
    {
        $user = auth()->user();

        foreach ($this->candidates->pluck('id') as $playerId) {
            PlayerLinkRequest::firstOrCreate(
                ['user_id' => $user->id, 'player_id' => $playerId],
                ['status' => PlayerLinkRequest::DECLINED, 'decided_at' => now()],
            );
        }

        $this->afterAnswer();
    }

    /**
     * Only ids that were actually offered can be claimed.
     *
     * @return Collection<int, int>
     */
    private function claimedIds(): Collection
    {
        return collect($this->selected)->map(fn ($id) => (int) $id)
            ->intersect($this->candidates->pluck('id'))
            ->values();
    }

    private function afterAnswer(): void
    {
        $this->reset('selected');
        unset($this->candidates, $this->pendingCount);
    }
}; ?>

<div>
    @if ($this->candidates->isNotEmpty())
        <div class="flex flex-col gap-3 rounded-[5px] bg-white p-5 text-black">
            <div>
                <p class="text-sm font-bold">We found earlier registrations under your email</p>
                <p class="text-xs text-black/70">
                    If these were you, request to link them so your match history and level appear on this account.
                    An organizer reviews every request.
                </p>
            </div>

            <div class="flex flex-col gap-2">
                @foreach ($this->candidates as $candidate)
                    <label class="flex cursor-pointer items-start gap-3 rounded-[5px] border border-[#d3d3d3] px-3 py-2" wire:key="candidate-{{ $candidate['id'] }}">
                        <input type="checkbox" wire:model="selected" value="{{ $candidate['id'] }}" class="mt-0.5">
                        <span class="flex flex-col">
                            <span class="text-xs font-bold">{{ $candidate['name'] }}</span>
                            <span class="text-[11px] text-black/60">{{ $candidate['tournaments'] }}</span>
                        </span>
                    </label>
                @endforeach
            </div>

            @error('selected')
                <p class="text-[11px] text-red-600">{{ $message }}</p>
            @enderror

            <div class="flex flex-wrap gap-3">
                <button type="button" wire:click="requestLink" class="cursor-pointer rounded-[5px] bg-[#3e948d] px-4 py-2 text-xs font-medium text-white">
                    These are mine - request link
                </button>
                <button type="button" wire:click="decline" class="cursor-pointer rounded-[5px] border border-[#a3a3a3] px-4 py-2 text-xs font-medium text-[#6b6b6b]">
                    Not me
                </button>
            </div>
        </div>
    @elseif ($this->pendingCount > 0)
        <div class="rounded-[5px] bg-white/90 px-5 py-3 text-xs text-black">
            Your request to link earlier registrations is awaiting organizer review.
        </div>
    @endif
</div>
