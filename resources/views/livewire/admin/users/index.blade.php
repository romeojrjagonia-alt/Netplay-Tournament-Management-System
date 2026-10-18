<?php

use App\Actions\Players\MergeDuplicatePlayers;
use App\Actions\Players\MergePlayerRecords;
use App\Actions\Players\PruneUnusedGuests;
use App\Models\DismissedPlayerPair;
use App\Mail\PlayerLinkDecided;
use App\Models\Player;
use App\Models\PlayerLinkRequest;
use App\Models\User;
use App\Rules\AccountPassword;
use App\Support\Players\DuplicatePlayerFinder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.admin', ['title' => 'Users'])] class extends Component {
    use WithFileUploads;

    public const ROLES = ['admin', 'umpire', 'player'];

    public bool $showFormModal = false;

    public bool $showLinkModal = false;

    public bool $showRejectLinkModal = false;

    public ?int $editingUserId = null;

    public string $firstName = '';

    public string $lastName = '';

    public string $middleInitial = '';

    public string $birthDate = '';

    public string $phoneNumber = '';

    public string $gender = '';

    public string $purok = '';

    public string $city = '';

    public string $province = '';

    public string $club = '';

    public $profilePhoto = null;

    public string $email = '';

    public string $role = 'umpire';

    public string $password = '';

    public string $password_confirmation = '';

    public string $search = '';

    public string $roleFilter = '';

    public ?int $rejectingLinkRequestId = null;

    public string $linkRejectionReason = '';

    /** The player account an admin is manually linking a guest record to. */
    public ?int $linkingUserId = null;

    public string $playerSearch = '';

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
        $user = User::with('player')->findOrFail($userId);
        $player = $user->player;

        $this->resetForm();
        $this->editingUserId = $user->id;

        [$this->firstName, $this->lastName, $this->middleInitial] = $this->splitName($user->name);
        $this->email = $user->email;
        $this->role = $user->role;

        if ($player) {
            $this->birthDate = $player->birth_date?->format('Y-m-d') ?? '';
            $this->phoneNumber = $player->phone_number ?? '';
            $this->gender = in_array($player->gender, ['male', 'female'], true) ? $player->gender : '';
            $this->club = $player->club ?? '';

            // Addresses are stored as "purok, city, province".
            $parts = array_map('trim', explode(',', (string) $player->address));

            if (count($parts) === 3) {
                [$this->purok, $this->city, $this->province] = $parts;
            } else {
                $this->purok = (string) $player->address;
            }
        }

        $this->showFormModal = true;
    }

    /**
     * Accounts created or edited here are trusted: the admin vouches for the
     * email address, so new accounts are marked verified straight away. The
     * form mirrors sign-up, and every account gets a player record like a
     * self sign-up does. The profile fields are required when creating; when
     * editing they only need to be valid if filled in, so older accounts
     * with an empty profile can still be edited.
     */
    public function save(): void
    {
        $editing = $this->editingUserId !== null ? User::findOrFail($this->editingUserId) : null;
        $profile = $editing ? 'nullable' : 'required';

        $data = $this->validate([
            'firstName' => ['required', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'middleInitial' => ['nullable', 'string', 'max:5'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($editing?->id)],
            'role' => ['required', Rule::in(self::ROLES)],
            'password' => [$editing ? 'nullable' : 'required', 'confirmed', new AccountPassword],
            'birthDate' => [$profile, 'date', 'before:today'],
            'phoneNumber' => [$profile, 'string', 'max:255'],
            'gender' => [$profile, 'in:male,female'],
            'purok' => [$profile, 'string', 'max:255'],
            'city' => [$profile, 'string', 'max:255'],
            'province' => [$profile, 'string', 'max:255'],
            'club' => ['nullable', 'string', 'max:255'],
            'profilePhoto' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($editing?->is(auth()->user()) && $data['role'] !== 'admin') {
            $this->addError('role', 'You can\'t remove your own admin role.');

            return;
        }

        $fullName = collect([
            $data['firstName'],
            filled($data['middleInitial']) ? rtrim($data['middleInitial'], '.').'.' : null,
            $data['lastName'],
        ])->filter()->implode(' ');

        $attributes = [
            'name' => $fullName,
            'email' => $data['email'],
            'role' => $data['role'],
        ];

        if (filled($data['password'])) {
            $attributes['password'] = $data['password'];
        }

        if ($editing) {
            $editing->update($attributes);
            $user = $editing;
            $message = "{$data['email']} was updated.";
            $title = 'User updated';
        } else {
            $user = User::create($attributes + ['email_verified_at' => now()]);
            $message = "Account created for {$data['email']}. Share the password with them securely.";
            $title = 'User created';
        }

        $this->saveProfile($user, $data, $fullName);

        $this->showFormModal = false;
        $this->resetForm();
        unset($this->users);

        $this->dispatch('system-message', title: $title, message: $message, variant: 'success');
    }

    /**
     * The player record every account has (as with sign-up): the contact
     * email follows the account, and only fields the admin filled in change.
     *
     * @param  array<string, mixed>  $data
     */
    private function saveProfile(User $user, array $data, string $fullName): void
    {
        $player = $user->ensurePlayer();

        $attributes = [
            'name' => $fullName,
            'email' => $user->email,
            'club' => $data['club'] !== '' ? $data['club'] : null,
        ];

        foreach (['birthDate' => 'birth_date', 'gender' => 'gender', 'phoneNumber' => 'phone_number'] as $field => $column) {
            if (filled($data[$field])) {
                $attributes[$column] = $data[$field];
            }
        }

        if (filled($data['purok']) && filled($data['city']) && filled($data['province'])) {
            $attributes['address'] = "{$data['purok']}, {$data['city']}, {$data['province']}";
        }

        if ($this->profilePhoto) {
            $previousPhoto = $player->photo_path;
            $attributes['photo_path'] = $this->profilePhoto->store('player-photos', 'public');

            if ($previousPhoto) {
                Storage::disk('public')->delete($previousPhoto);
            }
        }

        $player->update($attributes);
    }

    /**
     * Best-effort split of a combined "First M. Last" name back into its
     * parts, for pre-filling the form.
     *
     * @return array{0: string, 1: string, 2: string} [first, last, middleInitial]
     */
    private function splitName(string $name): array
    {
        $parts = explode(' ', trim($name));

        if (count($parts) >= 3 && str_ends_with($parts[1], '.') && mb_strlen($parts[1]) <= 3) {
            return [$parts[0], implode(' ', array_slice($parts, 2)), rtrim($parts[1], '.')];
        }

        if (count($parts) >= 2) {
            return [$parts[0], implode(' ', array_slice($parts, 1)), ''];
        }

        return [$parts[0] ?? '', '', ''];
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

    /**
     * Players asking to claim the guest record registered under their email.
     *
     * @return \Illuminate\Support\Collection<int, PlayerLinkRequest>
     */
    #[Computed]
    public function linkRequests()
    {
        return PlayerLinkRequest::query()
            ->pending()
            ->with(['user', 'player.statistic', 'player.applicationPlayers.application.tournamentCategory.tournament'])
            ->oldest()
            ->get();
    }

    public function approveLinkRequest(int $linkRequestId, MergePlayerRecords $merge): void
    {
        $linkRequest = PlayerLinkRequest::query()->pending()->with(['user', 'player'])->findOrFail($linkRequestId);

        try {
            $merge->handle($linkRequest->player, $linkRequest->user);
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('system-message', title: 'Can\'t link these records', message: $e->getMessage(), variant: 'danger');

            return;
        }

        $this->decideLinkRequest($linkRequest, PlayerLinkRequest::APPROVED);

        $this->dispatch('system-message', title: 'Records linked', message: "{$linkRequest->player->name} was linked to {$linkRequest->user->email}.", variant: 'success');
    }

    public function openRejectLinkRequest(int $linkRequestId): void
    {
        $this->rejectingLinkRequestId = PlayerLinkRequest::query()->pending()->whereKey($linkRequestId)->value('id');
        $this->linkRejectionReason = '';
        $this->showRejectLinkModal = $this->rejectingLinkRequestId !== null;
    }

    public function rejectLinkRequest(): void
    {
        $this->validate(['linkRejectionReason' => ['nullable', 'string', 'max:500']]);

        $linkRequest = PlayerLinkRequest::query()->pending()->with(['user', 'player'])->findOrFail($this->rejectingLinkRequestId);

        $this->decideLinkRequest($linkRequest, PlayerLinkRequest::REJECTED, trim($this->linkRejectionReason) ?: null);

        $this->rejectingLinkRequestId = null;
        $this->showRejectLinkModal = false;
        $this->linkRejectionReason = '';

        $this->dispatch('system-message', title: 'Request rejected', message: 'The player was emailed.', variant: 'success');
    }

    public function openLinkModal(int $userId): void
    {
        $this->linkingUserId = User::query()->where('role', 'player')->whereKey($userId)->value('id');
        $this->playerSearch = '';
        $this->showLinkModal = $this->linkingUserId !== null;
    }

    /**
     * Guest players (no account) matching the admin's search, for a manual link.
     *
     * @return \Illuminate\Support\Collection<int, Player>
     */
    #[Computed]
    public function linkablePlayers()
    {
        $search = trim($this->playerSearch);

        if ($this->linkingUserId === null || mb_strlen($search) < 2) {
            return collect();
        }

        return Player::query()
            ->whereNull('user_id')
            ->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('phone_number', 'like', "%{$search}%"))
            ->orderBy('name')
            ->limit(10)
            ->get();
    }

    /**
     * Admin links a guest record straight to an account (for guests who gave
     * no email, or a different one), without a player request.
     */
    public function linkPlayer(int $playerId, MergePlayerRecords $merge): void
    {
        $user = User::query()->where('role', 'player')->findOrFail($this->linkingUserId);
        $player = Player::query()->whereNull('user_id')->findOrFail($playerId);

        try {
            $merge->handle($player, $user);
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('system-message', title: 'Can\'t link these records', message: $e->getMessage(), variant: 'danger');

            return;
        }

        $linkRequest = PlayerLinkRequest::updateOrCreate(
            ['user_id' => $user->id, 'player_id' => $player->id],
            ['status' => PlayerLinkRequest::PENDING],
        );

        $this->decideLinkRequest($linkRequest->load(['user', 'player']), PlayerLinkRequest::APPROVED);

        $this->linkingUserId = null;
        $this->showLinkModal = false;
        $this->playerSearch = '';

        $this->dispatch('system-message', title: 'Records linked', message: "{$player->name} was linked to {$user->email}.", variant: 'success');
    }

    private function decideLinkRequest(PlayerLinkRequest $linkRequest, string $status, ?string $reason = null): void
    {
        $linkRequest->update([
            'status' => $status,
            'decided_by' => auth()->id(),
            'decided_at' => now(),
            'rejection_reason' => $reason,
        ]);

        unset($this->linkRequests, $this->users);

        try {
            Mail::to($linkRequest->user->email)->send(new PlayerLinkDecided($linkRequest));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Pairs of player records that look like the same person.
     *
     * @return \Illuminate\Support\Collection<int, array{a: Player, b: Player, reasons: list<string>}>
     */
    #[Computed]
    public function duplicatePairs()
    {
        return app(DuplicatePlayerFinder::class)->pairs();
    }

    public function mergeDuplicates(int $firstPlayerId, int $secondPlayerId, MergeDuplicatePlayers $merge): void
    {
        $first = Player::query()->findOrFail($firstPlayerId);
        $second = Player::query()->findOrFail($secondPlayerId);

        try {
            $survivor = $merge->handle($first, $second);
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('system-message', title: 'Can\'t merge these records', message: $e->getMessage(), variant: 'danger');

            return;
        }

        unset($this->duplicatePairs, $this->unusedGuestCount, $this->linkRequests);

        $this->dispatch('system-message', title: 'Records merged', message: "The two records were merged into {$survivor->name}.", variant: 'success');
    }

    /**
     * An admin judged these two to be different people; stop suggesting them.
     */
    public function dismissDuplicate(int $firstPlayerId, int $secondPlayerId): void
    {
        $ids = Player::query()->whereIn('id', [$firstPlayerId, $secondPlayerId])->pluck('id');

        if ($ids->count() === 2) {
            DismissedPlayerPair::dismiss($firstPlayerId, $secondPlayerId);
        }

        unset($this->duplicatePairs);
    }

    #[Computed]
    public function unusedGuestCount(): int
    {
        return app(PruneUnusedGuests::class)->count();
    }

    public function pruneUnusedGuests(PruneUnusedGuests $prune): void
    {
        $deleted = $prune->handle();

        unset($this->unusedGuestCount, $this->duplicatePairs);

        $this->dispatch('system-message', title: 'Guest records deleted', message: "{$deleted} unused guest record(s) were deleted.", variant: 'success');
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function users(): Collection
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
            ->get();
    }

    private function resetForm(): void
    {
        $this->reset('editingUserId', 'firstName', 'lastName', 'middleInitial', 'email', 'role', 'password', 'password_confirmation', 'birthDate', 'phoneNumber', 'gender', 'purok', 'city', 'province', 'club', 'profilePhoto');
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
            <span class="text-xl font-bold text-white/70">{{ $this->users->count() }}</span>
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

    @if ($this->linkRequests->isNotEmpty())
        <div class="flex flex-col gap-3 rounded-[10px] bg-white px-4 py-4 text-black sm:px-[19px]">
            <div class="flex items-center gap-2">
                <span class="text-sm font-bold">Link requests</span>
                <span class="rounded-full bg-[#fff1d6] px-2 py-0.5 text-[10px] font-bold text-[#8a5a00]">{{ $this->linkRequests->count() }} pending</span>
            </div>

            @foreach ($this->linkRequests as $linkRequest)
                @php
                    $entries = $linkRequest->player->applicationPlayers
                        ->map(fn ($ap) => $ap->application?->tournamentCategory?->tournament?->name)
                        ->filter()->unique()->implode(', ');
                @endphp
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-[#ececec] pt-3" wire:key="link-{{ $linkRequest->id }}">
                    <div class="flex min-w-0 flex-col gap-0.5 text-xs">
                        <span><span class="font-bold">{{ $linkRequest->user->name }}</span> ({{ $linkRequest->user->email }})</span>
                        <span class="text-black/70">claims the guest record <span class="font-bold">{{ $linkRequest->player->name }}</span></span>
                        <span class="text-[11px] text-black/60">
                            {{ $linkRequest->player->applicationPlayers->count() }} entries · {{ $linkRequest->player->statistic?->matches_played ?? 0 }} matches
                            @if ($entries) · {{ $entries }} @endif
                        </span>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" wire:click="openRejectLinkRequest({{ $linkRequest->id }})" class="cursor-pointer rounded-[5px] border border-[#bd6b6c] px-4 py-2 text-xs font-medium text-[#bd6b6c]">Reject</button>
                        <button type="button"
                                x-on:click="$dispatch('system-confirm', { title: 'Link these records?', message: @js('Merge '.$linkRequest->player->name.' into '.$linkRequest->user->email.'? This can\'t be undone.'), confirmLabel: 'Link', confirm: () => $wire.approveLinkRequest({{ $linkRequest->id }}) })"
                                class="cursor-pointer rounded-[5px] bg-[#3e948d] px-4 py-2 text-xs font-medium text-white">Approve</button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($this->duplicatePairs->isNotEmpty() || $this->unusedGuestCount > 0)
        <div class="flex flex-col gap-3 rounded-[10px] bg-white px-4 py-4 text-black sm:px-[19px]">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-bold">Player records</span>
                    @if ($this->duplicatePairs->isNotEmpty())
                        <span class="rounded-full bg-[#fff1d6] px-2 py-0.5 text-[10px] font-bold text-[#8a5a00]">{{ $this->duplicatePairs->count() }} possible duplicate(s)</span>
                    @endif
                </div>

                @if ($this->unusedGuestCount > 0)
                    <button type="button"
                            x-on:click="$dispatch('system-confirm', { title: 'Delete unused guest records?', message: @js($this->unusedGuestCount.' guest record(s) with no account, no entries and no link requests (older than 7 days) will be deleted. Records with any history are never touched.'), confirmLabel: 'Delete', confirm: () => $wire.pruneUnusedGuests() })"
                            class="cursor-pointer rounded-[5px] border border-[#bd6b6c] px-4 py-2 text-xs font-medium text-[#bd6b6c]">
                        Delete unused guest records ({{ $this->unusedGuestCount }})
                    </button>
                @endif
            </div>

            @foreach ($this->duplicatePairs as $pair)
                <div class="flex flex-col gap-3 border-t border-[#ececec] pt-3" wire:key="dup-{{ $pair['a']->id }}-{{ $pair['b']->id }}">
                    <div class="flex flex-wrap items-center gap-2 text-[10px] font-bold text-[#8a5a00]">
                        @foreach ($pair['reasons'] as $reason)
                            <span class="rounded-full bg-[#fff1d6] px-2 py-0.5">{{ $reason }}</span>
                        @endforeach
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ([$pair['a'], $pair['b']] as $record)
                            <div class="flex flex-col gap-0.5 rounded-[5px] border border-[#d3d3d3] px-3 py-2 text-xs">
                                <span class="font-bold">{{ $record->name }}@if ($record->user_id) <span class="font-normal text-[#138177]">(has account)</span>@endif</span>
                                <span class="text-black/70">{{ $record->email ?? 'No email' }} · {{ $record->phone_number ?? 'No phone' }}</span>
                                <span class="text-black/70">{{ $record->birth_date?->format('M j, Y') ?? 'No birthdate' }} · {{ $record->application_players_count }} entries</span>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="dismissDuplicate({{ $pair['a']->id }}, {{ $pair['b']->id }})" class="cursor-pointer rounded-[5px] border border-[#a3a3a3] px-4 py-2 text-xs font-medium text-[#6b6b6b]">Not the same person</button>
                        <button type="button"
                                x-on:click="$dispatch('system-confirm', { title: 'Merge these records?', message: @js('Merge '.$pair['a']->name.' and '.$pair['b']->name.' into one player? Their entries and history are combined. This can\'t be undone.'), confirmLabel: 'Merge', confirm: () => $wire.mergeDuplicates({{ $pair['a']->id }}, {{ $pair['b']->id }}) })"
                                class="cursor-pointer rounded-[5px] bg-[#3e948d] px-4 py-2 text-xs font-medium text-white">Merge</button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="flex w-full flex-col rounded-[10px] bg-white px-4 py-3 sm:px-[19px]">
      {{-- Roughly seven rows tall; everything beyond that scrolls. --}}
      <div
          class="max-h-[480px] overflow-y-auto [scrollbar-width:thin]"
          x-data="{ scrolling: false, timer: null }"
          x-on:scroll.passive="scrolling = true; clearTimeout(timer); timer = setTimeout(() => scrolling = false, 800)"
          x-bind:style="{ scrollbarColor: scrolling ? 'rgba(0, 0, 0, 0.35) transparent' : 'transparent transparent' }"
      >
        <div class="sticky top-0 z-10 hidden items-center gap-3 bg-white px-3 pb-2 text-[10px] text-black/60 md:flex">
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

                {{-- Fixed positioning so the menu isn't clipped by the scrolling list. --}}
                <div class="w-6" x-data="{ open: false, top: 0, right: 0 }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false" x-on:scroll.window.capture="open = false">
                    <button
                        type="button"
                        x-on:click="const rect = $el.getBoundingClientRect(); top = rect.bottom + 4; right = window.innerWidth - rect.right; open = !open"
                        class="flex size-6 cursor-pointer items-center justify-center text-black"
                        aria-label="Actions for {{ $user->name }}"
                    >
                        <flux:icon.ellipsis-vertical class="size-4" />
                    </button>

                    <div x-show="open" x-cloak x-bind:style="`top: ${top}px; right: ${right}px`" class="fixed z-50 flex w-36 flex-col rounded-[5px] bg-white p-1 text-xs text-black shadow-lg ring-1 ring-black/10">
                        <button type="button" wire:click="openEditModal({{ $user->id }})" x-on:click="open = false" class="flex cursor-pointer items-center gap-2 rounded-[5px] px-3 py-2 text-left hover:bg-[#e7f0ef]">
                            <flux:icon.pencil-square class="size-4" />
                            Edit
                        </button>
                        @if ($user->role === 'player')
                            <button type="button" wire:click="openLinkModal({{ $user->id }})" x-on:click="open = false" class="flex cursor-pointer items-center gap-2 rounded-[5px] px-3 py-2 text-left hover:bg-[#e7f0ef]">
                                <flux:icon.link class="size-4" />
                                Link player record
                            </button>
                        @endif
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
      </div>
    </div>

    <flux:modal wire:model.self="showLinkModal" class="w-full max-w-lg dark:bg-white! dark:ring-black/5!">
        <div class="flex flex-col gap-4 text-black">
            <div>
                <h2 class="text-base font-bold">Link player record</h2>
                <p class="text-sm text-black/70">Search a guest player (no account) by name or phone and attach their history to this account.</p>
            </div>

            <input type="search" wire:model.live.debounce.300ms="playerSearch" placeholder="Search guest players" class="rounded-[5px] border border-black/20 px-2 py-1.5 text-xs">

            <div class="flex max-h-64 flex-col gap-2 overflow-y-auto">
                @forelse ($this->linkablePlayers as $player)
                    <div class="flex items-center justify-between gap-3 rounded-[5px] border border-[#d3d3d3] px-3 py-2" wire:key="linkable-{{ $player->id }}">
                        <span class="flex flex-col text-xs">
                            <span class="font-bold">{{ $player->name }}</span>
                            <span class="text-[11px] text-black/60">{{ $player->phone_number ?? 'No phone' }}</span>
                        </span>
                        <button type="button"
                                x-on:click="$dispatch('system-confirm', { title: 'Link this record?', message: @js('Merge '.$player->name.' into this account? This can\'t be undone.'), confirmLabel: 'Link', confirm: () => $wire.linkPlayer({{ $player->id }}) })"
                                class="cursor-pointer rounded-[5px] bg-[#3e948d] px-3 py-1.5 text-xs font-medium text-white">Link</button>
                    </div>
                @empty
                    <p class="py-4 text-center text-xs text-black/50">{{ mb_strlen(trim($playerSearch)) < 2 ? 'Type at least 2 characters to search.' : 'No guest players found.' }}</p>
                @endforelse
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model.self="showRejectLinkModal" class="w-full max-w-md dark:bg-white! dark:ring-black/5!">
        <div class="flex flex-col gap-4 text-black">
            <h2 class="text-base font-bold">Reject link request</h2>
            <label class="flex flex-col gap-1 text-[11px]">
                Reason (optional, emailed to the player)
                <textarea wire:model="linkRejectionReason" rows="3" maxlength="500" class="rounded-[5px] border border-black/20 px-2 py-1.5 text-xs"></textarea>
                @error('linkRejectionReason') <span class="text-[10px] text-red-600">{{ $message }}</span> @enderror
            </label>
            <div class="flex justify-end gap-2">
                <button type="button" wire:click="$set('showRejectLinkModal', false)" class="cursor-pointer rounded-[5px] border border-[#a3a3a3] px-4 py-2 text-xs font-medium text-[#6b6b6b]">Cancel</button>
                <button type="button" wire:click="rejectLinkRequest" class="cursor-pointer rounded-[5px] bg-[#bd6b6c] px-4 py-2 text-xs font-medium text-white">Reject request</button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model.self="showFormModal" class="w-full max-w-2xl dark:bg-white! dark:ring-black/5!">
        <form wire:submit="save" class="flex max-h-[80vh] flex-col gap-5 text-black">
            <div class="flex flex-col gap-1">
                <h2 class="text-base font-bold">{{ $editingUserId ? 'Edit User' : 'Add User' }}</h2>
                <p class="text-sm text-black/70">
                    {{ $editingUserId ? 'Update this account. Leave the password blank to keep the current one.' : 'The same details as sign-up. Accounts added here are verified automatically and can sign in right away.' }}
                </p>
            </div>

            <div class="flex min-h-0 flex-col gap-3 overflow-y-auto pr-1">
                <label class="flex flex-col gap-1 text-[11px]">
                    Role
                    <select wire:model="role" class="rounded-[5px] border border-black/20 px-2 py-1.5 text-xs">
                        <option value="admin">Admin</option>
                        <option value="umpire">Umpire</option>
                        <option value="player">Player</option>
                    </select>
                    @error('role') <span class="text-[10px] text-red-600">{{ $message }}</span> @enderror
                </label>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <label class="flex flex-col gap-1 text-[11px]">
                        First name
                        <input type="text" wire:model="firstName" autocomplete="off" placeholder="e.g. Juan" class="rounded-[5px] border border-black/20 px-2 py-1.5 text-xs">
                        @error('firstName') <span class="text-[10px] text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="flex flex-col gap-1 text-[11px]">
                        Last name
                        <input type="text" wire:model="lastName" autocomplete="off" placeholder="e.g. Cruz" class="rounded-[5px] border border-black/20 px-2 py-1.5 text-xs">
                        @error('lastName') <span class="text-[10px] text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="flex flex-col gap-1 text-[11px]">
                        M.I
                        <input type="text" wire:model="middleInitial" autocomplete="off" placeholder="e.g. D" class="rounded-[5px] border border-black/20 px-2 py-1.5 text-xs">
                        @error('middleInitial') <span class="text-[10px] text-red-600">{{ $message }}</span> @enderror
                    </label>
                </div>

                <label class="flex flex-col gap-1 text-[11px]">
                    Email
                    <input type="email" wire:model="email" autocomplete="off" class="rounded-[5px] border border-black/20 px-2 py-1.5 text-xs">
                    @error('email') <span class="text-[10px] text-red-600">{{ $message }}</span> @enderror
                </label>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <label class="flex flex-col gap-1 text-[11px]">
                        {{ $editingUserId ? 'New password (optional)' : 'Password' }}
                        <input type="password" wire:model="password" autocomplete="new-password" class="rounded-[5px] border border-black/20 px-2 py-1.5 text-xs">
                        @error('password') <span class="text-[10px] text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="flex flex-col gap-1 text-[11px]">
                        Confirm password
                        <input type="password" wire:model="password_confirmation" autocomplete="new-password" class="rounded-[5px] border border-black/20 px-2 py-1.5 text-xs">
                    </label>
                </div>
                <p class="text-[10px] text-black/60">{{ \App\Rules\AccountPassword::HINT }}</p>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <label class="flex flex-col gap-1 text-[11px]">
                        Birthdate
                        <input type="date" wire:model="birthDate" class="rounded-[5px] border border-black/20 px-2 py-1.5 text-xs">
                        @error('birthDate') <span class="text-[10px] text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="flex flex-col gap-1 text-[11px]">
                        Gender
                        <select wire:model="gender" class="rounded-[5px] border border-black/20 px-2 py-1.5 text-xs">
                            <option value="" hidden>Select gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </select>
                        @error('gender') <span class="text-[10px] text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="flex flex-col gap-1 text-[11px]">
                        Phone number
                        <input type="text" wire:model="phoneNumber" placeholder="e.g. 09xxxxxxxxx" class="rounded-[5px] border border-black/20 px-2 py-1.5 text-xs">
                        @error('phoneNumber') <span class="text-[10px] text-red-600">{{ $message }}</span> @enderror
                    </label>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <label class="flex flex-col gap-1 text-[11px]">
                        Purok / street
                        <input type="text" wire:model="purok" placeholder="e.g. Purok 5" class="rounded-[5px] border border-black/20 px-2 py-1.5 text-xs">
                        @error('purok') <span class="text-[10px] text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="flex flex-col gap-1 text-[11px]">
                        City
                        <input type="text" wire:model="city" placeholder="e.g. Ozamiz City" class="rounded-[5px] border border-black/20 px-2 py-1.5 text-xs">
                        @error('city') <span class="text-[10px] text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="flex flex-col gap-1 text-[11px]">
                        Province
                        <input type="text" wire:model="province" placeholder="e.g. Misamis Occidental" class="rounded-[5px] border border-black/20 px-2 py-1.5 text-xs">
                        @error('province') <span class="text-[10px] text-red-600">{{ $message }}</span> @enderror
                    </label>
                </div>

                <label class="flex flex-col gap-1 text-[11px]">
                    Club (optional)
                    <input type="text" wire:model="club" placeholder="e.g. OBC Badminton Club" class="rounded-[5px] border border-black/20 px-2 py-1.5 text-xs">
                    @error('club') <span class="text-[10px] text-red-600">{{ $message }}</span> @enderror
                </label>

                <div class="flex flex-col gap-1 text-[11px]">
                    Profile photo (optional)
                    <input type="file" wire:model="profilePhoto" accept="image/*" class="rounded-[5px] border border-black/20 px-2 py-1.5 text-xs">
                    @error('profilePhoto') <span class="text-[10px] text-red-600">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <button type="button" wire:click="$set('showFormModal', false)" class="cursor-pointer rounded-[5px] border border-[#a3a3a3] px-4 py-2 text-xs font-medium text-[#6b6b6b]">
                    Cancel
                </button>
                <button type="submit" wire:loading.attr="disabled" wire:target="save,profilePhoto" class="cursor-pointer rounded-[5px] bg-[#3e948d] px-4 py-2 text-xs font-medium text-white disabled:opacity-60">
                    {{ $editingUserId ? 'Save Changes' : 'Add User' }}
                </button>
            </div>
        </form>
    </flux:modal>
</div>
