<?php

use App\Models\Matches;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.admin', ['title' => 'Match Officials'])] class extends Component {
    public Tournament $tournament;

    /** @var array<int, string> The umpire picked for each tournament category, keyed by tournament category id. */
    public array $umpireByCategory = [];

    public function mount(Tournament $tournament): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $this->tournament = $tournament;
    }

    /**
     * Put one umpire on every match in the category that hasn't been played
     * yet. Individual matches can still be changed on the schedule page.
     */
    public function assignToCategory(int $tournamentCategoryId): void
    {
        $tournamentCategory = $this->tournament->tournamentCategories()->with('category')->findOrFail($tournamentCategoryId);

        $umpireId = $this->umpireByCategory[$tournamentCategoryId] ?? '';

        $this->validate([
            "umpireByCategory.$tournamentCategoryId" => ['nullable', Rule::exists('users', 'id')->where('role', 'umpire')],
        ], [], ["umpireByCategory.$tournamentCategoryId" => 'umpire']);

        $updated = $tournamentCategory->matches()
            ->where('status', '!=', 'completed')
            ->update(['assigned_umpire_id' => $umpireId !== '' ? (int) $umpireId : null]);

        unset($this->categories, $this->umpires);

        if ($updated === 0) {
            $this->dispatch('system-message', title: 'Nothing to assign', message: 'There are no unplayed matches in this category yet. Generate its schedule first.', variant: 'danger');

            return;
        }

        $name = $umpireId !== '' ? User::find($umpireId)->name : null;

        $this->dispatch(
            'system-message',
            title: 'Officials updated',
            message: $name
                ? "{$name} is now the umpire for {$updated} ".str('match')->plural($updated)." in {$tournamentCategory->category_label}."
                : "Umpire cleared from {$updated} ".str('match')->plural($updated)." in {$tournamentCategory->category_label}.",
            variant: 'success',
        );
    }

    /**
     * @return Collection<int, \App\Models\TournamentCategory>
     */
    #[Computed]
    public function categories(): Collection
    {
        return $this->tournament->tournamentCategories()
            ->with('category')
            ->withCount([
                'matches',
                'matches as open_matches_count' => fn ($query) => $query->where('status', '!=', 'completed'),
                'matches as unassigned_matches_count' => fn ($query) => $query->where('status', '!=', 'completed')->whereNull('assigned_umpire_id'),
            ])
            ->orderBy('id')
            ->get();
    }

    /**
     * Every umpire account with their load in this event.
     *
     * @return Collection<int, array{user: User, assigned: int, completed: int}>
     */
    #[Computed]
    public function umpires(): Collection
    {
        $matches = Matches::query()
            ->whereNotNull('assigned_umpire_id')
            ->whereHas('tournamentCategory', fn ($query) => $query->where('tournament_id', $this->tournament->id))
            ->get(['assigned_umpire_id', 'status'])
            ->groupBy('assigned_umpire_id');

        return User::query()->where('role', 'umpire')->orderBy('name')->get()
            ->map(fn (User $user) => [
                'user' => $user,
                'assigned' => $matches->get($user->id)?->count() ?? 0,
                'completed' => $matches->get($user->id)?->where('status', 'completed')->count() ?? 0,
            ]);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-col gap-4 border-b border-white/50 pb-4">
        <h2 class="text-2xl font-bold text-white">{{ $tournament->name }}</h2>

        <x-admin.event-tabs :tournament="$tournament" active="officials" />
    </div>

    <div class="flex flex-col gap-3 rounded-[10px] bg-white px-4 py-5 sm:px-[35px] sm:py-[23px]">
        <div class="flex flex-col gap-1">
            <span class="text-lg font-bold text-black">Assign umpires by category</span>
            <p class="text-xs text-black/60">
                Pick an umpire to cover every unplayed match in a category. To use a different umpire for a single match,
                open the category on the <a href="{{ route('admin.events.schedule.index', $tournament) }}" wire:navigate class="font-medium underline">Schedule Matches</a> page.
            </p>
        </div>

        @if ($this->umpires->isEmpty())
            <p class="rounded-[5px] bg-[#eaf4f3] px-4 py-3 text-xs text-black">
                There are no umpire accounts yet.
                <a href="{{ route('admin.users.index') }}" wire:navigate class="font-bold underline">Create one on the Users page</a>.
            </p>
        @endif

        <div class="flex flex-col divide-y divide-black/10">
            @forelse ($this->categories as $tournamentCategory)
                <div class="flex flex-wrap items-center gap-3 py-3" wire:key="category-{{ $tournamentCategory->id }}">
                    <div class="flex min-w-[200px] flex-1 flex-col">
                        <span class="text-sm font-bold text-black">{{ $tournamentCategory->category_label }}</span>
                        <span class="text-[11px] text-black/60">
                            @if ($tournamentCategory->matches_count === 0)
                                No schedule yet
                            @else
                                {{ $tournamentCategory->open_matches_count }} unplayed {{ str('match')->plural($tournamentCategory->open_matches_count) }}
                                · {{ $tournamentCategory->unassigned_matches_count }} without an umpire
                            @endif
                        </span>
                    </div>

                    @if ($tournamentCategory->matches_count === 0)
                        <a href="{{ route('admin.events.schedule.show', [$tournament, $tournamentCategory]) }}" wire:navigate
                           class="text-xs font-medium text-[#3e948d] underline">Generate schedule</a>
                    @else
                        <select wire:model="umpireByCategory.{{ $tournamentCategory->id }}" aria-label="Umpire for {{ $tournamentCategory->category_label }}"
                                class="rounded-[5px] border border-black/20 px-2 py-1.5 text-xs text-black">
                            <option value="">No umpire</option>
                            @foreach ($this->umpires as $row)
                                <option value="{{ $row['user']->id }}">{{ $row['user']->name }}</option>
                            @endforeach
                        </select>

                        <button type="button" wire:click="assignToCategory({{ $tournamentCategory->id }})"
                                class="cursor-pointer rounded-[5px] bg-[#3e948d] px-4 py-1.5 text-xs font-medium text-white">
                            Assign to all unplayed matches
                        </button>
                    @endif

                    @error("umpireByCategory.{$tournamentCategory->id}")
                        <p class="basis-full text-[11px] text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @empty
                <p class="py-6 text-center text-xs text-black/50">This event has no categories yet.</p>
            @endforelse
        </div>
    </div>

    <div class="flex flex-col gap-2.5 rounded-[10px] bg-white px-4 py-5 sm:px-[35px] sm:py-[23px]">
        <span class="text-xs font-bold text-black">Umpires ({{ $this->umpires->count() }})</span>

        <div class="overflow-x-auto">
            <div class="flex min-w-[420px] flex-col">
                <div class="flex gap-1.5 border-b border-[#8cc7c1] bg-[#e7f0ef]">
                    <span class="flex-1 p-2.5 text-center text-xs text-black">Umpire</span>
                    <span class="w-28 p-2.5 text-center text-xs text-black">Assigned</span>
                    <span class="w-28 p-2.5 text-center text-xs text-black">Completed</span>
                </div>

                @forelse ($this->umpires as $row)
                    <div class="flex items-center gap-1.5 border-b border-[#ddefed]" wire:key="umpire-{{ $row['user']->id }}">
                        <span class="flex-1 p-2.5 text-center text-xs text-black">{{ $row['user']->name }}</span>
                        <span class="w-28 p-2.5 text-center text-xs text-black">{{ $row['assigned'] }}</span>
                        <span class="w-28 p-2.5 text-center text-xs text-black">{{ $row['completed'] }}</span>
                    </div>
                @empty
                    <p class="py-6 text-center text-xs text-black/50">No umpires yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
