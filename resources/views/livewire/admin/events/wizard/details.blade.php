<?php

use App\Models\Tournament;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.admin', ['title' => 'Create New Events'])] class extends Component {
    use WithFileUploads;

    public ?Tournament $tournament = null;

    public string $name = '';

    public string $format = '';

    public string $location = '';

    public string $dateStart = '';

    public string $dateEnd = '';

    public string $registrationStart = '';

    public string $registrationEnd = '';

    /** @var array<int, string> */
    public array $selectedDays = [];

    public $poster = null;

    /** @var array<int, string> */
    public array $weekOrder = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    public function mount(?Tournament $tournament = null): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        // On the bare admin/events/create route (no {tournament} segment),
        // Laravel's container still instantiates an empty Tournament here
        // rather than passing null — check ->exists, not truthiness.
        if (! $tournament || ! $tournament->exists) {
            return;
        }

        $this->tournament = $tournament;
        $this->name = $tournament->name;
        $this->format = $tournament->format;
        $this->location = $tournament->location ?? '';
        $this->dateStart = $tournament->date_start->format('Y-m-d');
        $this->dateEnd = $tournament->date_end->format('Y-m-d');
        $this->registrationStart = $tournament->registration_start->format('Y-m-d');
        $this->registrationEnd = $tournament->registration_end->format('Y-m-d');
        $this->selectedDays = $tournament->specific_days
            ? array_map('trim', explode(',', $tournament->specific_days))
            : [];
    }

    /**
     * Validate this step and persist it as a draft, creating the tournament
     * the first time through so the admin can resume later — see
     * NETPLAY.md's admin workflow for tournament setup.
     */
    public function next(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'format' => ['required', 'in:single_elimination,round_robin,double_elimination'],
            'location' => ['nullable', 'string', 'max:255'],
            'dateStart' => ['required', 'date'],
            'dateEnd' => ['required', 'date', 'after_or_equal:dateStart'],
            'registrationStart' => ['required', 'date'],
            'registrationEnd' => ['required', 'date', 'after_or_equal:registrationStart'],
            'selectedDays' => ['required', 'array', 'min:1'],
            'poster' => ['nullable', 'image', 'max:5120'],
        ]);

        $attributes = [
            'name' => $data['name'],
            'format' => $data['format'],
            'sport' => 'Badminton',
            'location' => $data['location'],
            'scoring_system' => $this->tournament->scoring_system ?? '',
            'date_start' => $data['dateStart'],
            'date_end' => $data['dateEnd'],
            'specific_days' => collect($this->weekOrder)->filter(fn ($day) => in_array($day, $data['selectedDays'], true))->implode(', '),
            'registration_start' => $data['registrationStart'],
            'registration_end' => $data['registrationEnd'],
        ];

        if ($this->poster) {
            $attributes['poster_path'] = $this->poster->store('tournament-posters', 'public');
        }

        if ($this->tournament) {
            $this->tournament->update($attributes);
        } else {
            $this->tournament = Tournament::create([...$attributes, 'status' => 'draft']);
        }

        $this->redirect(route('admin.events.wizard.categories', $this->tournament), navigate: true);
    }
}; ?>

<div class="flex flex-col items-center gap-5">
    <x-admin.wizard-steps :current="1" />

    <div class="flex w-[814px] flex-col gap-[10px] rounded-[10px] bg-white px-[35px] py-[40px]">
        <div class="grid grid-cols-2 gap-x-[26px] gap-y-6">
            <x-player.registration-field label="Event Name" model="name" placeholder="Enter event name" />

            <div class="flex flex-col gap-[5px]">
                <span class="text-sm text-black">Tournament runs on these days</span>
                <div class="flex gap-4">
                    @foreach ($this->weekOrder as $day)
                        <label class="flex flex-col items-center gap-1 text-[9px] text-[#051c1a]">
                            {{ $day }}
                            <input type="checkbox" value="{{ $day }}" wire:model="selectedDays" class="size-[17px] rounded-[5px] border-2 border-[#0b4742]">
                        </label>
                    @endforeach
                </div>
                @error('selectedDays') <p class="text-[10px] text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col gap-[2px]">
                <span class="text-[12px] text-black">Date Range</span>
                <div class="flex items-center gap-2">
                    <div class="flex h-[30px] flex-1 items-center gap-[10px] rounded-[5px] bg-[#eaf4f3] px-[10px]">
                        <input wire:model="dateStart" type="date" class="w-full bg-transparent text-[12px] text-black focus:outline-none">
                    </div>
                    <span>-</span>
                    <div class="flex h-[30px] flex-1 items-center gap-[10px] rounded-[5px] bg-[#eaf4f3] px-[10px]">
                        <input wire:model="dateEnd" type="date" class="w-full bg-transparent text-[12px] text-black focus:outline-none">
                    </div>
                </div>
                @error('dateStart') <p class="text-[10px] text-red-600">{{ $message }}</p> @enderror
                @error('dateEnd') <p class="text-[10px] text-red-600">{{ $message }}</p> @enderror
            </div>

            <x-player.registration-field label="Event Location" model="location" placeholder="Location" />

            <div class="flex flex-col gap-[2px]">
                <span class="text-[12px] text-black">Registration Window</span>
                <div class="flex items-center gap-2">
                    <div class="flex h-[30px] flex-1 items-center gap-[10px] rounded-[5px] bg-[#eaf4f3] px-[10px]">
                        <input wire:model="registrationStart" type="date" class="w-full bg-transparent text-[12px] text-black focus:outline-none">
                    </div>
                    <span>-</span>
                    <div class="flex h-[30px] flex-1 items-center gap-[10px] rounded-[5px] bg-[#eaf4f3] px-[10px]">
                        <input wire:model="registrationEnd" type="date" class="w-full bg-transparent text-[12px] text-black focus:outline-none">
                    </div>
                </div>
                @error('registrationStart') <p class="text-[10px] text-red-600">{{ $message }}</p> @enderror
                @error('registrationEnd') <p class="text-[10px] text-red-600">{{ $message }}</p> @enderror
            </div>

            <x-player.registration-select
                label="Tournament Format"
                model="format"
                :options="['single_elimination' => 'Single Elimination', 'round_robin' => 'Round Robin', 'double_elimination' => 'Double Elimination']"
            />
        </div>

        <div class="flex flex-col gap-3">
            <span class="text-xs font-bold text-black">Event Cover image</span>
            <label class="flex h-[95px] cursor-pointer flex-col items-center justify-center gap-[7px] rounded-[5px] border border-dashed border-black/50 px-5 py-2.5">
                <input type="file" wire:model="poster" accept="image/*" class="hidden">
                <flux:icon.arrow-up-tray class="size-[30px]" />
                <span class="text-center text-[10px] text-[#051c1a]">
                    <span class="block font-bold">{{ $poster ? $poster->getClientOriginalName() : 'Click to Upload' }}</span>
                    <span class="block">Recommended 1200x600 px, JPG or PNG, max 5 mb</span>
                </span>
            </label>
            @error('poster') <p class="text-[10px] text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="flex w-[814px] justify-end gap-6">
        <a href="{{ route('admin.events.index') }}" wire:navigate class="flex w-[126px] items-center justify-center rounded-[5px] border border-[#a3a3a3] bg-white px-[30px] py-[10px] text-xs font-medium text-[#a3a3a3]">
            Cancel
        </a>
        <button type="button" wire:click="next" class="w-[126px] cursor-pointer rounded-[5px] bg-[#3e948d] px-[30px] py-[10px] text-xs font-medium text-white">
            Next
        </button>
    </div>
</div>
