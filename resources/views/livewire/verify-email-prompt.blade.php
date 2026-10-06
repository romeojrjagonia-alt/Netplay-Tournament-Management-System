<?php

use App\Livewire\Actions\SendVerificationEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Volt\Component;

new class extends Component {
    public bool $show = false;

    /**
     * Why the prompt opened: "signup" (just registered) or "login" (reminder).
     */
    public string $reason = 'login';

    public bool $sent = false;

    public ?string $error = null;

    /**
     * Open once per login/signup for users whose email is still unverified.
     * The "verification_prompt" session flag is set by those flows and
     * consumed here, so the prompt does not repeat on later pages.
     */
    public function mount(): void
    {
        $reason = Session::pull('verification_prompt');

        if ($reason !== null && ! Auth::user()->hasVerifiedEmail()) {
            $this->reason = $reason;
            $this->show = true;
        }
    }

    /**
     * Send the verification link to the user's current email address.
     */
    public function sendVerification(SendVerificationEmail $send): void
    {
        $this->error = null;

        $result = $send(Auth::user());

        if ($result === SendVerificationEmail::ALREADY_VERIFIED) {
            $this->show = false;

            return;
        }

        if ($result === SendVerificationEmail::THROTTLED) {
            $this->error = 'Please wait a minute before requesting another link.';

            return;
        }

        $this->sent = true;
    }

    public function dismiss(): void
    {
        $this->show = false;
    }
}; ?>

<div>
    <flux:modal wire:model.self="show" class="w-full max-w-md dark:bg-white! dark:ring-black/5!">
        <div class="flex flex-col gap-6">
            @if ($sent)
                <div class="flex flex-col gap-2">
                    <h2 class="text-lg font-bold text-black">Check your inbox</h2>
                    <p class="text-sm text-black/70">
                        We sent a verification link to
                        <span class="font-bold text-black">{{ auth()->user()->email }}</span>.
                        Open it to verify your account. If you don't see it, check your spam folder.
                    </p>
                </div>

                <div class="flex justify-end">
                    <button type="button" wire:click="dismiss"
                            class="cursor-pointer rounded-[5px] bg-[#0d5750] px-6 py-2 text-sm text-white transition hover:bg-[#0b4742]">
                        Close
                    </button>
                </div>
            @else
                <div class="flex flex-col gap-2">
                    <h2 class="text-lg font-bold text-black">
                        {{ $reason === 'signup' ? 'Verify your account' : 'Your email is not verified' }}
                    </h2>
                    <p class="text-sm text-black/70">
                        We will send a verification link to
                        <span class="font-bold text-black">{{ auth()->user()->email }}</span>
                        to verify your account. Verify now?
                    </p>

                    @if ($error)
                        <p class="text-[11px] text-red-600">{{ $error }}</p>
                    @endif
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" wire:click="dismiss"
                            class="cursor-pointer rounded-[5px] border border-[#a3a3a3] px-6 py-2 text-sm text-black transition hover:bg-black/5">
                        Later
                    </button>
                    <button type="button" wire:click="sendVerification" wire:loading.attr="disabled" wire:target="sendVerification"
                            class="cursor-pointer rounded-[5px] bg-[#0d5750] px-6 py-2 text-sm text-white transition hover:bg-[#0b4742] disabled:cursor-not-allowed disabled:opacity-60">
                        <span wire:loading.remove wire:target="sendVerification">Verify Now</span>
                        <span wire:loading wire:target="sendVerification">Sending…</span>
                    </button>
                </div>
            @endif
        </div>
    </flux:modal>
</div>
