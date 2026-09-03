<?php

use App\Models\Category;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.admin', ['title' => 'Create New Events'])] class extends Component {
    public Tournament $tournament;

    public string $scoringSystem = '';

    public string $categoryPreset = '';

    public string $level = '';

    public string $ageMin = '';

    public string $ageMax = '';

    public string $fee = '';

    public string $limit = '';

    /**
     * Standard category types — each preset bakes in the gender and team
     * size a "Men's Doubles"-style name implies, since neither is collected
     * separately in this form (both are required, not-null columns).
     *
     * @var array<string, array{name: string, gender: string, team_size: int}>
     */
    public array $presets = [
        'mens_singles' => ['name' => "Men's Singles", 'gender' => 'male', 'team_size' => 1],
        'womens_singles' => ['name' => "Women's Singles", 'gender' => 'female', 'team_size' => 1],
        'mens_doubles' => ['name' => "Men's Doubles", 'gender' => 'male', 'team_size' => 2],
        'womens_doubles' => ['name' => "Women's Doubles", 'gender' => 'female', 'team_size' => 2],
        'mixed_doubles' => ['name' => 'Mixed Doubles', 'gender' => 'mixed', 'team_size' => 2],
    ];

    public function mount(Tournament $tournament): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $this->tournament = $tournament;
        $this->scoringSystem = $tournament->scoring_system;
    }

    public function updatedScoringSystem(string $value): void
    {
        $this->tournament->update(['scoring_system' => $value]);
    }

    public function addCategory(): void
    {
        $data = $this->validate([
            'categoryPreset' => ['required', 'in:'.implode(',', array_keys($this->presets))],
            'level' => ['required', 'string', 'max:255'],
            'ageMin' => ['nullable', 'integer', 'min:0'],
            'ageMax' => ['nullable', 'integer', 'gte:ageMin'],
            'fee' => ['required', 'numeric', 'min:0'],
            'limit' => ['required', 'integer', 'min:1'],
        ]);

        $preset = $this->presets[$data['categoryPreset']];

        $category = Category::firstOrCreate([
            'name' => $preset['name'],
            'gender' => $preset['gender'],
            'team_size' => $preset['team_size'],
            'level' => $data['level'],
            'age_min' => $data['ageMin'] !== '' ? $data['ageMin'] : null,
            'age_max' => $data['ageMax'] !== '' ? $data['ageMax'] : null,
        ]);

        if ($this->tournament->tournamentCategories()->where('category_id', $category->id)->exists()) {
            $this->addError('categoryPreset', 'This category has already been added to the event.');

            return;
        }

        $this->tournament->tournamentCategories()->create([
            'category_id' => $category->id,
            'registration_fee' => $data['fee'],
            'max_slots' => $data['limit'],
            'status' => 'open',
        ]);

        $this->reset(['categoryPreset', 'level', 'ageMin', 'ageMax', 'fee', 'limit']);

        unset($this->categories);
    }

    public function removeCategory(int $tournamentCategoryId): void
    {
        $this->tournament->tournamentCategories()
            ->whereDoesntHave('playerApplications')
            ->where('id', $tournamentCategoryId)
            ->delete();

        unset($this->categories);
    }

    /**
     * @return Collection<int, TournamentCategory>
     */
    #[Computed]
    public function categories(): Collection
    {
        return $this->tournament->tournamentCategories()->with('category')->get();
    }

    public function back(): void
    {
        $this->redirect(route('admin.events.wizard.details', $this->tournament), navigate: true);
    }

    public function next(): void
    {
        if ($this->categories->isEmpty()) {
            $this->addError('categoryPreset', 'Add at least one category before continuing.');

            return;
        }

        $this->redirect(route('admin.events.wizard.review', $this->tournament), navigate: true);
    }
}; ?>

<div class="flex flex-col items-center gap-5">
    <x-admin.wizard-steps :current="2" />

    <div class="flex w-[814px] flex-col gap-5 rounded-[10px] bg-white px-[35px] py-[23px]">
        <div class="border-b border-[#138177] pb-4">
            <x-player.registration-field label="Pointing System" model="scoringSystem" placeholder="e.g. Best of 3 to 21" />
        </div>

        <div class="flex items-end gap-3">
            <x-player.registration-select label="Category" model="categoryPreset" :options="collect($presets)->map(fn ($p) => $p['name'])->all()" class="flex-1" />
            <x-player.registration-field label="Player Level" model="level" placeholder="e.g. Beginner" class="flex-1" />
            <div class="flex items-end gap-1">
                <x-player.registration-field label="Age Min" model="ageMin" type="number" placeholder="Min" class="w-16" />
                <span class="pb-2">-</span>
                <x-player.registration-field label="Age Max" model="ageMax" type="number" placeholder="Max" class="w-16" />
            </div>
            <x-player.registration-field label="Registration Fee" model="fee" type="number" placeholder="PHP 00.00" class="flex-1" />
            <x-player.registration-field label="Player Limit" model="limit" type="number" placeholder="Player Limit" class="flex-1" />
            <button type="button" wire:click="addCategory" class="flex shrink-0 items-center gap-1.5 rounded-[10px] bg-[#3e948d] px-[30px] py-[10px] text-xs font-medium text-white">
                <flux:icon.plus class="size-4" />
                Add Category
            </button>
        </div>

        <div class="flex flex-col gap-2.5">
            <span class="text-xs font-bold text-black">Categories Added ({{ $this->categories->count() }})</span>

            <div class="flex flex-col">
                <div class="flex gap-1.5 border-b border-[#8cc7c1] bg-[#e7f0ef]">
                    <span class="flex-1 p-2.5 text-center text-xs text-black">Category</span>
                    <span class="flex-1 p-2.5 text-center text-xs text-black">Player Level</span>
                    <span class="flex-1 p-2.5 text-center text-xs text-black">Age</span>
                    <span class="flex-1 p-2.5 text-center text-xs text-black">Registration Fee</span>
                    <span class="flex-1 p-2.5 text-center text-xs text-black">Limit</span>
                    <span class="w-10"></span>
                </div>

                @forelse ($this->categories as $tournamentCategory)
                    <div class="flex items-center gap-1.5 border-b border-[#ddefed] bg-white" wire:key="category-{{ $tournamentCategory->id }}">
                        <span class="flex-1 p-2.5 text-center text-xs text-black">{{ $tournamentCategory->category->name }}</span>
                        <span class="flex-1 p-2.5 text-center text-xs text-black">{{ $tournamentCategory->category->level ?? '—' }}</span>
                        <span class="flex-1 p-2.5 text-center text-xs text-black">{{ $tournamentCategory->category->age_min ?? '—' }} - {{ $tournamentCategory->category->age_max ?? '—' }}</span>
                        <span class="flex-1 p-2.5 text-center text-xs text-black">PHP {{ number_format((float) $tournamentCategory->registration_fee, 2) }}</span>
                        <span class="flex-1 p-2.5 text-center text-xs text-black">{{ $tournamentCategory->max_slots }}</span>
                        <button type="button" wire:click="removeCategory({{ $tournamentCategory->id }})" class="w-10 cursor-pointer text-center text-xs text-[#bd6b6c]" aria-label="Remove category">
                            <flux:icon.trash class="mx-auto size-4" />
                        </button>
                    </div>
                @empty
                    <p class="py-6 text-center text-xs text-black/50">No categories added yet.</p>
                @endforelse
            </div>
        </div>

        @error('categoryPreset') <p class="text-[10px] text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="flex w-[814px] justify-end gap-6">
        <button type="button" wire:click="back" class="w-[126px] cursor-pointer rounded-[5px] border border-[#a3a3a3] bg-white px-[30px] py-[10px] text-xs font-medium text-[#a3a3a3]">
            Back
        </button>
        <button type="button" wire:click="next" class="w-[126px] cursor-pointer rounded-[5px] bg-[#3e948d] px-[30px] py-[10px] text-xs font-medium text-white">
            Next
        </button>
    </div>
</div>
