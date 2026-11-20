<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth.signin')] class extends Component {
    #[Locked]
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Mount the component.
     */
    public function mount(string $token): void
    {
        $this->token = $token;

        $this->email = request()->string('email');
    }

    /**
     * Reset the password for the given user.
     */
    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            $this->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        if ($status != Password::PasswordReset) {
            $this->addError('email', __($status));

            return;
        }

        Session::flash('status', __($status));

        $this->redirectRoute('login', navigate: true);
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
                    Choose a new<br><span class="font-bold">password</span>
                </h2>
            </div>
        </div>

        {{-- Form panel --}}
        <div class="flex w-full flex-col items-center justify-center gap-8 px-8 py-14 lg:w-[530px] md:px-14">
            <div class="flex flex-col items-center gap-2 text-center">
                <h1 class="text-2xl font-bold text-black">Reset Password</h1>
                <p class="max-w-[313px] text-xs text-black/60">Please enter your new password below.</p>
            </div>

            <x-auth-session-status class="text-center !text-xs !text-[#138177]" :status="session('status')" />

            <form wire:submit="resetPassword" class="flex w-full max-w-[313px] flex-col items-center gap-4">
                <x-auth.field label="Email" model="email" type="email" placeholder="e.g. juancruz@gmail.com" class="w-full" />

                <div x-data="{ show: false }" class="flex w-full flex-col gap-0.5">
                    <label for="password" class="text-xs text-black">New Password</label>
                    <div class="flex items-center gap-2 rounded-[5px] bg-[#11776e]/15 px-2.5 py-2.5">
                        <input id="password" wire:model="password" :type="show ? 'text' : 'password'"
                               placeholder="Enter new password" required autocomplete="new-password"
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

                <div x-data="{ show: false }" class="flex w-full flex-col gap-0.5">
                    <label for="password_confirmation" class="text-xs text-black">Confirm Password</label>
                    <div class="flex items-center gap-2 rounded-[5px] bg-[#11776e]/15 px-2.5 py-2.5">
                        <input id="password_confirmation" wire:model="password_confirmation" :type="show ? 'text' : 'password'"
                               placeholder="Re-enter new password" required autocomplete="new-password"
                               class="w-full bg-transparent text-xs text-black placeholder:text-black/50 focus:outline-none">
                        <button type="button" @click="show = !show" class="shrink-0 cursor-pointer text-black/50">
                            <flux:icon.eye class="size-4" x-show="!show" />
                            <flux:icon.eye-slash class="size-4" x-show="show" x-cloak />
                        </button>
                    </div>
                    @error('password_confirmation')
                        <p class="text-[11px] text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                        class="flex h-9 w-full cursor-pointer items-center justify-center rounded-[69px] bg-[#096] text-sm font-bold text-white transition hover:bg-[#007a52]">
                    Reset Password
                </button>
            </form>

            <div class="flex items-center gap-1 text-xs text-[#138177]">
                <a href="{{ route('login') }}" wire:navigate class="font-bold">Back to Sign In</a>
            </div>
        </div>
    </div>
</div>
