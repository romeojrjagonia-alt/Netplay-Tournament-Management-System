<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth.signin')] class extends Component {
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        Password::sendResetLink($this->only('email'));

        session()->flash('status', __('A reset link will be sent if the account exists.'));
    }
}; ?>

<div class="relative w-full max-w-[994px] overflow-hidden rounded-[20px] bg-white shadow-2xl">
    <a href="{{ route('home') }}" wire:navigate
       class="absolute top-5 right-5 z-10 text-white transition hover:text-white/80 lg:text-teal-800 lg:hover:text-teal-600">
        <flux:icon.x-mark class="size-7" />
    </a>

    <div class="flex flex-col lg:flex-row">
        {{-- Welcome panel --}}
        <div class="relative shrink-0 overflow-hidden lg:w-[464px]"
             style="background-image: linear-gradient(202deg, #8CC7C1 3.75%, #77B9B3 12.29%, #61ABA4 19.34%, #3E948D 30.06%, #11776E 41.94%, #0D5750 53.65%, #083531 78.04%, #051C1A 101.46%);">
            <img src="{{ asset('images/auth/grid-pattern.svg') }}" alt=""
                 class="absolute inset-0 h-full w-full object-cover">

            <div class="relative flex h-full min-h-[150px] flex-col justify-end p-6 pt-16 lg:min-h-[608px] lg:p-10 lg:pb-14">
                <h2 class="text-3xl leading-[1.15] text-white lg:text-[37px]">
                    Forgot your<br><span class="font-bold">password?</span>
                </h2>
            </div>
        </div>

        {{-- Form panel --}}
        <div class="flex w-full flex-col items-center justify-center gap-8 px-8 py-14 lg:w-[530px] md:px-14">
            <div class="flex flex-col items-center gap-2 text-center">
                <h1 class="text-2xl font-bold text-black">Forgot Password</h1>
                <p class="max-w-[313px] text-xs text-black/60">Enter your email and we'll send you a link to reset your password.</p>
            </div>

            <x-auth-session-status class="text-center !text-xs !text-[#138177]" :status="session('status')" />

            <form wire:submit="sendPasswordResetLink" class="flex w-full max-w-[313px] flex-col items-center gap-4">
                <x-auth.field label="Email" model="email" type="email" placeholder="e.g. juancruz@gmail.com" class="w-full" />

                <button type="submit"
                        class="flex h-9 w-full cursor-pointer items-center justify-center rounded-[69px] bg-[#096] text-sm font-bold text-white transition hover:bg-[#007a52]">
                    Email Password Reset Link
                </button>
            </form>

            <div class="flex items-center gap-1 text-xs text-[#138177]">
                Remembered it?
                <a href="{{ route('login') }}" wire:navigate class="font-bold">Sign In</a>
            </div>
        </div>
    </div>
</div>
