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
       class="absolute top-5 right-5 z-10 text-teal-800 transition hover:text-teal-600">
        <flux:icon.x-mark class="size-7" />
    </a>

    <div class="flex flex-col md:flex-row">
        {{-- Welcome panel --}}
        <div class="relative hidden shrink-0 overflow-hidden md:block md:w-[464px]"
             style="background-image: linear-gradient(202deg, #8CC7C1 3.75%, #77B9B3 12.29%, #61ABA4 19.34%, #3E948D 30.06%, #11776E 41.94%, #0D5750 53.65%, #083531 78.04%, #051C1A 101.46%);">
            <img src="{{ asset('images/auth/grid-pattern.svg') }}" alt=""
                 class="absolute inset-0 h-full w-full object-cover">

            <div class="relative flex h-full min-h-[608px] flex-col justify-end p-10 pb-14">
                <h2 class="text-[37px] leading-[1.15] text-white">
                    Welcome Back to<br><span class="font-bold">NetPlay!</span>
                </h2>
            </div>
        </div>

        {{-- Form panel --}}
        <div class="flex w-full flex-col items-center gap-8 px-8 py-14 md:w-[530px] md:px-14">
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

            <div class="flex w-full items-center gap-4">
                <div class="h-px flex-1 bg-black/20"></div>
                <span class="shrink-0 text-xs text-[#138177]">or continue with</span>
                <div class="h-px flex-1 bg-black/20"></div>
            </div>

            <button type="button"
                    class="flex h-9 w-full max-w-[195px] cursor-pointer items-center justify-center gap-2.5 rounded-[15px] border border-[#138177] bg-white/20 px-6 text-sm text-[#11776e] transition hover:bg-[#11776e]/10">
                <svg class="size-[19px]" viewBox="0 0 48 48">
                    <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.9 29.3 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.6 6.1 29.6 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.7-.4-3.5z"/>
                    <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.6 15.9 18.9 13 24 13c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.6 6.1 29.6 4 24 4c-7.6 0-14.1 4.3-17.4 10.7z"/>
                    <path fill="#4CAF50" d="M24 44c5.5 0 10.4-1.9 14.3-5.1l-6.6-5.6C29.6 35 26.9 36 24 36c-5.3 0-9.7-3.1-11.3-7.4l-6.6 5.1C9.8 39.6 16.4 44 24 44z"/>
                    <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.3-2.3 4.2-4.2 5.5l6.6 5.6C41.4 36 44 30.5 44 24c0-1.3-.1-2.7-.4-3.5z"/>
                </svg>
                Sign In with Google
            </button>

            <div class="flex items-center gap-1 text-xs text-[#138177]">
                Don't have an account?
                <a href="{{ route('register') }}" wire:navigate class="font-bold">Sign Up</a>
            </div>
        </div>
    </div>
</div>
