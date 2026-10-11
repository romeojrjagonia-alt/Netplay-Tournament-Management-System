<?php

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.admin', ['title' => 'Users'])] class extends Component {
    use WithPagination;

    public const PER_PAGE = 10;

    public const ROLES = ['admin', 'umpire', 'player'];

    public bool $showFormModal = false;

    public ?int $editingUserId = null;

    public string $name = '';

    public string $email = '';

    public string $role = 'umpire';

    public string $password = '';

    public string $password_confirmation = '';

    public string $search = '';

    public string $roleFilter = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function openEditModal(int $userId): void
    {
        $user = User::findOrFail($userId);

        $this->resetForm();
        $this->editingUserId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role;
        $this->showFormModal = true;
    }

    /**
     * Accounts created or edited here are trusted: the admin vouches for the
     * email address, so new accounts are marked verified straight away.
     */
    public function save(): void
    {
        $editing = $this->editingUserId !== null ? User::findOrFail($this->editingUserId) : null;

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($editing?->id)],
            'role' => ['required', Rule::in(self::ROLES)],
            'password' => [$editing ? 'nullable' : 'required', 'confirmed', Password::min(8)],
        ]);

        if ($editing?->is(auth()->user()) && $data['role'] !== 'admin') {
            $this->addError('role', 'You can\'t remove your own admin role.');

            return;
        }

        $attributes = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
        ];

        if (filled($data['password'])) {
            $attributes['password'] = $data['password'];
        }

        if ($editing) {
            $editing->update($attributes);
            $message = "{$data['email']} was updated.";
            $title = 'User updated';
        } else {
            User::create($attributes + ['email_verified_at' => now()]);
            $message = "Account created for {$data['email']}. Share the password with them securely.";
            $title = 'User created';
        }

        $this->showFormModal = false;
        $this->resetForm();
        unset($this->users);

        $this->dispatch('system-message', title: $title, message: $message, variant: 'success');
    }

    public function delete(int $userId): void
    {
        $user = User::findOrFail($userId);

        if ($user->is(auth()->user())) {
            $this->dispatch('system-message', title: 'Can\'t delete', message: 'You can\'t delete your own account.', variant: 'danger');

            return;
        }

        $user->delete();

        unset($this->users);

        $this->dispatch('system-message', title: 'User deleted', message: "{$user->email} was deleted.", variant: 'success');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
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
        $search = trim($this->search);

        return User::query()
            ->when($this->roleFilter !== '', fn ($query) => $query->where('role', $this->roleFilter))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderBy('role')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE);
    }

    private function resetForm(): void
    {
        $this->reset('editingUserId', 'name', 'email', 'role', 'password', 'password_confirmation');
        $this->resetValidation();
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-col gap-3 border-b border-white/40 pb-3">
        <h2 class="text-2xl font-bold text-white">User Management</h2>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <span class="text-xl font-bold text-white">All Users</span>
            <span class="text-xl font-bold text-white/70">{{ $this->users->total() }}</span>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <label class="flex h-10 w-full items-center gap-2 rounded-[5px] bg-white px-3 sm:w-[265px]">
                <flux:icon.magnifying-glass class="size-4 shrink-0 text-black/60" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search" aria-label="Search users" class="w-full border-0 bg-transparent p-0 text-xs text-black placeholder:text-black/50 focus:ring-0 focus:outline-none">
            </label>

            <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
                <button type="button" x-on:click="open = !open" class="flex h-10 cursor-pointer items-center gap-2 rounded-[5px] bg-white px-4 text-xs text-black">
                    <flux:icon.funnel class="size-4" />
                    Filter
                    @if ($roleFilter !== '')
                        <span class="rounded-full bg-[#e7f0ef] px-2 py-0.5 text-[10px] text-[#138177] capitalize">{{ $roleFilter }}</span>
                    @endif
                </button>

                <div x-show="open" x-cloak class="absolute right-0 z-20 mt-1 flex w-44 flex-col rounded-[5px] bg-white p-1 text-xs text-black shadow-lg">
                    @foreach (['' => 'All roles', 'admin' => 'Admins', 'umpire' => 'Umpires', 'player' => 'Players'] as $value => $label)
                        <button type="button" wire:click="$set('roleFilter', '{{ $value }}')" x-on:click="open = false" class="flex cursor-pointer items-center justify-between rounded-[5px] px-3 py-2 text-left hover:bg-[#e7f0ef] {{ $roleFilter === $value ? 'font-bold' : '' }}">
                            {{ $label }}
                            @if ($roleFilter === $value)
                                <flux:icon.check class="size-3.5 text-[#138177]" />
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>

            <button type="button" wire:click="openCreateModal" class="flex h-10 cursor-pointer items-center gap-2 rounded-[5px] bg-[#3e948d] px-4 text-xs font-medium text-white">
                <flux:icon.plus class="size-4" />
                Add User
            </button>
        </div>
    </div>

    <div class="flex w-full flex-col rounded-[10px] bg-white px-4 py-3 sm:px-[19px]">
        <div class="hidden items-center gap-3 px-3 pb-2 text-[10px] text-black/60 md:flex">
            <span class="flex-1 pl-14">User name</span>
            <span class="w-28 text-center">Role</span>
            <span class="w-36">Date Added</span>
            <span class="w-6"></span>
        </div>

        @forelse ($this->users as $user)
            <div class="flex items-center gap-3 border-t border-[#ececec] px-3 py-3 first:border-t-0" wire:key="user-{{ $user->id }}">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-[#52528f] text-xs font-bold text-white uppercase">{{ \Illuminate\Support\Str::substr($user->initials(), 0, 2) }}</span>

                <div class="flex min-w-0 flex-1 flex-col">
                    <span class="truncate text-xs font-bold text-black">{{ $user->name }}</span>
                    <span class="truncate text-[11px] text-black/70">{{ $user->email }}</span>
                </div>

                <span class="flex w-28 justify-center">
                    <span class="rounded-full border border-[#cfe9ec] bg-[#eef9fb] px-4 py-0.5 text-[10px] text-[#138177] capitalize">{{ $user->role }}</span>
                </span>

                <span class="hidden w-36 text-[11px] text-black/70 md:block">{{ $user->created_at?->format('F j, Y') }}</span>

                <div class="relative w-6" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
                    <button type="button" x-on:click="open = !open" class="flex size-6 cursor-pointer items-center justify-center text-black" aria-label="Actions for {{ $user->name }}">
                        <flux:icon.ellipsis-vertical class="size-4" />
                    </button>

                    <div x-show="open" x-cloak class="absolute right-0 z-20 mt-1 flex w-36 flex-col rounded-[5px] bg-white p-1 text-xs text-black shadow-lg ring-1 ring-black/10">
                        <button type="button" wire:click="openEditModal({{ $user->id }})" x-on:click="open = false" class="flex cursor-pointer items-center gap-2 rounded-[5px] px-3 py-2 text-left hover:bg-[#e7f0ef]">
                            <flux:icon.pencil-square class="size-4" />
                            Edit
                        </button>
                        @if ($user->id !== auth()->id())
                            <button
                                type="button"
                                x-on:click="open = false; $dispatch('system-confirm', { title: 'Delete user?', message: @js('Delete '.$user->name.' ('.$user->email.')? This can\'t be undone.'), variant: 'danger', confirmLabel: 'Delete', confirm: () => $wire.delete({{ $user->id }}) })"
                                class="flex cursor-pointer items-center gap-2 rounded-[5px] px-3 py-2 text-left text-[#bd6b6c] hover:bg-[#fbeeee]"
                            >
                                <flux:icon.trash class="size-4" />
                                Delete
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <p class="py-6 text-center text-xs text-black/50">No users found.</p>
        @endforelse

        @if ($this->users->hasPages())
            <div class="pt-3">{{ $this->users->links() }}</div>
        @endif
    </div>

    <flux:modal wire:model.self="showFormModal" class="w-full max-w-lg dark:bg-white! dark:ring-black/5!">
        <form wire:submit="save" class="flex flex-col gap-5">
            <div class="flex flex-col gap-1">
                <h2 class="text-base font-bold text-black">{{ $editingUserId ? 'Edit User' : 'Add User' }}</h2>
                <p class="text-sm text-black/70">
                    {{ $editingUserId ? 'Update this account. Leave the password blank to keep the current one.' : 'Accounts added here are verified automatically and can sign in right away.' }}
                </p>
            </div>

            <div class="flex flex-col gap-3">
                <label class="flex flex-col gap-1 text-[11px] text-black">
                    Role
                    <select wire:model="role" class="rounded-[5px] border border-black/20 px-2 py-1.5 text-xs">
                        <option value="admin">Admin</option>
                        <option value="umpire">Umpire</option>
                        <option value="player">Player</option>
                    </select>
                    @error('role') <span class="text-[10px] text-red-600">{{ $message }}</span> @enderror
                </label>
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
                    {{ $editingUserId ? 'New password (optional)' : 'Password' }}
                    <input type="password" wire:model="password" autocomplete="new-password" class="rounded-[5px] border border-black/20 px-2 py-1.5 text-xs">
                    @error('password') <span class="text-[10px] text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="flex flex-col gap-1 text-[11px] text-black">
                    Confirm password
                    <input type="password" wire:model="password_confirmation" autocomplete="new-password" class="rounded-[5px] border border-black/20 px-2 py-1.5 text-xs">
                </label>
            </div>

            <div class="flex justify-end gap-2">
                <button type="button" wire:click="$set('showFormModal', false)" class="cursor-pointer rounded-[5px] border border-[#a3a3a3] px-4 py-2 text-xs font-medium text-[#6b6b6b]">
                    Cancel
                </button>
                <button type="submit" class="cursor-pointer rounded-[5px] bg-[#3e948d] px-4 py-2 text-xs font-medium text-white">
                    {{ $editingUserId ? 'Save Changes' : 'Add User' }}
                </button>
            </div>
        </form>
    </flux:modal>
</div>
