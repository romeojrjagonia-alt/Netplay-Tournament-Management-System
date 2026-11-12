{{-- Shared event-details fields, included by both the create wizard's step 1
     and the standalone edit page. Relies on the including Livewire
     component's bound properties (name, format, dateStart, …) and its
     posterPreviewUrl computed property. --}}
<div class="flex w-full max-w-[1100px] flex-col gap-[10px] rounded-[10px] bg-white px-4 py-6 sm:px-[35px] sm:py-[40px]">
    <div class="grid grid-cols-1 gap-x-[26px] gap-y-6 sm:grid-cols-2">
        <x-player.registration-field label="Event Name" model="name" placeholder="Enter event name" />

        <div class="flex flex-col gap-[5px]">
            <span class="text-sm text-black">Tournament runs on these days</span>
            <div class="flex gap-4">
                @foreach ($this->weekOrder as $day)
                    <label class="flex flex-col items-center gap-1 text-xs text-[#051c1a]">
                        {{ $day }}
                        <input type="checkbox" value="{{ $day }}" wire:model="selectedDays" class="size-5 rounded-[5px] border-2 border-[#0b4742]">
                    </label>
                @endforeach
            </div>
            @error('selectedDays') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-col gap-[2px]">
            <span class="text-sm text-black">Date Range</span>
            <div class="flex items-center gap-2">
                <div class="flex h-11 flex-1 items-center gap-[10px] rounded-[5px] bg-[#eaf4f3] px-[10px]">
                    <x-date-picker model="dateStart" />
                </div>
                <span>-</span>
                <div class="flex h-11 flex-1 items-center gap-[10px] rounded-[5px] bg-[#eaf4f3] px-[10px]">
                    <x-date-picker model="dateEnd" />
                </div>
            </div>
            @error('dateStart') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            @error('dateEnd') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-col gap-[2px]">
            <span class="text-sm text-black">Time</span>
            <div class="flex items-center gap-2">
                <div class="flex h-11 flex-1 items-center gap-[10px] rounded-[5px] bg-[#eaf4f3] px-[10px]">
                    <input wire:model="timeStart" type="time" class="w-full bg-transparent text-sm text-black focus:outline-none">
                </div>
                <span>-</span>
                <div class="flex h-11 flex-1 items-center gap-[10px] rounded-[5px] bg-[#eaf4f3] px-[10px]">
                    <input wire:model="timeEnd" type="time" class="w-full bg-transparent text-sm text-black focus:outline-none">
                </div>
            </div>
            @error('timeStart') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            @error('timeEnd') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <x-player.registration-select label="Sport" model="sport" :live="true" :options="$this->sports" />

        <x-player.registration-field label="Venue / Location" model="location" placeholder="e.g. OBC Badminton Facility, Ozamiz City" />

        <div class="flex flex-col gap-[2px]">
            <span class="text-sm text-black">Registration Window</span>
            <div class="flex items-center gap-2">
                <div class="flex h-11 flex-1 items-center gap-[10px] rounded-[5px] bg-[#eaf4f3] px-[10px]">
                    <x-date-picker model="registrationStart" />
                </div>
                <span>-</span>
                <div class="flex h-11 flex-1 items-center gap-[10px] rounded-[5px] bg-[#eaf4f3] px-[10px]">
                    <x-date-picker model="registrationEnd" />
                </div>
            </div>
            @error('registrationStart') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            @error('registrationEnd') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <x-player.registration-select
            label="Tournament Format"
            model="format"
            :live="true"
            :options="['single_elimination' => 'Single Elimination', 'round_robin' => 'Round Robin']"
        />

        <x-player.registration-select label="Pointing System" model="scoringSystem" :options="$this->scoringSystems" />

        @if ($format === 'round_robin')
            <x-player.registration-select
                label="1st Tiebreaker"
                model="tiebreakerFirst"
                placeholder="None"
                :options="\App\Models\Tournament::TIEBREAKER_RULES"
            />
            <x-player.registration-select
                label="2nd Tiebreaker (optional)"
                model="tiebreakerSecond"
                placeholder="None"
                :options="\App\Models\Tournament::TIEBREAKER_RULES"
            />
        @endif
    </div>

    <div class="flex flex-col gap-3">
        <span class="text-xs font-bold text-black">Event Cover image</span>
        <label @class([
            'group relative flex w-full cursor-pointer flex-col items-center justify-center gap-[7px] overflow-hidden rounded-[5px] border border-dashed border-black/50 px-5 py-2.5',
            'aspect-[2/1]' => $this->posterPreviewUrl,
            'h-[95px]' => ! $this->posterPreviewUrl,
        ])>
            <input type="file" wire:model="poster" accept="image/*" class="hidden">

            @if ($this->posterPreviewUrl)
                <img src="{{ $this->posterPreviewUrl }}" alt="Event cover preview" class="absolute inset-0 size-full object-cover">
                <span class="relative flex items-center gap-1.5 rounded-full bg-black/60 px-3 py-1 text-xs font-bold text-white opacity-0 transition group-hover:opacity-100">
                    <flux:icon.arrow-up-tray class="size-3.5" />
                    Click to Replace
                </span>
            @else
                <flux:icon.arrow-up-tray class="size-[30px]" />
                <span class="text-center text-xs text-[#051c1a]">
                    <span class="block font-bold">Click to Upload</span>
                    <span class="block">Recommended 1200x600 px, JPG or PNG, max 5 mb</span>
                </span>
            @endif
        </label>
        @error('poster') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
    </div>
</div>
