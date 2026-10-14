<?php

use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Mail\UmpireSignupCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.auth.signup')] class extends Component {
    use WithFileUploads;

    public int $step = 1;

    public ?string $userType = null;

    /**
     * True when the visitor arrived from Google and still needs a profile.
     * Name and email then come from the session, never from client state.
     */
    public bool $viaGoogle = false;

    public string $firstName = '';
    public string $lastName = '';
    public string $middleInitial = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public string $birthDate = '';

    public string $phoneNumber = '';
    public string $gender = '';
    public string $purok = '';
    public string $city = '';
    public string $province = '';
    public string $club = '';
    public $profilePhoto = null;

    /**
     * Umpire sign-up is only completed after the visitor proves they own the
     * email address. Until then nothing is stored in the database; the
     * code's hash lives in the cache under `$verificationToken`.
     */
    #[Locked]
    public bool $awaitingCode = false;

    #[Locked]
    public string $verificationToken = '';

    /** Unix time after which a new code may be requested. */
    #[Locked]
    public int $resendAvailableAt = 0;

    public string $verificationCode = '';

    public const CODE_TTL_MINUTES = 10;

    public const RESEND_COOLDOWN_SECONDS = 60;

    public const MAX_CODE_ATTEMPTS = 5;

    /**
     * Pre-fill from a pending Google sign-up, if there is one.
     */
    public function mount(): void
    {
        $google = Session::get('google_signup');

        if ($google !== null) {
            $this->viaGoogle = true;
            $this->firstName = $google['first_name'];
            $this->lastName = $google['last_name'];
            $this->email = $google['email'];
        }
    }

    /**
     * Mark the account type the visitor is registering as.
     */
    public function selectUserType(string $type): void
    {
        $this->userType = $type;
    }

    /**
     * Validate the current step's fields and advance the wizard.
     */
    public function nextStep(): void
    {
        if ($this->step === 1) {
            $this->validate([
                'userType' => ['required', 'in:player,umpire'],
            ], [
                'userType.required' => "Please choose how you'll use NetPlay.",
            ]);

            // Google already supplied the account details, so skip step 2.
            $this->step = $this->viaGoogle ? 3 : 2;

            return;
        }

        if ($this->step === 2) {
            $this->validate([
                'firstName' => ['required', 'string', 'max:255'],
                'lastName' => ['required', 'string', 'max:255'],
                'middleInitial' => ['nullable', 'string', 'max:5'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ]);
        }

        $this->step = min($this->step + 1, 3);
    }

    /**
     * Return to the previous step without validating.
     */
    public function previousStep(): void
    {
        $this->step = $this->viaGoogle && $this->step === 3 ? 1 : max($this->step - 1, 1);
    }

    /**
     * Validate the full wizard and create the user + player records.
     */
    public function register(): void
    {
        $google = $this->viaGoogle ? Session::get('google_signup') : null;

        if ($this->viaGoogle && $google === null) {
            $this->redirect(route('login'), navigate: true);

            return;
        }

        if ($google !== null) {
            $this->firstName = $google['first_name'];
            $this->lastName = $google['last_name'];
            $this->email = $google['email'];
        }

        $this->validate([
            'userType' => ['required', 'in:player,umpire'],
            'firstName' => ['required', 'string', 'max:255'],
            'lastName' => [$this->viaGoogle ? 'nullable' : 'required', 'string', 'max:255'],
            'middleInitial' => ['nullable', 'string', 'max:5'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => $this->viaGoogle ? ['nullable'] : ['required', 'string', 'min:8', 'confirmed'],
            'birthDate' => ['required', 'date', 'before:today'],
            'phoneNumber' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:male,female'],
            'purok' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'province' => ['required', 'string', 'max:255'],
            'club' => ['nullable', 'string', 'max:255'],
            'profilePhoto' => ['nullable', 'image', 'max:2048'],
        ]);

        $fullName = collect([
            $this->firstName,
            $this->middleInitial !== '' ? $this->middleInitial.'.' : null,
            $this->lastName,
        ])->filter()->implode(' ');

        // Umpires get a code by email first; no account exists until it is
        // entered. (Google already verified its address.)
        if ($this->userType === 'umpire' && $google === null) {
            $this->sendVerificationCode($fullName);

            return;
        }

        $this->createAccount($fullName, $google, emailVerified: false);
    }

    /**
     * Check the emailed code and, only then, create the umpire account.
     */
    public function verifyCode(): void
    {
        abort_unless($this->awaitingCode, 403);

        $this->validate(['verificationCode' => ['required', 'digits:6']], [
            'verificationCode.digits' => 'Enter the 6-digit code from your email.',
        ]);

        $entry = Cache::get($this->cacheKey());

        if (! $entry || $entry['email'] !== Str::lower($this->email)) {
            $this->abandonSignup('Your verification expired, so no account was created. Please sign up again.');

            return;
        }

        if (now()->timestamp > $entry['expires_at']) {
            $this->addError('verificationCode', 'That code has expired. Request a new one.');

            return;
        }

        if (! Hash::check($this->verificationCode, $entry['code'])) {
            $entry['attempts']++;

            if ($entry['attempts'] >= self::MAX_CODE_ATTEMPTS) {
                $this->abandonSignup('Too many incorrect codes, so no account was created. Please sign up again.');

                return;
            }

            Cache::put($this->cacheKey(), $entry, now()->addMinutes(30));

            $this->addError('verificationCode', 'That code is incorrect. '.(self::MAX_CODE_ATTEMPTS - $entry['attempts']).' attempts left.');

            return;
        }

        Cache::forget($this->cacheKey());
        $this->awaitingCode = false;

        $this->createAccount($this->fullName(), null, emailVerified: true);
    }

    /**
     * Email a fresh code, no sooner than 60 seconds after the last one.
     */
    public function resendCode(): void
    {
        abort_unless($this->awaitingCode, 403);

        $entry = Cache::get($this->cacheKey());

        if (! $entry) {
            $this->abandonSignup('Your verification expired, so no account was created. Please sign up again.');

            return;
        }

        $wait = $entry['resend_at'] - now()->timestamp;

        if ($wait > 0) {
            $this->addError('verificationCode', "Please wait {$wait} seconds before requesting another code.");

            return;
        }

        $this->sendVerificationCode($this->fullName());
    }

    /**
     * The visitor backed out: discard the pending sign-up, nothing was saved.
     */
    public function cancelVerification(): void
    {
        $this->abandonSignup('Verification cancelled. No account was created — sign up again to continue.');
    }

    private function fullName(): string
    {
        return collect([
            $this->firstName,
            $this->middleInitial !== '' ? $this->middleInitial.'.' : null,
            $this->lastName,
        ])->filter()->implode(' ');
    }

    private function cacheKey(): string
    {
        return 'umpire-signup:'.$this->verificationToken;
    }

    private function sendVerificationCode(string $fullName): void
    {
        $throttleKey = 'umpire-code:'.Str::lower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $this->dispatch('system-message', title: 'Too many codes requested', message: 'Please try again in a little while.', variant: 'danger');

            return;
        }

        RateLimiter::hit($throttleKey, 3600);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        if ($this->verificationToken === '') {
            $this->verificationToken = Str::random(40);
        }

        Cache::put($this->cacheKey(), [
            'email' => Str::lower($this->email),
            'code' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES)->timestamp,
            'resend_at' => now()->addSeconds(self::RESEND_COOLDOWN_SECONDS)->timestamp,
        ], now()->addMinutes(30));

        Mail::to($this->email)->send(new UmpireSignupCode($code, $fullName, self::CODE_TTL_MINUTES));

        $this->resendAvailableAt = now()->addSeconds(self::RESEND_COOLDOWN_SECONDS)->timestamp;
        $this->verificationCode = '';
        $this->awaitingCode = true;
        $this->resetErrorBag('verificationCode');
    }

    /**
     * Drop the pending sign-up and return to a blank form.
     */
    private function abandonSignup(string $message): void
    {
        if ($this->verificationToken !== '') {
            Cache::forget($this->cacheKey());
        }

        $this->reset();
        $this->resetErrorBag();
        $this->dispatch('system-message', title: 'Sign-up not completed', message: $message, variant: 'danger');
    }

    /**
     * @param  array<string, mixed>|null  $google
     */
    private function createAccount(string $fullName, ?array $google, bool $emailVerified): void
    {
        $photoPath = $this->profilePhoto?->store('player-photos', 'public');

        $user = DB::transaction(function () use ($fullName, $photoPath, $google, $emailVerified) {
            $user = User::create([
                'name' => $fullName,
                'email' => $this->email,
                'role' => $this->userType,
                ...($google !== null
                    ? ['google_id' => $google['id'], 'email_verified_at' => now()]
                    : ['password' => $this->password, 'email_verified_at' => $emailVerified ? now() : null]),
            ]);

            Player::create([
                'user_id' => $user->id,
                'name' => $fullName,
                'birth_date' => $this->birthDate,
                'phone_number' => $this->phoneNumber,
                'gender' => $this->gender,
                'address' => "{$this->purok}, {$this->city}, {$this->province}",
                'club' => $this->club !== '' ? $this->club : null,
                'photo_path' => $photoPath,
                'email' => $user->email,
            ]);

            return $user;
        });

        Auth::login($user);
        Session::regenerate();
        Session::forget('google_signup');

        if ($google === null && ! $emailVerified) {
            Session::put('verification_prompt', 'signup');
        }

        $this->redirect(route($user->homeRouteName(), absolute: false), navigate: true);
    }
}; ?>

<div class="relative w-full max-w-[994px] overflow-hidden rounded-[20px] bg-white shadow-2xl">
    <a href="{{ route('home') }}" wire:navigate
       class="absolute top-5 right-5 z-10 text-white transition hover:text-white/80 lg:text-teal-800 lg:hover:text-teal-600">
        <flux:icon.x-mark class="size-7" />
    </a>

    <div class="flex flex-col lg:flex-row">
        {{-- Status panel --}}
        <div class="relative shrink-0 overflow-hidden lg:w-[464px]"
             style="background-image: linear-gradient(202deg, #8CC7C1 3.75%, #77B9B3 12.29%, #61ABA4 19.34%, #3E948D 30.06%, #11776E 41.94%, #0D5750 53.65%, #083531 78.04%, #051C1A 101.46%);">
            <img src="{{ asset('images/auth/grid-pattern.svg') }}" alt=""
                 class="absolute inset-0 h-full w-full object-cover">

            <div class="relative flex h-full min-h-[150px] flex-col justify-end gap-8 p-6 pt-16 lg:min-h-[608px] lg:p-10 lg:pb-14">
                <div class="flex items-end gap-4">
                    <h2 class="text-3xl leading-none text-white lg:text-[37px]">Get Started</h2>
                    <p class="max-w-[160px] text-[15px] leading-5 text-white">Complete this step to register your account.</p>
                </div>

                <div class="hidden gap-[15px] lg:flex">
                    @foreach ([
                        1 => ['label' => 'Select User Type', 'badge' => '#0b4742'],
                        2 => ['label' => 'Sign Up your account', 'badge' => '#083531'],
                        3 => ['label' => 'Set up your Profile', 'badge' => '#083531'],
                    ] as $index => $item)
                        <div class="w-[106px] rounded-[10px] bg-white p-3 {{ $step >= $index ? '' : 'opacity-50' }}">
                            <span class="flex h-[27px] w-[27px] items-center justify-center rounded-full text-sm text-white"
                                  style="background-color: {{ $item['badge'] }}">{{ $index }}</span>
                            <p class="mt-2 text-sm text-black">{{ $item['label'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Form panel --}}
        <div class="flex w-full flex-col items-center gap-10 px-8 py-10 lg:w-[530px] md:px-14">
            @if ($step === 1)
                <div class="flex flex-col items-center gap-8 text-center">
                    <div class="flex flex-col items-center gap-2">
                        <h1 class="text-2xl font-bold text-black">Create Your Account</h1>
                        <p class="text-[15px] text-[#3e948d]">Choose how you'll use NETPLAY!</p>
                    </div>

                    <div class="flex flex-col items-center gap-2">
                        <div class="flex w-full items-center justify-center gap-3 sm:gap-5">
                            <button type="button" wire:click="selectUserType('player')"
                                    @class([
                                        'min-w-0 max-w-[153px] flex-1 cursor-pointer rounded-[20px] border px-3 py-[35px] text-center transition sm:px-[30px]',
                                        'border-emerald-700 bg-emerald-700/25 ring-1 ring-emerald-700' => $userType === 'player',
                                        'border-[#81bfba] bg-[#82c0bb]/25 hover:border-emerald-700' => $userType !== 'player',
                                    ])>
                                <div class="flex h-[70px] items-center justify-center">
                                    <img src="{{ asset('images/auth/player-icon.svg') }}" alt="" class="h-[59px] w-auto">
                                </div>
                                <p class="text-xs font-bold text-black">Player</p>
                                <p class="mt-1 text-[9px] text-black">Register for tournaments and track your matches</p>
                            </button>

                            <button type="button" wire:click="selectUserType('umpire')"
                                    @class([
                                        'w-[153px] cursor-pointer rounded-[20px] border px-[30px] py-[35px] text-center transition',
                                        'border-emerald-700 bg-emerald-700/25 ring-1 ring-emerald-700' => $userType === 'umpire',
                                        'border-[#81bfba] bg-[#82c0bb]/25 hover:border-emerald-700' => $userType !== 'umpire',
                                    ])>
                                <div class="relative mx-auto h-[71px] w-[93px]">
                                    <div class="absolute inset-[32.9%_0_0_36.59%] flex items-center justify-center">
                                        <img src="{{ asset('images/auth/umpire-icon-1.svg') }}" alt=""
                                             class="h-[31px] w-[51px] rotate-[159.1deg]">
                                    </div>
                                    <div class="absolute inset-[0_38.01%_21.93%_0] flex items-center justify-center">
                                        <img src="{{ asset('images/auth/umpire-icon-2.svg') }}" alt=""
                                             class="h-[42px] w-[47px] rotate-[19.31deg]">
                                    </div>
                                </div>
                                <p class="text-xs font-bold text-black">Umpire</p>
                                <p class="mt-1 text-[9px] text-black">Officiate matches and manage live scoring</p>
                            </button>

                        </div>

                        @error('userType')
                            <p class="text-[11px] text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    @if ($viaGoogle)
                        <p class="text-xs text-[#3e948d]">Signing up with Google as <span class="font-bold">{{ $email }}</span></p>
                    @else
                        <div class="flex w-full flex-col items-center gap-3">
                            <div class="flex w-full items-center gap-3 text-xs text-black/50">
                                <span class="h-px flex-1 bg-black/15"></span>or<span class="h-px flex-1 bg-black/15"></span>
                            </div>

                            <x-auth.google-button label="Sign up with Google" class="max-w-[313px]" />
                        </div>
                    @endif
                </div>
            @elseif ($step === 2)
                <div class="flex w-full flex-col items-center gap-8">
                    <div class="flex flex-col items-center gap-2 text-center">
                        <h1 class="text-2xl font-bold text-black">Create Your Account</h1>
                        <p class="text-[15px] text-[#3e948d]">Enter your credentials to create your account</p>
                    </div>

                    <div class="flex w-full flex-col items-center gap-5">
                        <div class="flex w-full gap-2">
                            <x-auth.field label="First Name" model="firstName" placeholder="e.g. Juan" class="flex-[2]" />
                            <x-auth.field label="Last Name" model="lastName" placeholder="e.g. Cruz" class="flex-[2]" />
                            <x-auth.field label="M.I" model="middleInitial" placeholder="e.g. D" class="w-14 shrink-0" />
                        </div>

                        <x-auth.field label="Email" model="email" type="email" placeholder="e.g. juancruz@gmail.com" class="w-full" />

                        <div class="flex w-full gap-4">
                            <div x-data="{ show: false }" class="flex flex-1 flex-col gap-0.5">
                                <label for="password" class="text-xs text-black">Password</label>
                                <div class="flex items-center gap-2 rounded-[5px] bg-[#11776e]/15 px-2.5 py-2.5">
                                    <input id="password" wire:model="password" :type="show ? 'text' : 'password'"
                                           placeholder="Enter your Password" required
                                           class="w-full bg-transparent text-xs text-black placeholder:text-black/50 focus:outline-none">
                                    <button type="button" @click="show = !show" class="shrink-0 text-black/50">
                                        <flux:icon.eye class="size-4" x-show="!show" />
                                        <flux:icon.eye-slash class="size-4" x-show="show" x-cloak />
                                    </button>
                                </div>
                                @error('password')
                                    <p class="text-[11px] text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div x-data="{ show: false }" class="flex flex-1 flex-col gap-0.5">
                                <label for="password_confirmation" class="text-xs text-black">Confirm Password</label>
                                <div class="flex items-center gap-2 rounded-[5px] bg-[#11776e]/15 px-2.5 py-2.5">
                                    <input id="password_confirmation" wire:model="password_confirmation" :type="show ? 'text' : 'password'"
                                           placeholder="Confirm your Password" required
                                           class="w-full bg-transparent text-xs text-black placeholder:text-black/50 focus:outline-none">
                                    <button type="button" @click="show = !show" class="shrink-0 text-black/50">
                                        <flux:icon.eye class="size-4" x-show="!show" />
                                        <flux:icon.eye-slash class="size-4" x-show="show" x-cloak />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <p class="w-full text-right text-xs text-[#3e948d] italic">Must be at least 8 characters</p>
                    </div>
                </div>
            @else
                <div class="flex w-full flex-col items-center gap-8">
                    <div class="flex flex-col items-center gap-2 text-center">
                        <h1 class="text-2xl font-bold text-black">Create Your Account</h1>
                        <p class="text-[15px] text-[#3e948d]">Finalize your account through setting your profile</p>
                    </div>

                    <div class="flex w-full flex-col items-center gap-3.5">
                        <div class="flex w-full gap-3">
                            <x-auth.field label="Birth Date" model="birthDate" type="date" class="flex-[2]" />

                            <x-auth.field label="Phone Number" model="phoneNumber" type="tel" placeholder="e.g. 09123456789" class="flex-[2]" />

                            <div class="flex w-28 shrink-0 flex-col gap-0.5">
                                <label for="gender" class="text-xs text-black">Gender</label>
                                <select id="gender" wire:model="gender"
                                        class="w-full rounded-[5px] bg-[#11776e]/15 px-2.5 py-2.5 text-xs text-black focus:outline-none">
                                    <option value="">Select</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                        </div>
                        @error('birthDate')
                            <p class="w-full text-[11px] text-red-600">{{ $message }}</p>
                        @enderror
                        @error('gender')
                            <p class="w-full text-[11px] text-red-600">{{ $message }}</p>
                        @enderror
                        @error('phoneNumber')
                            <p class="w-full text-[11px] text-red-600">{{ $message }}</p>
                        @enderror

                        <div class="flex w-full gap-2">
                            <x-auth.field label="Purok/ Street" model="purok" placeholder="e.g. Purok 5" class="flex-1" />
                            <x-auth.field label="City" model="city" placeholder="e.g. Ozamiz City" class="flex-1" />
                            <x-auth.field label="Province" model="province" placeholder="e.g. Misamis Occidental" class="flex-[1.5]" />
                        </div>

                        <x-auth.field label="Club" model="club" placeholder="e.g. OBC Badminton Club" class="w-full" />

                        <div x-data="{ fileName: '' }" class="flex w-full flex-col gap-0.5 px-8">
                            <label for="profilePhoto" class="text-xs text-black">Upload Profile Photo</label>
                            <label for="profilePhoto"
                                   class="flex cursor-pointer items-center justify-center rounded-[5px] border border-dashed border-[#138177] bg-[#11776e]/15 px-2.5 py-8 text-center text-xs text-black/50">
                                <span x-text="fileName || 'Upload Image'"></span>
                            </label>
                            <input id="profilePhoto" type="file" wire:model="profilePhoto" accept="image/*" class="hidden"
                                   x-on:change="fileName = $event.target.files[0]?.name ?? ''">
                            @error('profilePhoto')
                                <p class="text-[11px] text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            @endif

            <div class="mt-auto flex w-full items-center justify-center gap-3.5">
                @if ($step === 1)
                    <a href="{{ route('home') }}" wire:navigate
                       class="flex h-9 items-center justify-center rounded-[69px] border border-[#138177]/50 bg-white px-8 sm:px-14 text-sm font-bold text-[#138177] transition hover:bg-teal-50">
                        Cancel
                    </a>
                @else
                    <button type="button" wire:click="previousStep"
                            class="flex h-9 cursor-pointer items-center justify-center rounded-[69px] border border-[#138177]/50 bg-white px-8 sm:px-14 text-sm font-bold text-[#138177] transition hover:bg-teal-50">
                        Back
                    </button>
                @endif

                @if ($step < 3)
                    <button type="button" wire:click="nextStep"
                            class="flex h-9 cursor-pointer items-center justify-center rounded-[69px] bg-[#0b4742] px-8 sm:px-16 text-sm font-bold text-white transition hover:bg-[#0d5750]">
                        Next
                    </button>
                @else
                    <button type="button" wire:click="register" wire:loading.attr="disabled" wire:target="register"
                            class="flex h-9 cursor-pointer items-center justify-center rounded-[69px] bg-[#0b4742] px-8 sm:px-16 text-sm font-bold text-white transition hover:bg-[#0d5750] disabled:cursor-not-allowed disabled:opacity-60">
                        <span wire:loading.remove wire:target="register">Confirm</span>
                        <span wire:loading wire:target="register">Creating account…</span>
                    </button>
                @endif
            </div>

            @if ($step === 1)
                <div class="flex items-center justify-center gap-1 text-xs text-[#3e948d]">
                    Already have an account?
                    <a href="{{ route('login') }}" wire:navigate class="font-bold">Sign In</a>
                </div>
            @endif
        </div>
    </div>

    @if ($awaitingCode)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="verify-title">
            <div class="absolute inset-0 bg-black/60"></div>

            <form wire:submit="verifyCode"
                  class="relative flex w-full max-w-sm flex-col gap-4 rounded-[20px] bg-white p-8 shadow-2xl">
                <div class="flex flex-col gap-1">
                    <h2 id="verify-title" class="text-xl font-bold text-black">Verify your email</h2>
                    <p class="text-xs text-black/70">
                        We sent a 6-digit code to <span class="font-bold">{{ $email }}</span>.
                        Enter it to finish creating your umpire account. It expires in {{ $this::CODE_TTL_MINUTES }} minutes.
                    </p>
                </div>

                <div class="flex flex-col gap-1">
                    <label for="verification-code" class="text-xs text-black">Verification code</label>
                    <input id="verification-code" wire:model="verificationCode" type="text" inputmode="numeric"
                           autocomplete="one-time-code" maxlength="6" placeholder="000000" autofocus
                           class="rounded-[5px] bg-[#11776e]/15 px-3 py-2.5 text-center text-lg tracking-[0.5em] text-black placeholder:text-black/30 focus:outline-none">
                    @error('verificationCode')
                        <p class="text-[11px] text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                        class="flex h-9 w-full cursor-pointer items-center justify-center rounded-[69px] bg-[#096] text-sm font-bold text-white transition hover:bg-[#007a52]">
                    Verify and create account
                </button>

                <div class="flex items-center justify-between gap-3 text-xs"
                     wire:key="resend-{{ $resendAvailableAt }}"
                     x-data="{ remaining: {{ max(0, $resendAvailableAt - now()->timestamp) }} }"
                     x-init="setInterval(() => { if (remaining > 0) remaining-- }, 1000)">
                    <button type="button" wire:click="cancelVerification" class="cursor-pointer text-black/60 underline">
                        Cancel
                    </button>

                    <button type="button" wire:click="resendCode" x-bind:disabled="remaining > 0"
                            class="cursor-pointer font-bold text-[#138177] disabled:cursor-not-allowed disabled:opacity-50">
                        <span x-show="remaining <= 0">Resend code</span>
                        <span x-show="remaining > 0" x-cloak x-text="'Resend code in ' + remaining + 's'"></span>
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>