<?php

use App\Support\Profile\ChangesPassword;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

/**
 * Profile modal for admins and umpires: change the display name and picture,
 * and the account password. Players have their own richer modal.
 */
new class extends Component {
    use ChangesPassword;
    use WithFileUploads;

    public bool $editing = false;

    public $photo = null;

    public string $name = '';

    public function startEditing(): void
    {
        $this->name = auth()->user()->name;
        $this->photo = null;
        $this->resetValidation();
        $this->editing = true;
    }

    public function cancelEditing(): void
    {
        $this->photo = null;
        $this->resetValidation();
        $this->editing = false;
    }

    /**
     * Save the display name and, if one was chosen, the new picture.
     */
    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ]);

        $user = auth()->user();
        $attributes = ['name' => $validated['name']];

        if ($this->photo) {
            $previousPhoto = $user->photo_path;
            $attributes['photo_path'] = $this->photo->store('staff-photos', 'public');

            if ($previousPhoto) {
                Storage::disk('public')->delete($previousPhoto);
            }
        }

        $user->update($attributes);

        $this->photo = null;
        $this->editing = false;

        $this->dispatch('profile-updated', name: $validated['name']);
    }

    public function removePhoto(): void
    {
        $user = auth()->user();

        if ($user->photo_path) {
            Storage::disk('public')->delete($user->photo_path);
            $user->update(['photo_path' => null]);
        }

        $this->photo = null;
    }
}; ?>

<div>
    <flux:modal name="staff-profile" class="w-full max-w-lg dark:bg-white! dark:ring-black/5!">
        @if ($securityView)
            <x-profile.security-form :has-password="$this->hasPassword" :password-step="$passwordStep" :new-password="$newPassword" />
        @else
            <div wire:key="staff-profile-main" class="flex flex-col gap-6 pt-2 pr-6 text-black">
                <div class="flex flex-col items-center gap-5 sm:flex-row sm:items-start">
                    @if ($editing)
                        <div class="flex shrink-0 flex-col items-center gap-2">
                        <label for="staff-profile-photo" class="group relative block size-28 shrink-0 cursor-pointer">
                            <x-portal.avatar
                                :photo-url="$photo && $photo->isPreviewable() ? $photo->temporaryUrl() : auth()->user()->profilePhotoUrl()"
                                :initials="auth()->user()->initials()"
                                class="size-28 text-3xl ring-2 ring-[#178279]"
                            />
                            <span class="absolute inset-0 flex items-center justify-center rounded-full bg-black/60 p-3 text-center text-[11px] font-bold text-white opacity-0 transition group-hover:opacity-100">
                                Click to change picture
                            </span>
                        </label>
                        <input id="staff-profile-photo" type="file" wire:model="photo" accept="image/*" class="hidden">

                        <label for="staff-profile-photo"
                               class="flex cursor-pointer items-center gap-1.5 rounded-[5px] border border-[#178279] px-3 py-1.5 text-xs font-bold text-[#178279] transition hover:bg-[#178279]/10">
                            <flux:icon.arrow-up-tray class="size-3.5" />
                            <span wire:loading.remove wire:target="photo">{{ $photo ? 'Change picture' : (auth()->user()->profilePhotoUrl() ? 'Replace picture' : 'Upload picture') }}</span>
                            <span wire:loading wire:target="photo">Uploading…</span>
                        </label>
                        <p class="text-[10px] text-black/50">JPG or PNG, up to 2 MB</p>
                        </div>
                    @else
                        <x-portal.avatar
                            :photo-url="auth()->user()->profilePhotoUrl()"
                            :initials="auth()->user()->initials()"
                            class="size-28 text-3xl ring-2 ring-[#178279]"
                        />
                    @endif

                    <div class="flex min-w-0 flex-1 flex-col gap-2 pt-1">
                        @if ($editing)
                            <label for="staff-profile-name" class="text-xs font-bold">Full Name</label>
                            <input id="staff-profile-name" type="text" wire:model="name" placeholder="Full Name"
                                   class="w-full rounded-[5px] bg-[#11776e]/15 px-2.5 py-2 text-sm text-black focus:outline-none">
                            @error('name') <p class="text-[11px] text-red-600">{{ $message }}</p> @enderror
                            @error('photo') <p class="text-[11px] text-red-600">{{ $message }}</p> @enderror

                            <p class="mt-1 text-xs font-bold">Email</p>
                            <p class="truncate text-sm text-black/60">{{ auth()->user()->email }}</p>

                            @if (auth()->user()->photo_path && ! $photo)
                                <button type="button" wire:click="removePhoto" class="mt-1 cursor-pointer self-start text-[11px] text-red-600 underline">
                                    Remove current picture
                                </button>
                            @endif
                        @else
                            <h2 class="text-xl font-bold text-black uppercase">{{ auth()->user()->name }}</h2>
                            <p class="truncate text-sm text-black/70">{{ auth()->user()->email }}</p>
                            <span class="w-fit rounded-full bg-[#0d5750] px-4 py-1.5 text-xs font-bold text-white uppercase">
                                {{ auth()->user()->role }}
                            </span>
                        @endif
                    </div>
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
