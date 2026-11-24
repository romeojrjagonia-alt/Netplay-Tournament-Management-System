@props(['hasPassword', 'passwordStep', 'newPassword' => ''])

<div wire:key="profile-security" class="flex flex-col gap-6 text-black">
    <div>
        <h2 class="text-xl font-bold">Security Details</h2>
        <p class="text-sm text-black/70">{{ $hasPassword ? 'Change your account password.' : 'You signed in with Google, so you have no password yet. Set one to also sign in with your email.' }}</p>
    </div>

    <div class="flex items-center justify-between rounded-t-[5px] bg-[#138177] px-[15px] py-[10px] text-white">
        <span class="text-[11px] font-bold">
            {{ ['', 'Current password', 'New password', 'Confirm new password'][$passwordStep] }}
        </span>
        <span class="text-[11px] italic">Step {{ $hasPassword ? $passwordStep : $passwordStep - 1 }} of {{ $hasPassword ? 3 : 2 }}</span>
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
