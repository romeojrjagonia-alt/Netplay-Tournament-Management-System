<?php

use App\Models\Category;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.admin', ['title' => 'Create Categories'])] class extends Component {
    use WithPagination;

    public const PER_PAGE = 10;

    public string $categoryType = '';

    public string $level = '';

    public string $ageMin = '';

    public string $ageMax = '';

    public ?int $editingId = null;

    public bool $showDuplicateWarning = false;

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
    }

    /**
     * Create the category, or save changes to the one being edited. An
     * identical category already in the list triggers a warning modal
     * instead of a duplicate row.
     */
    public function save(): void
    {
        $data = $this->validate([
            'categoryType' => ['required', 'in:'.implode(',', array_keys(Category::GENDER_TYPES))],
            'level' => ['required', 'in:'.Category::ANY_LEVEL.','.implode(',', Category::LEVELS)],
            'ageMin' => ['nullable', 'integer', 'min:0', 'max:120'],
            'ageMax' => ['nullable', 'integer', 'min:0', 'max:120', 'gte:ageMin'],
        ]);

        $type = Category::GENDER_TYPES[$data['categoryType']];

        $attributes = [
            'name' => $type['name'],
            'gender' => $type['gender'],
            'team_size' => $type['team_size'],
            'level' => $data['level'] === Category::ANY_LEVEL ? null : $data['level'],
            'age_min' => $data['ageMin'] !== '' ? (int) $data['ageMin'] : null,
            'age_max' => $data['ageMax'] !== '' ? (int) $data['ageMax'] : null,
        ];

        $category = $this->editingId ? Category::findOrFail($this->editingId) : null;

        if ($category?->isLocked()) {
            $this->addError('categoryType', 'This category is used by a published event and can no longer be edited.');

            return;
        }

        $isDuplicate = Category::query()
            ->matching($attributes)
            ->when($category, fn ($query) => $query->whereKeyNot($category->id))
            ->exists();

        if ($isDuplicate) {
            $this->showDuplicateWarning = true;

            return;
        }

        $category ? $category->update($attributes) : Category::create($attributes);

        $this->cancelEdit();
    }

    public function edit(int $categoryId): void
    {
        $category = Category::findOrFail($categoryId);

        if ($category->isLocked()) {
            return;
        }

        $this->resetValidation();
        $this->editingId = $category->id;
        $this->categoryType = $category->gender_type_key ?? '';
        $this->level = $category->level ?? Category::ANY_LEVEL;
        $this->ageMin = (string) ($category->age_min ?? '');
        $this->ageMax = (string) ($category->age_max ?? '');
    }

    public function cancelEdit(): void
    {
        $this->reset(['categoryType', 'level', 'ageMin', 'ageMax', 'editingId']);
        $this->resetValidation();

        unset($this->categories);
    }

    /**
     * A category can only be deleted while no event — draft or published —
     * uses it.
     */
    public function delete(int $categoryId): void
    {
        $category = Category::findOrFail($categoryId);

        if ($category->tournamentCategories()->exists()) {
            return;
        }

        $category->delete();

        if ($this->editingId === $categoryId) {
            $this->cancelEdit();
        }

        unset($this->categories);

        // Deleting the last row of the last page would leave an empty page.
        if ($this->categories->isEmpty() && $this->getPage() > 1) {
            $this->previousPage();
            unset($this->categories);
        }
    }

    /**
     * @return LengthAwarePaginator<int, Category>
     */
    #[Computed]
    public function categories(): LengthAwarePaginator
    {
        return Category::query()
            ->withCount('tournamentCategories')
            ->withExists(['tournamentCategories as is_in_published_event' => fn ($query) => $query->whereHas('tournament', fn ($tournament) => $tournament->published())])
            ->orderBy('name')
            ->orderBy('level')
            ->orderBy('id')
            ->paginate(self::PER_PAGE);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="border-b border-white/50 pb-4">
        <h2 class="text-2xl font-bold text-white">Create Categories</h2>
        <p class="mt-1 text-xs text-white/80">Design categories once, then add them to any event. Registration fee and maximum players are set per event.</p>
    </div>

    <div class="flex w-full flex-col gap-5 rounded-[10px] bg-white px-4 py-5 sm:px-[35px] sm:py-[23px]">
        <span class="text-xs font-bold text-black">{{ $editingId ? 'Edit Category' : 'New Category' }}</span>

        <div class="flex flex-wrap items-end gap-3">
            <x-player.registration-select label="Gender" model="categoryType" :options="collect(\App\Models\Category::GENDER_TYPES)->map(fn ($type) => $type['name'])->all()" class="min-w-[160px] flex-1" />
            <x-player.registration-select label="Player Level" model="level" :options="[\App\Models\Category::ANY_LEVEL => 'Any'] + array_combine(\App\Models\Category::LEVELS, \App\Models\Category::LEVELS)" class="min-w-[160px] flex-1" />
            <div class="flex items-end gap-1">
                <x-player.registration-field label="Age Min" model="ageMin" type="number" placeholder="Any" class="w-20" />
                <span class="pb-2">-</span>
                <x-player.registration-field label="Age Max" model="ageMax" type="number" placeholder="Any" class="w-20" />
            </div>

            <div class="flex shrink-0 gap-2">
                @if ($editingId)
                    <button type="button" wire:click="cancelEdit" class="cursor-pointer rounded-[10px] border border-[#a3a3a3] bg-white px-[24px] py-[10px] text-xs font-medium text-[#a3a3a3]">
                        Cancel
                    </button>
                @endif
                <button type="button" wire:click="save" class="flex cursor-pointer items-center gap-1.5 rounded-[10px] bg-[#3e948d] px-[30px] py-[10px] text-xs font-medium text-white">
                    @if ($editingId)
                        Save Changes
                    @else
                        <flux:icon.plus class="size-4" />
                        Add Category
                    @endif
                </button>
            </div>
        </div>

        <p class="text-[10px] text-black/60">Leave Age empty for a category that is open to all ages.</p>
    </div>

    <div class="flex w-full flex-col gap-2.5 rounded-[10px] bg-white px-4 py-5 sm:px-[35px] sm:py-[23px]">
        <span class="text-xs font-bold text-black">Categories ({{ $this->categories->total() }})</span>

        <div class="overflow-x-auto">
            <div class="flex min-w-[560px] flex-col">
                <div class="flex gap-1.5 border-b border-[#8cc7c1] bg-[#e7f0ef]">
                    <span class="flex-1 p-2.5 text-center text-xs text-black">Category</span>
                    <span class="flex-1 p-2.5 text-center text-xs text-black">Player Level</span>
                    <span class="flex-1 p-2.5 text-center text-xs text-black">Age</span>
                    <span class="w-24 p-2.5 text-center text-xs text-black">Used in</span>
                    <span class="w-20"></span>
                </div>

                @forelse ($this->categories as $category)
                    <div class="flex items-center gap-1.5 border-b border-[#ddefed] bg-white" wire:key="category-{{ $category->id }}">
                        <span class="flex-1 p-2.5 text-center text-xs text-black">{{ $category->name }}</span>
                        <span class="flex-1 p-2.5 text-center text-xs text-black">{{ $category->level ?? 'Any' }}</span>
                        <span class="flex-1 p-2.5 text-center text-xs text-black">{{ $category->age_label }}</span>
                        <span class="w-24 p-2.5 text-center text-xs text-black">{{ $category->tournament_categories_count }} {{ str('event')->plural($category->tournament_categories_count) }}</span>
                        <div class="flex w-20 items-center justify-center gap-2">
                            @if ($category->is_in_published_event)
                                <span class="text-[10px] text-black/50" title="Used by a published event">Locked</span>
                            @else
                                <button type="button" wire:click="edit({{ $category->id }})" class="cursor-pointer text-black" aria-label="Edit category">
                                    <flux:icon.pencil-square class="size-4" />
                                </button>
                                @if ($category->tournament_categories_count === 0)
                                    <button type="button" x-on:click="$dispatch('system-confirm', { title: 'Delete category?', message: @js('Delete '.$category->name.($category->level ? ' ('.$category->level.')' : '').'? This can\'t be undone.'), variant: 'danger', confirmLabel: 'Delete', confirm: () => $wire.delete({{ $category->id }}) })" class="cursor-pointer text-[#bd6b6c]" aria-label="Delete category">
                                        <flux:icon.trash class="size-4" />
                                    </button>
                                @endif
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-xs text-black/50">No categories created yet.</p>
                @endforelse
            </div>
        </div>

        @if ($this->categories->hasPages())
            <div class="pt-2">{{ $this->categories->links() }}</div>
        @endif
    </div>

    <flux:modal wire:model.self="showDuplicateWarning" class="max-w-md">
        <div class="flex flex-col gap-5">
            <div class="flex flex-col gap-2">
                <flux:heading size="lg">Duplicate category</flux:heading>
                <flux:text>A category with the same gender, player level and age already exists. It was not saved again.</flux:text>
            </div>

            <div class="flex justify-end">
                <flux:button variant="primary" wire:click="$set('showDuplicateWarning', false)">OK</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
