<?php

use App\Models\Matches;
use App\Models\User;
use Livewire\Volt\Component;

/**
 * Lets an umpire use the site as a player and switch back again. Nothing about
 * the account changes in the database: the choice is kept in the session, so
 * it ends when they sign out and they are always an umpire again on next login.
 * Shown in the sidebar for umpire accounts only.
 */
new class extends Component {
    public function switchRole(): void
    {
        $user = auth()->user();

        abort_unless($user->canSwitchRoles(), 403);

        if ($user->isInPlayerMode()) {
            session()->forget(User::PLAYER_MODE_SESSION_KEY);
        } else {
            // A live match needs its umpire, so it must be finished first.
            if (Matches::query()->where('assigned_umpire_id', $user->id)->where('status', 'in_progress')->exists()) {
                $this->dispatch('modal-close', name: 'confirm-role-switch');
                $this->dispatch('system-message', title: 'Match in progress', message: 'Finish scoring your current match before switching to the player side.', variant: 'danger');

                return;
            }

            session()->put(User::PLAYER_MODE_SESSION_KEY, true);
            $user->ensurePlayer();
        }

        $this->redirect(route($user->homeRouteName()), navigate: true);
    }
}; ?>

@php $toUmpire = auth()->user()->isInPlayerMode(); @endphp

<div>
    <flux:modal.trigger name="confirm-role-switch">
        <button type="button"
                class="grid w-full cursor-pointer grid-cols-[27px_1fr] items-center gap-2.5 p-2.5 text-left text-sm text-black hover:bg-[#f5faf9]">
            <flux:icon name="arrows-right-left" class="col-start-1 size-5 justify-self-center" />
            <span class="col-start-2">{{ $toUmpire ? 'Switch to Umpire' : 'Switch to Player' }}</span>
        </button>
    </flux:modal.trigger>

    <flux:modal name="confirm-role-switch" class="w-full max-w-md dark:bg-white! dark:ring-black/5!">
        <div class="flex flex-col gap-5 text-black">
            <div class="flex flex-col gap-2">
                <h2 class="text-lg font-bold">{{ $toUmpire ? 'Switch back to the umpire side?' : 'Switch to the player side?' }}</h2>

                @if ($toUmpire)
                    <p class="text-sm text-black/70">
                        You will go back to your umpire dashboard, where you can see and score the matches assigned to you.
                    </p>
                @else
                    <p class="text-sm text-black/70">
                        You will use NetPlay as a player: register for tournaments and follow your own matches.
                        You will not be able to score matches while you are there.
                    </p>
                    <p class="text-sm text-black/70">
                        Your umpire account and assigned matches stay as they are, and you can switch back from the sidebar at any time.
                    </p>
                @endif
            </div>

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <button type="button"
                            class="cursor-pointer rounded-[5px] border border-[#a3a3a3] px-6 py-2 text-sm text-black transition hover:bg-black/5">
                        Cancel
                    </button>
                </flux:modal.close>
                <button type="button" wire:click="switchRole" wire:loading.attr="disabled" wire:target="switchRole"
                        class="cursor-pointer rounded-[5px] bg-[#0d5750] px-6 py-2 text-sm text-white transition hover:bg-[#0b4742] disabled:cursor-not-allowed disabled:opacity-60">
                    <span wire:loading.remove wire:target="switchRole">{{ $toUmpire ? 'Yes, switch to Umpire' : 'Yes, switch to Player' }}</span>
                    <span wire:loading wire:target="switchRole">Switching…</span>
                </button>
            </div>
        </div>
    </flux:modal>
</div>
