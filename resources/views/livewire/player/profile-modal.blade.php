<?php

use App\Models\Player;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public bool $editing = false;

    public string $name = '';

    public string $email = '';

    public string $address = '';

    public string $phoneNumber = '';

    #[Computed]
    public function player(): Player
    {
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
        $this->resetValidation();
        $this->editing = false;
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
        ]);

        $user = Auth::user();
        $user->name = $validated['name'];
        $user->email = $validated['email'];

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->player->update([
            'name' => $validated['name'],
            'address' => $validated['address'],
            'phone_number' => $validated['phoneNumber'],
        ]);

        $this->editing = false;

        $this->dispatch('profile-updated', name: $validated['name']);
    }
}; ?>

<div>
    <flux:modal name="player-profile" class="w-full max-w-2xl dark:bg-white! dark:ring-black/5!">
        <div class="flex flex-col gap-6">
            <div class="flex items-start gap-5">
                <x-portal.avatar
                    :photo-url="$this->player->photo_url"
                    :initials="auth()->user()->initials()"
                    class="size-28 text-3xl ring-2 ring-[#178279]"
                />

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
                    <button type="button" disabled class="cursor-not-allowed rounded-[5px] border border-[#d3d3d3] px-6 py-2 text-sm text-black/40">
                        Security Details
                    </button>
                @endif
            </div>
        </div>
    </flux:modal>
</div>
