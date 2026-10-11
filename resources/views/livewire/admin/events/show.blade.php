<?php

use App\Models\Category;
use App\Models\PaymentDetail;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.admin', ['title' => 'Event Tournaments'])] class extends Component {
    public Tournament $tournament;

    public bool $showCategoryModal = false;

    /** @var array<int, string> Fee inputs for categories not yet added, keyed by category id. */
    public array $newFee = [];

    /** @var array<int, string> Max-player inputs for categories not yet added, keyed by category id. */
    public array $newSlots = [];

    /** @var array<int, string> Fee inputs for added categories, keyed by tournament category id. */
    public array $editFee = [];

    /** @var array<int, string> Max-player inputs for added categories, keyed by tournament category id. */
    public array $editSlots = [];

    public function mount(Tournament $tournament): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $this->tournament = $tournament;

        if (! $tournament->is_published && request()->query('manage') === 'categories') {
            $this->openCategoryModal();
        }
    }

    public function openCategoryModal(): void
    {
        abort_if($this->tournament->is_published, 403);

        $this->syncCategoryInputs();
        $this->showCategoryModal = true;
    }

    /**
     * Add a library category to this draft event with its fee and player cap.
     */
    public function addCategory(int $categoryId): void
    {
        abort_if($this->tournament->is_published, 403);

        $this->resetValidation();

        $validator = Validator::make(
            ['fee' => $this->newFee[$categoryId] ?? '', 'slots' => $this->newSlots[$categoryId] ?? ''],
            ['fee' => ['required', 'numeric', 'min:0'], 'slots' => ['required', 'integer', 'min:1']],
        );

        if ($validator->fails()) {
            $this->addRowErrors('new', $categoryId, $validator);

            return;
        }

        $data = $validator->validated();
        $category = Category::findOrFail($categoryId);

        $this->tournament->tournamentCategories()->firstOrCreate(
            ['category_id' => $category->id],
            ['registration_fee' => $data['fee'], 'max_slots' => $data['slots'], 'status' => 'open'],
        );

        unset($this->categories, $this->availableCategories);
        $this->syncCategoryInputs();
    }

    /**
     * Save the edited fee and player cap of an added category.
     */
    public function saveCategoryTerms(int $tournamentCategoryId): void
    {
        abort_if($this->tournament->is_published, 403);

        $this->resetValidation();

        $tournamentCategory = $this->tournament->tournamentCategories()->findOrFail($tournamentCategoryId);

        $validator = Validator::make(
            ['fee' => $this->editFee[$tournamentCategoryId] ?? '', 'slots' => $this->editSlots[$tournamentCategoryId] ?? ''],
            ['fee' => ['required', 'numeric', 'min:0'], 'slots' => ['required', 'integer', 'min:1']],
        );

        if ($validator->fails()) {
            $this->addRowErrors('edit', $tournamentCategoryId, $validator);

            return;
        }

        $data = $validator->validated();

        $tournamentCategory->update(['registration_fee' => $data['fee'], 'max_slots' => $data['slots']]);

        unset($this->categories);
    }

    public function removeCategory(int $tournamentCategoryId): void
    {
        abort_if($this->tournament->is_published, 403);

        $this->tournament->tournamentCategories()
            ->whereDoesntHave('playerApplications')
            ->where('id', $tournamentCategoryId)
            ->delete();

        unset($this->categories, $this->availableCategories);
        $this->syncCategoryInputs();
    }

    /**
     * Library categories not yet added to this event.
     *
     * @return Collection<int, Category>
     */
    #[Computed]
    public function availableCategories(): Collection
    {
        return Category::query()
            ->whereNotIn('id', $this->tournament->tournamentCategories()->select('category_id'))
            ->orderBy('name')
            ->orderBy('level')
            ->get();
    }

    /**
     * Seed the added-category inputs from the database, dropping stale keys.
     */
    private function syncCategoryInputs(): void
    {
        $added = $this->tournament->tournamentCategories()->get();

        $this->editFee = $added->mapWithKeys(fn ($tc) => [$tc->id => (string) $tc->registration_fee])->all();
        $this->editSlots = $added->mapWithKeys(fn ($tc) => [$tc->id => (string) $tc->max_slots])->all();
        $this->newFee = array_intersect_key($this->newFee, $this->availableCategories->keyBy('id')->all());
        $this->newSlots = array_intersect_key($this->newSlots, $this->availableCategories->keyBy('id')->all());
    }

    private function addRowErrors(string $prefix, int $id, \Illuminate\Validation\Validator $validator): void
    {
        foreach ($validator->errors()->messages() as $field => $messages) {
            $this->addError($prefix.ucfirst($field).'.'.$id, $messages[0]);
        }
    }

    /**
     * Publishing requires at least one category — categories can no longer
     * be added once published, so an admin must add them before publishing
     * (they may leave a draft empty and add categories later).
     */
    public function togglePublish(): void
    {
        if (! $this->tournament->is_published && $this->tournament->tournamentCategories()->doesntExist()) {
            $this->dispatch('system-message', title: 'Cannot publish yet', message: 'Add at least one category before publishing this event.', variant: 'danger');

            return;
        }

        $this->tournament->update(['status' => $this->tournament->is_published ? 'draft' : 'published']);
    }

    /**
     * @return Collection<int, \App\Models\TournamentCategory>
     */
    #[Computed]
    public function categories(): Collection
    {
        return $this->tournament->tournamentCategories()
            ->with(['category', 'playerApplications' => fn ($query) => $query->where('status', 'approved')->with('applicationPlayers')])
            ->get();
    }

    /**
     * @return array{categories: int, pendingApplications: int, pendingPayments: int, registeredPlayers: int}
     */
    #[Computed]
    public function totals(): array
    {
        $tournamentCategoryIds = $this->categories->pluck('id');

        return [
            'categories' => $tournamentCategoryIds->count(),
            'pendingApplications' => PlayerApplication::whereIn('tournament_category_id', $tournamentCategoryIds)
                ->where('status', 'pending_verification')
                ->count(),
            'pendingPayments' => PaymentDetail::where('payment_status', 'pending')
                ->whereHas('application', fn ($query) => $query->whereIn('tournament_category_id', $tournamentCategoryIds))
                ->count(),
            'registeredPlayers' => $this->categories->flatMap->playerApplications->flatMap->applicationPlayers->count(),
        ];
    }

    public function formatDateRange(): string
    {
        if ($this->tournament->date_start->isSameMonth($this->tournament->date_end)) {
            return $this->tournament->date_start->format('F j').' - '.$this->tournament->date_end->format('j, Y');
        }

        return $this->tournament->date_start->format('F j, Y').' - '.$this->tournament->date_end->format('F j, Y');
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-col gap-4 border-b border-white/50 pb-4">
        <div class="flex flex-wrap items-center gap-3 sm:gap-5">
            <h2 class="min-w-0 flex-1 basis-full text-2xl font-bold text-white sm:basis-0">{{ $tournament->name }}</h2>

            @unless ($tournament->is_published)
                <a href="{{ route('admin.events.edit', $tournament) }}" wire:navigate class="rounded-full border border-[#138177] bg-white px-4 py-2 text-xs text-black">
                    Edit Event
                </a>

                <button type="button" wire:click="openCategoryModal" class="cursor-pointer rounded-full border border-[#138177] bg-white px-4 py-2 text-xs text-black">
                    {{ $this->categories->isEmpty() ? 'Add Categories' : 'Manage Categories' }}
                </button>
            @endunless

            @if ($tournament->is_published && $tournament->registration_end->endOfDay()->isFuture())
                <a href="{{ route('tournaments.register', $tournament) }}" class="rounded-full border border-[#138177] bg-white px-4 py-2 text-xs text-black">
                    Register Walk-in
                </a>
            @endif

            <button type="button" wire:click="togglePublish" class="cursor-pointer rounded-full border border-[#138177] bg-white px-4 py-2 text-xs text-black">
                {{ $tournament->is_published ? 'Unpublish Event' : 'Publish Event' }}
            </button>
        </div>


        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-8">
                <span class="flex items-center gap-2 text-xs font-bold text-white">
                    <flux:icon.calendar class="size-[18px] shrink-0" />
                    {{ $this->formatDateRange() }}
                </span>

                @if ($tournament->time)
                    <span class="flex items-center gap-2 text-xs font-bold text-white">
                        <flux:icon.clock class="size-[18px] shrink-0" />
                        {{ $tournament->time }}
                    </span>
                @endif

                @if ($tournament->specific_days)
                    <div class="flex gap-1">
                        @foreach (explode(',', $tournament->specific_days) as $day)
                            <span class="rounded-[5px] bg-[#9cee69] px-2.5 py-0.5 text-[10px] font-bold text-[#051c1a]">{{ trim($day) }}</span>
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($tournament->is_published && ! $tournament->is_concluded)
                <span class="rounded-[15px] bg-[#096] px-4 py-1.5 text-[10px] font-bold text-white">
                    Accepting Registration till {{ $tournament->registration_end->format('M. j, Y') }}
                </span>
            @else
                <span class="rounded-[15px] bg-white/20 px-4 py-1.5 text-[10px] font-bold text-white">
                    {{ $tournament->lifecycle_label }}
                </span>
            @endif
        </div>

        <x-admin.event-tabs :tournament="$tournament" active="dashboard" />
    </div>

    <div class="flex flex-wrap items-center gap-4 sm:gap-[30px]">
        <div class="relative flex h-[67px] items-center gap-1.5 overflow-hidden rounded-[10px] px-5 sm:px-[30px]">
            <div class="absolute inset-0 bg-white mix-blend-soft-light"></div>
            <span class="relative text-[10px] text-white">NO. OF CATEGORIES</span>
            <span class="relative text-[33px] font-bold text-white">{{ $this->totals['categories'] }}</span>
        </div>
        <div class="relative flex h-[67px] items-center gap-1.5 overflow-hidden rounded-[10px] px-5 sm:px-[30px]">
            <div class="absolute inset-0 bg-white mix-blend-soft-light"></div>
            <span class="relative text-[10px] text-white">PENDING APPLICATIONS</span>
            <span class="relative text-[33px] font-bold text-white">{{ $this->totals['pendingApplications'] }}</span>
        </div>
        <div class="relative flex h-[67px] items-center gap-1.5 overflow-hidden rounded-[10px] px-5 sm:px-[30px]">
            <div class="absolute inset-0 bg-white mix-blend-soft-light"></div>
            <span class="relative text-[10px] text-white">PENDING PAYMENTS</span>
            <span class="relative text-[33px] font-bold text-white">{{ $this->totals['pendingPayments'] }}</span>
        </div>
        <div class="relative flex h-[67px] items-center gap-1.5 overflow-hidden rounded-[10px] px-5 sm:px-[30px]">
            <div class="absolute inset-0 bg-white mix-blend-soft-light"></div>
            <span class="relative text-[10px] text-white">REGISTERED PLAYERS</span>
            <span class="relative text-[33px] font-bold text-white">{{ $this->totals['registeredPlayers'] }}</span>
        </div>
    </div>

    <div class="flex flex-col gap-[17px]">
        @forelse ($this->categories as $tournamentCategory)
            @php
                $registered = $tournamentCategory->playerApplications->flatMap->applicationPlayers->count();
                $capacity = max($tournamentCategory->max_slots, 1);
                $percent = min(100, (int) round($registered / $capacity * 100));
            @endphp
            <div class="flex flex-wrap items-center gap-x-10 gap-y-3 rounded-[10px] bg-white px-5 py-3 sm:px-[30px] lg:gap-x-[100px] shadow-[0px_6px_2px_rgba(0,0,0,0.25)]">
                <span class="text-lg font-bold text-black">{{ $tournamentCategory->category->name }}</span>

                <div class="flex flex-col gap-1">
                    <span class="text-xs text-black">LEVEL</span>
                    <span class="text-xs font-bold text-black uppercase">{{ $tournamentCategory->category->level ?? 'Any' }}</span>
                </div>

                <div class="flex flex-col gap-1">
                    <span class="text-xs text-black">AGE RANGE</span>
                    <span class="text-xs font-bold text-black">
                        {{ $tournamentCategory->category->age_label }}
                    </span>
                </div>

                <div class="flex min-w-[180px] flex-1 flex-col gap-1">
                    <span class="text-xs text-black">{{ $registered }}/{{ $tournamentCategory->max_slots }} PLAYERS REGISTERED</span>
                    <div class="h-[9px] w-full overflow-hidden rounded-full bg-[#d9d9d9]">
                        <div class="h-full bg-[#9cee69]" style="width: {{ $percent }}%"></div>
                    </div>
                </div>
            </div>
        @empty
            <div class="flex min-h-[320px] flex-col items-center justify-center gap-6 rounded-[10px] bg-white">
                <p class="text-[22px] text-black/70 italic">No Categories Added Yet</p>

                @unless ($tournament->is_published)
                    <button type="button" wire:click="openCategoryModal" class="flex cursor-pointer items-center gap-1.5 rounded-[10px] bg-[#3e948d] px-[30px] py-[10px] text-xs font-medium text-white">
                        <flux:icon.plus class="size-4" />
                        Add Categories
                    </button>
                @endunless
            </div>
        @endforelse
    </div>

    @unless ($tournament->is_published)
        <flux:modal wire:model.self="showCategoryModal" class="w-full md:w-[760px] max-w-[95vw] dark:bg-white! dark:ring-black/5!">
            <div class="flex flex-col gap-5">
                <div class="flex flex-col gap-1">
                    <h2 class="text-base font-bold text-black">Event Categories</h2>
                    <p class="text-sm text-black/70">Add categories to "{{ $tournament->name }}" and set each one's registration fee and maximum players. Need a new category? <a href="{{ route('admin.categories.index') }}" wire:navigate class="font-medium underline">Create one here</a>.</p>
                </div>

                <div class="flex flex-col gap-2">
                    <span class="text-xs font-bold">Available categories ({{ $this->availableCategories->count() }})</span>
                    <div class="max-h-60 overflow-y-auto rounded-[5px] border border-[#ddefed]">
                        @forelse ($this->availableCategories as $category)
                            <div class="flex flex-wrap items-start gap-2 border-b border-[#ddefed] p-2.5 last:border-b-0" wire:key="available-{{ $category->id }}">
                                <div class="min-w-[160px] flex-1">
                                    <p class="text-xs font-bold">{{ $category->name }}</p>
                                    <p class="text-[11px] opacity-70">{{ $category->level ?? 'Any' }} · {{ $category->age_label }}</p>
                                </div>
                                <div class="flex flex-col gap-0.5">
                                    <input type="number" step="0.01" min="0" wire:model="newFee.{{ $category->id }}" placeholder="Fee (PHP)" aria-label="Registration fee" class="h-[30px] w-28 rounded-[5px] bg-[#eaf4f3] px-2 text-xs text-black placeholder:text-black/50">
                                    @error('newFee.'.$category->id) <p class="text-[10px] text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div class="flex flex-col gap-0.5">
                                    <input type="number" min="1" wire:model="newSlots.{{ $category->id }}" placeholder="Max players" aria-label="Maximum players" class="h-[30px] w-28 rounded-[5px] bg-[#eaf4f3] px-2 text-xs text-black placeholder:text-black/50">
                                    @error('newSlots.'.$category->id) <p class="text-[10px] text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <button type="button" wire:click="addCategory({{ $category->id }})" class="flex h-[30px] cursor-pointer items-center gap-1 rounded-[5px] bg-[#3e948d] px-3 text-xs font-medium text-white">
                                    <flux:icon.plus class="size-3.5" />
                                    Add
                                </button>
                            </div>
                        @empty
                            <p class="p-4 text-center text-xs opacity-60">No more categories to add.</p>
                        @endforelse
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <span class="text-xs font-bold">Added to this event ({{ $this->categories->count() }})</span>
                    <div class="max-h-60 overflow-y-auto rounded-[5px] border border-[#ddefed]">
                        @forelse ($this->categories as $tournamentCategory)
                            <div class="flex flex-wrap items-start gap-2 border-b border-[#ddefed] p-2.5 last:border-b-0" wire:key="added-{{ $tournamentCategory->id }}">
                                <div class="min-w-[160px] flex-1">
                                    <p class="text-xs font-bold">{{ $tournamentCategory->category->name }}</p>
                                    <p class="text-[11px] opacity-70">{{ $tournamentCategory->category->level ?? 'Any' }} · {{ $tournamentCategory->category->age_label }}</p>
                                </div>
                                <div class="flex flex-col gap-0.5">
                                    <input type="number" step="0.01" min="0" wire:model="editFee.{{ $tournamentCategory->id }}" wire:change="saveCategoryTerms({{ $tournamentCategory->id }})" aria-label="Registration fee" class="h-[30px] w-28 rounded-[5px] bg-[#eaf4f3] px-2 text-xs text-black">
                                    @error('editFee.'.$tournamentCategory->id) <p class="text-[10px] text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div class="flex flex-col gap-0.5">
                                    <input type="number" min="1" wire:model="editSlots.{{ $tournamentCategory->id }}" wire:change="saveCategoryTerms({{ $tournamentCategory->id }})" aria-label="Maximum players" class="h-[30px] w-28 rounded-[5px] bg-[#eaf4f3] px-2 text-xs text-black">
                                    @error('editSlots.'.$tournamentCategory->id) <p class="text-[10px] text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <button type="button" wire:click="removeCategory({{ $tournamentCategory->id }})" class="flex h-[30px] w-9 cursor-pointer items-center justify-center text-[#bd6b6c]" aria-label="Remove category">
                                    <flux:icon.trash class="size-4" />
                                </button>
                            </div>
                        @empty
                            <p class="p-4 text-center text-xs opacity-60">No categories added yet.</p>
                        @endforelse
                    </div>
                </div>

                <div class="flex justify-end">
                    <flux:button variant="primary" wire:click="$set('showCategoryModal', false)">Done</flux:button>
                </div>
            </div>
        </flux:modal>
    @endunless
</div>
