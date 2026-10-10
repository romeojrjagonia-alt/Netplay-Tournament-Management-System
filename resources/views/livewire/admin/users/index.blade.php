<?php

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.admin', ['title' => 'Users'])] class extends Component {
    use WithPagination;

    public const PER_PAGE = 10;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $roleFilter = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
    }

    /**
     * Umpires can't sign themselves up (anyone could claim the role), so an
     * admin creates their accounts here.
     */
    public function createUmpire(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => 'umpire',
            'email_verified_at' => now(),
        ]);

        $this->dispatch('system-message', title: 'Umpire created', message: "Umpire account created for {$data['email']}. Share the password with them securely.", variant: 'success');

        $this->reset('name', 'email', 'password', 'password_confirmation');
        $this->resetValidation();
        unset($this->users);
    }

    public function updatedRoleFilter(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->when($this->roleFilter !== '', fn ($query) => $query->where('role', $this->roleFilter))
            ->orderBy('role')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-col gap-1">
        <h2 class="text-2xl font-bold text-white">Users</h2>
        <p class="text-xs text-white/70">Create umpire accounts and review everyone who can sign in.</p>
    </div>


    <form wire:submit="createUmpire" class="flex flex-col gap-4 rounded-[10px] bg-white px-4 py-5 sm:px-[35px] sm:py-[23px]">
        <span class="text-xs font-bold text-black">New Umpire</span>

        <div class="grid gap-3 sm:grid-cols-2">
            <label class="flex flex-col gap-1 text-[11px] text-black">
                Full name
                <input type="text" wire:model="name" autocomplete="off" class="rounded-[5px] border border-black/20 px-2 py-1.5 text-xs">
                @error('name') <span class="text-[10px] text-red-600">{{ $message }}</span> @enderror
            </label>
            <label class="flex flex-col gap-1 text-[11px] text-black">
                Email
                <input type="email" wire:model="email" autocomplete="off" class="rounded-[5px] border border-black/20 px-2 py-1.5 text-xs">
                @error('email') <span class="text-[10px] text-red-600">{{ $message }}</span> @enderror
            </label>
            <label class="flex flex-col gap-1 text-[11px] text-black">
                Password
                <input type="password" wire:model="password" autocomplete="new-password" class="rounded-[5px] border border-black/20 px-2 py-1.5 text-xs">
                @error('password') <span class="text-[10px] text-red-600">{{ $message }}</span> @enderror
            </label>
            <label class="flex flex-col gap-1 text-[11px] text-black">
                Confirm password
                <input type="password" wire:model="password_confirmation" autocomplete="new-password" class="rounded-[5px] border border-black/20 px-2 py-1.5 text-xs">
            </label>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="flex cursor-pointer items-center gap-1.5 rounded-[10px] bg-[#3e948d] px-[30px] py-[10px] text-xs font-medium text-white">
                <flux:icon.plus class="size-4" />
                Create Umpire
            </button>
        </div>
    </form>

    <div class="flex w-full flex-col gap-2.5 rounded-[10px] bg-white px-4 py-5 sm:px-[35px] sm:py-[23px]">
        <div class="flex items-center justify-between gap-3">
            <span class="text-xs font-bold text-black">Accounts ({{ $this->users->total() }})</span>
            <select wire:model.live="roleFilter" aria-label="Filter by role" class="rounded-[5px] border border-black/20 px-2 py-1 text-xs text-black">
                <option value="">All roles</option>
                <option value="admin">Admins</option>
                <option value="umpire">Umpires</option>
                <option value="player">Players</option>
            </select>
        </div>

        <div class="overflow-x-auto">
            <div class="flex min-w-[480px] flex-col">
                <div class="flex gap-1.5 border-b border-[#8cc7c1] bg-[#e7f0ef]">
                    <span class="flex-1 p-2.5 text-center text-xs text-black">Name</span>
                    <span class="flex-1 p-2.5 text-center text-xs text-black">Email</span>
                    <span class="w-24 p-2.5 text-center text-xs text-black">Role</span>
                </div>

                @forelse ($this->users as $user)
                    <div class="flex items-center gap-1.5 border-b border-[#ddefed]" wire:key="user-{{ $user->id }}">
                        <span class="flex-1 p-2.5 text-center text-xs text-black">{{ $user->name }}</span>
                        <span class="flex-1 p-2.5 text-center text-xs text-black">{{ $user->email }}</span>
                        <span class="w-24 p-2.5 text-center text-xs text-black capitalize">{{ $user->role }}</span>
                    </div>
                @empty
                    <p class="py-6 text-center text-xs text-black/50">No users found.</p>
                @endforelse
            </div>
        </div>

        @if ($this->users->hasPages())
            <div class="pt-2">{{ $this->users->links() }}</div>
        @endif
    </div>
</div>
