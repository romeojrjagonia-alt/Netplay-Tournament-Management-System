<?php

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth.signin')] class extends Component {
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->ensureIsNotRateLimited();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password])) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        Session::regenerate();

        $this->redirectIntended(
            default: route(Auth::user()->homeRouteName(), absolute: false),
            navigate: true,
        );
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
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
                    Welcome Back to<br><span class="font-bold">NetPlay!</span>
                </h2>
            </div>
        </div>

        {{-- Form panel --}}
        <div class="flex w-full flex-col items-center gap-8 px-8 py-14 lg:w-[530px] md:px-14">
            <h1 class="text-2xl font-bold text-black">Sign In</h1>

            <x-auth-session-status class="text-center" :status="session('status')" />

            <form wire:submit="login" class="flex w-full max-w-[313px] flex-col items-center gap-1">
                <div class="flex w-full flex-col gap-[9px]">
                    <x-auth.field label="Email" model="email" type="email" placeholder="e.g. juancruz@gmail.com" />

                    <div x-data="{ show: false }" class="flex flex-col gap-0.5">
                        <label for="password" class="text-xs text-black">Password</label>
                        <div class="flex items-center gap-2 rounded-[5px] bg-[#11776e]/15 px-2.5 py-2.5">
                            <input id="password" wire:model="password" :type="show ? 'text' : 'password'"
                                   placeholder="Enter Password" required autocomplete="current-password"
                                   class="w-full bg-transparent text-xs text-black placeholder:text-black/50 focus:outline-none">
                            <button type="button" @click="show = !show" class="shrink-0 cursor-pointer text-black/50">
                                <flux:icon.eye class="size-4" x-show="!show" />
                                <flux:icon.eye-slash class="size-4" x-show="show" x-cloak />
                            </button>
                        </div>
                        @error('password')
                            <p class="text-[11px] text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex w-full justify-end py-2.5">
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" wire:navigate
                           class="text-xs text-[#2f8880]">
                            Forgot Password?
                        </a>
                    @endif
                </div>

                <button type="submit"
                        class="flex h-9 w-full cursor-pointer items-center justify-center rounded-[69px] bg-[#096] text-sm font-bold text-white transition hover:bg-[#007a52]">
                    Sign In
                </button>
            </form>

            <div class="flex items-center gap-1 text-xs text-[#138177]">
                Don't have an account?
                <a href="{{ route('register') }}" wire:navigate class="font-bold">Sign Up</a>
            </div>
        </div>
    </div>
</div>
