<?php

use App\Livewire\Actions\SendVerificationEmail;
use App\Mail\PasswordChanged;
use App\Models\Player;
use App\Rules\AccountPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public bool $editing = false;

    public $photo = null;

    public string $name = '';

    public string $email = '';

    public string $address = '';

    public string $phoneNumber = '';

    public ?string $verificationStatus = null;

    public bool $securityView = false;

    /** 1 = current password, 2 = new password, 3 = confirm it. */
    public int $passwordStep = 1;

    /** Set only on the server once the current password was checked. */
    #[Locked]
    public bool $currentPasswordVerified = false;

    public string $currentPassword = '';

    public string $newPassword = '';

    public string $newPasswordConfirmation = '';

    /**
     * Accounts created with Google have no password yet, so there is nothing
     * to check in step 1 and they go straight to setting one.
     */
    #[Computed]
    public function hasPassword(): bool
    {
        return filled(auth()->user()->password);
    }

    public function openSecurity(): void
    {
        $this->resetPasswordFlow();
        $this->securityView = true;
    }

    public function closeSecurity(): void
    {
        $this->resetPasswordFlow();
        $this->securityView = false;
    }

    /**
     * Step 1: the player proves it is them by entering the current password.
     */
    public function verifyCurrentPassword(): void
    {
        $this->validate(['currentPassword' => ['required', 'string']], ['currentPassword.required' => 'Enter your current password.']);

        $throttleKey = 'player-password:'.auth()->id();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $this->addError('currentPassword', 'Too many attempts. Please wait a minute and try again.');

            return;
        }

        if (! Hash::check($this->currentPassword, (string) auth()->user()->password)) {
            RateLimiter::hit($throttleKey, 60);
            $this->addError('currentPassword', 'That is not your current password.');

            return;
        }

        RateLimiter::clear($throttleKey);

        $this->currentPassword = '';
        $this->currentPasswordVerified = true;
        $this->passwordStep = 2;
    }

    /**
     * Step 2: choose a new password that follows the account password rules.
     */
    public function submitNewPassword(): void
    {
        abort_unless($this->currentPasswordVerified, 403);

        $this->validate(['newPassword' => ['required', 'string', new AccountPassword]]);

        if ($this->hasPassword && Hash::check($this->newPassword, (string) auth()->user()->password)) {
            $this->addError('newPassword', 'Your new password must be different from your current one.');

            return;
        }

        $this->newPasswordConfirmation = '';
        $this->passwordStep = 3;
    }

    public function backToNewPassword(): void
    {
        $this->newPasswordConfirmation = '';
        $this->resetValidation();
        $this->passwordStep = 2;
    }

    /**
     * Step 3: confirm the new password, save it and tell the player by email.
     */
    public function confirmNewPassword(): void
    {
        abort_unless($this->currentPasswordVerified && $this->passwordStep === 3, 403);

        $this->validate(['newPassword' => ['required', 'string', new AccountPassword]]);

        if (! hash_equals($this->newPassword, $this->newPasswordConfirmation)) {
            $this->addError('newPasswordConfirmation', 'The passwords do not match.');

            return;
        }

        $user = auth()->user();
        $user->update(['password' => $this->newPassword]);

        try {
            Mail::to($user->email)->send(new PasswordChanged($user, now()->format('F j, Y \a\t g:i A')));
        } catch (\Throwable $e) {
            // The password is already changed; a mail problem must not undo it.
            report($e);
        }

        $this->closeSecurity();

        $this->dispatch('system-message', title: 'Password changed', message: 'Your password was changed successfully. We sent a confirmation to your email.', variant: 'success');
    }

    private function resetPasswordFlow(): void
    {
        $this->reset('currentPassword', 'newPassword', 'newPasswordConfirmation');
        $this->resetValidation();
        $this->currentPasswordVerified = ! $this->hasPassword;
        $this->passwordStep = $this->hasPassword ? 1 : 2;
    }

    #[Computed]
    public function player(): Player
    {
        auth()->user()->ensurePlayer();

        return auth()->user()->player()->with('statistic')->firstOrFail();
    }

    #[Computed]
    public function winRate(): int
    {
        $played = $this->player->statistic?->matches_played ?? 0;

        if ($played === 0) {
            return 0;
        }

        return (int) round(($this->player->statistic->wins / $played) * 100);
    }

    /**
     * Populate the edit form from the current user/player and switch to edit mode.
     */
    public function startEditing(): void
    {
        $this->name = $this->player->name;
        $this->email = auth()->user()->email;
        $this->address = $this->player->address ?? '';
        $this->phoneNumber = $this->player->phone_number ?? '';

        $this->editing = true;
    }

    public function cancelEditing(): void
    {
        $this->photo = null;
        $this->resetValidation();
        $this->editing = false;
    }

    /**
     * Send a verification link to the player's current email address.
     */
    public function sendVerification(SendVerificationEmail $send): void
    {
        $this->verificationStatus = $send(Auth::user());
    }

    /**
     * Save the editable profile fields (name, address, email, phone number)
     * back to the user and player records.
     */
    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore(Auth::id())],
            'address' => ['required', 'string', 'max:255'],
            'phoneNumber' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ]);

        $user = Auth::user();
        $user->name = $validated['name'];
        $user->email = $validated['email'];

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
            $this->verificationStatus = null;
        }

        $user->save();

        $attributes = [
            'name' => $validated['name'],
            'address' => $validated['address'],
            'phone_number' => $validated['phoneNumber'],
        ];

        if ($this->photo) {
            $previousPhoto = $this->player->photo_path;
            $attributes['photo_path'] = $this->photo->store('player-photos', 'public');

            if ($previousPhoto) {
                Storage::disk('public')->delete($previousPhoto);
            }
        }

        $this->player->update($attributes);
        $this->photo = null;
        unset($this->player);

        $this->editing = false;

        $this->dispatch('profile-updated', name: $validated['name']);
    }
}; ?>

<div>
    <flux:modal name="player-profile" class="w-full max-w-2xl dark:bg-white! dark:ring-black/5!">
        @if ($securityView)
            <div wire:key="profile-security" class="flex flex-col gap-6 text-black">
                <div>
                    <h2 class="text-xl font-bold">Security Details</h2>
                    <p class="text-sm text-black/70">{{ $this->hasPassword ? 'Change your account password.' : 'You signed in with Google, so you have no password yet. Set one to also sign in with your email.' }}</p>
                </div>

                <div class="flex items-center justify-between rounded-t-[5px] bg-[#138177] px-[15px] py-[10px] text-white">
                    <span class="text-[11px] font-bold">
                        {{ ['', 'Current password', 'New password', 'Confirm new password'][$passwordStep] }}
                    </span>
                    <span class="text-[11px] italic">Step {{ $this->hasPassword ? $passwordStep : $passwordStep - 1 }} of {{ $this->hasPassword ? 3 : 2 }}</span>
                </div>

                @if ($passwordStep === 1)
                    <form wire:submit="verifyCurrentPassword" wire:key="password-step-1" class="flex flex-col gap-3">
                        <label class="flex flex-col gap-1 text-xs">
                            Enter your current password
                            <div x-data="{ show: false }" class="flex items-center gap-2 rounded-[5px] bg-[#11776e]/15 px-2.5 py-2.5">
                                <input wire:model="currentPassword" :type="show ? 'text' : 'password'" autocomplete="current-password" autofocus
                                       class="w-full bg-transparent text-xs text-black focus:outline-none">
                                <button type="button" @click="show = !show" class="shrink-0 text-black/50" aria-label="Show or hide password">
                                    <flux:icon.eye class="size-4" x-show="!show" />
                                    <flux:icon.eye-slash class="size-4" x-show="show" x-cloak />
                                </button>
                            </div>
                            @error('currentPassword') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </label>

                        <div class="flex justify-end gap-3">
                            <button type="button" wire:click="closeSecurity" class="cursor-pointer rounded-[5px] border border-[#a3a3a3] px-6 py-2 text-sm">Cancel</button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="verifyCurrentPassword" class="cursor-pointer rounded-[5px] bg-[#0d5750] px-6 py-2 text-sm text-white disabled:opacity-60">Continue</button>
                        </div>
                    </form>
                @elseif ($passwordStep === 2)
                    <form wire:submit="submitNewPassword" wire:key="password-step-2" class="flex flex-col gap-3">
                        <label class="flex flex-col gap-1 text-xs">
                            Enter your new password
                            <div x-data="{ show: false }" class="flex items-center gap-2 rounded-[5px] bg-[#11776e]/15 px-2.5 py-2.5">
                                <input wire:model.live.debounce.250ms="newPassword" :type="show ? 'text' : 'password'" autocomplete="new-password" autofocus
                                       class="w-full bg-transparent text-xs text-black focus:outline-none">
                                <button type="button" @click="show = !show" class="shrink-0 text-black/50" aria-label="Show or hide password">
                                    <flux:icon.eye class="size-4" x-show="!show" />
                                    <flux:icon.eye-slash class="size-4" x-show="show" x-cloak />
                                </button>
                            </div>
                            @error('newPassword') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </label>

                        @php
                            $checks = [
                                'At least 8 characters' => strlen($newPassword) >= 8,
                                'Uppercase and lowercase letters' => preg_match('/[a-z]/', $newPassword) && preg_match('/[A-Z]/', $newPassword),
                                'At least one number' => (bool) preg_match('/[0-9]/', $newPassword),
                                'No spaces or special characters' => $newPassword !== '' && ! preg_match('/[^A-Za-z0-9]/', $newPassword),
                            ];
                        @endphp
                        <ul class="flex flex-col gap-1 text-[11px]">
                            @foreach ($checks as $label => $passed)
                                <li class="{{ $passed ? 'text-[#0d5750]' : 'text-black/60' }}">{{ $passed ? '✓' : '○' }} {{ $label }}</li>
                            @endforeach
                        </ul>

                        <div class="flex justify-end gap-3">
                            <button type="button" wire:click="closeSecurity" class="cursor-pointer rounded-[5px] border border-[#a3a3a3] px-6 py-2 text-sm">Cancel</button>
                            <button type="submit" class="cursor-pointer rounded-[5px] bg-[#0d5750] px-6 py-2 text-sm text-white">Continue</button>
                        </div>
                    </form>
                @else
                    <form wire:submit="confirmNewPassword" wire:key="password-step-3" class="flex flex-col gap-3">
                        <label class="flex flex-col gap-1 text-xs">
                            Enter your new password again to confirm
                            <div x-data="{ show: false }" class="flex items-center gap-2 rounded-[5px] bg-[#11776e]/15 px-2.5 py-2.5">
                                <input wire:model="newPasswordConfirmation" :type="show ? 'text' : 'password'" autocomplete="new-password" autofocus
                                       class="w-full bg-transparent text-xs text-black focus:outline-none">
                                <button type="button" @click="show = !show" class="shrink-0 text-black/50" aria-label="Show or hide password">
                                    <flux:icon.eye class="size-4" x-show="!show" />
                                    <flux:icon.eye-slash class="size-4" x-show="show" x-cloak />
                                </button>
                            </div>
                            @error('newPasswordConfirmation') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                            @error('newPassword') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </label>

                        <div class="flex justify-end gap-3">
                            <button type="button" wire:click="backToNewPassword" class="cursor-pointer rounded-[5px] border border-[#a3a3a3] px-6 py-2 text-sm">Back</button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="confirmNewPassword" class="cursor-pointer rounded-[5px] bg-[#0d5750] px-6 py-2 text-sm text-white disabled:opacity-60">
                                <span wire:loading.remove wire:target="confirmNewPassword">Change password</span>
                                <span wire:loading wire:target="confirmNewPassword">Saving…</span>
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        @else
        <div wire:key="profile-main" class="flex flex-col gap-6">
            <div class="flex items-start gap-5">
                @if ($editing)
                    <label for="profile-photo" class="group relative block size-28 shrink-0 cursor-pointer">
                        <x-portal.avatar
                            :photo-url="$photo ? $photo->temporaryUrl() : $this->player->photo_url"
                            :initials="auth()->user()->initials()"
                            class="size-28 text-3xl ring-2 ring-[#178279]"
                        />
                        <span class="absolute inset-0 flex items-center justify-center rounded-full bg-black/60 p-3 text-center text-[11px] font-bold text-white opacity-0 transition group-hover:opacity-100">
                            Click to change profile
                        </span>
                    </label>
                    <input id="profile-photo" type="file" wire:model="photo" accept="image/*" class="hidden">
                    @error('photo')
                        <p class="text-[11px] text-red-600">{{ $message }}</p>
                    @enderror
                @else
                    <x-portal.avatar
                        :photo-url="$this->player->photo_url"
                        :initials="auth()->user()->initials()"
                        class="size-28 text-3xl ring-2 ring-[#178279]"
                    />
                @endif

                <div class="flex flex-col gap-3 pt-1">
                    @if ($editing)
                        <div class="flex flex-col gap-2">
                            <input type="text" wire:model="name" placeholder="Full Name"
                                   class="rounded-[5px] bg-[#11776e]/15 px-2.5 py-1.5 text-sm font-bold text-black focus:outline-none">
                            @error('name')
                                <p class="text-[11px] text-red-600">{{ $message }}</p>
                            @enderror

                            <input type="email" wire:model="email" placeholder="Email"
                                   class="rounded-[5px] bg-[#11776e]/15 px-2.5 py-1.5 text-xs text-black focus:outline-none">
                            @error('email')
                                <p class="text-[11px] text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @else
                        <div>
                            <h2 class="text-xl font-bold text-black uppercase">{{ $this->player->name }}</h2>
                            <p class="text-sm text-black/70">{{ auth()->user()->email }}</p>
                        </div>
                    @endif

                    @if (! $editing && ! auth()->user()->hasVerifiedEmail())
                        <div class="flex flex-col items-start gap-1">
                            @if ($verificationStatus === 'sent')
                                <p class="text-xs text-[#0d5750]">Verification link sent. Check your inbox.</p>
                            @else
                                <button type="button" wire:click="sendVerification" wire:loading.attr="disabled" wire:target="sendVerification"
                                        class="cursor-pointer rounded-[5px] bg-[#0d5750] px-4 py-1.5 text-xs font-bold text-white transition hover:bg-[#0b4742] disabled:cursor-not-allowed disabled:opacity-60">
                                    <span wire:loading.remove wire:target="sendVerification">Verify account</span>
                                    <span wire:loading wire:target="sendVerification">Sending…</span>
                                </button>
                                @if ($verificationStatus === 'throttled')
                                    <p class="text-[11px] text-red-600">Please wait a minute before requesting another link.</p>
                                @endif
                            @endif
                        </div>
                    @endif

                    <div class="flex flex-wrap items-center gap-2.5">
                        <span class="rounded-full bg-[#0d5750] px-4 py-1.5 text-xs font-bold text-white">
                            {{ $this->player->level ?? 'Unranked' }}
                        </span>
                        <span class="rounded-full bg-[#178279] px-4 py-1.5 text-xs font-bold text-white">
                            {{ $this->winRate }}% Win Rate
                        </span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div class="flex items-center gap-3 rounded-[5px] bg-[#77b9b3] px-5 py-4">
                    <span class="text-3xl font-bold text-white">{{ $this->player->statistic?->matches_played ?? 0 }}</span>
                    <span class="text-xs leading-tight font-bold text-white/90">Matches Played</span>
                </div>
                <div class="flex items-center gap-3 rounded-[5px] bg-[#77b9b3] px-5 py-4">
                    <span class="text-3xl font-bold text-white">{{ $this->player->statistic?->wins ?? 0 }}</span>
                    <span class="text-xs leading-tight font-bold text-white/90">Wins</span>
                </div>
                <div class="flex items-center gap-3 rounded-[5px] bg-[#77b9b3] px-5 py-4">
                    <span class="text-3xl font-bold text-white">{{ $this->player->statistic?->losses ?? 0 }}</span>
                    <span class="text-xs leading-tight font-bold text-white/90">Losses</span>
                </div>
            </div>

            <div class="grid grid-cols-4 gap-4">
                <div>
                    <p class="text-xs font-bold text-black">Full Name</p>
                    <p class="text-sm text-black/70">{{ $this->player->name }}</p>
                </div>
                <div>
                    <p class="text-xs font-bold text-black">Age</p>
                    <p class="text-sm text-black/70">{{ $this->player->age ? "{$this->player->age} yrs. old" : '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-bold text-black">Gender</p>
                    <p class="text-sm text-black/70">{{ $this->player->gender ? Str::headline($this->player->gender) : '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-bold text-black">Birth Date</p>
                    <p class="text-sm text-black/70">{{ $this->player->birth_date?->format('F j, Y') ?? '—' }}</p>
                </div>
            </div>

            <div>
                <p class="text-xs font-bold text-black">Address</p>
                @if ($editing)
                    <input type="text" wire:model="address" placeholder="Address"
                           class="mt-1 w-full rounded-[5px] bg-[#11776e]/15 px-2.5 py-2 text-sm text-black focus:outline-none">
                    @error('address')
                        <p class="text-[11px] text-red-600">{{ $message }}</p>
                    @enderror
                @else
                    <p class="text-sm text-black/70">{{ $this->player->address ?? '—' }}</p>
                @endif
            </div>

            <div>
                <p class="text-xs font-bold text-black">Contact Number</p>
                @if ($editing)
                    <input type="text" wire:model="phoneNumber" placeholder="Contact Number"
                           class="mt-1 w-full rounded-[5px] bg-[#11776e]/15 px-2.5 py-2 text-sm text-black focus:outline-none">
                    @error('phoneNumber')
                        <p class="text-[11px] text-red-600">{{ $message }}</p>
                    @enderror
                @else
                    <p class="text-sm text-black/70">{{ $this->player->phone_number ?? '—' }}</p>
                @endif
            </div>

            <div class="flex justify-end gap-3">
                @if ($editing)
                    <button type="button" wire:click="cancelEditing"
                            class="cursor-pointer rounded-[5px] border border-[#a3a3a3] px-6 py-2 text-sm text-black transition hover:bg-black/5">
                        Cancel
                    </button>
                    <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                            class="cursor-pointer rounded-[5px] bg-[#0d5750] px-6 py-2 text-sm text-white transition hover:bg-[#0b4742] disabled:cursor-not-allowed disabled:opacity-60">
                        <span wire:loading.remove wire:target="save">Save</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </button>
                @else
                    <button type="button" wire:click="startEditing"
                            class="cursor-pointer rounded-[5px] border border-[#178279] px-6 py-2 text-sm text-[#178279] transition hover:bg-[#178279]/10">
                        Edit
                    </button>
                    <button type="button" wire:click="openSecurity"
                            class="cursor-pointer rounded-[5px] border border-[#178279] px-6 py-2 text-sm text-[#178279] transition hover:bg-[#178279]/10">
                        Security Details
                    </button>
                @endif
            </div>
        </div>
        @endif
    </flux:modal>
</div>
