<?php

use App\Models\Tournament;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.admin', ['title' => 'Create New Events'])] class extends Component {
    public Tournament $tournament;

    public function mount(Tournament $tournament): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $this->tournament = $tournament;
    }

    /**
     * @return Collection<int, \App\Models\TournamentCategory>
     */
    #[Computed]
    public function categories(): Collection
    {
        return $this->tournament->tournamentCategories()->with('category')->get();
    }

    public function formatDateRange(): string
    {
        if ($this->tournament->date_start->isSameMonth($this->tournament->date_end)) {
            return $this->tournament->date_start->format('F j').' - '.$this->tournament->date_end->format('j, Y');
        }

        return $this->tournament->date_start->format('F j, Y').' - '.$this->tournament->date_end->format('F j, Y');
    }
}; ?>

<div class="flex flex-col items-center gap-5">
    <x-admin.wizard-steps :current="3" />

    <div class="flex w-[814px] flex-col gap-[26px] rounded-[10px] bg-white px-[33px] py-[35px]">
        <div class="flex flex-col gap-[17px]">
            <div class="flex items-center justify-between">
                <span class="text-sm font-bold text-black">Event Details</span>
                <a href="{{ route('admin.events.wizard.details', $tournament) }}" wire:navigate aria-label="Edit event details">
                    <flux:icon.pencil-square class="size-5 text-black" />
                </a>
            </div>

            <div class="flex flex-wrap gap-5">
                <div class="flex w-[125px] flex-col gap-1">
                    <span class="text-[11px] text-black">Event Name</span>
                    <span class="text-xs font-bold text-black">{{ $tournament->name }}</span>
                </div>
                <div class="flex w-[125px] flex-col gap-1">
                    <span class="text-[11px] text-black">Start Date</span>
                    <span class="text-xs font-bold text-black">{{ $tournament->date_start->format('M. j, Y') }}</span>
                </div>
                <div class="flex w-[125px] flex-col gap-1">
                    <span class="text-[11px] text-black">End Date</span>
                    <span class="text-xs font-bold text-black">{{ $tournament->date_end->format('M. j, Y') }}</span>
                </div>
                <div class="flex w-[125px] flex-col gap-1">
                    <span class="text-[11px] text-black">Time</span>
                    <span class="text-xs font-bold text-black">{{ $tournament->time ?? '—' }}</span>
                </div>
                <div class="flex flex-col gap-1">
                    <span class="text-[11px] text-black">Specific Days</span>
                    <div class="flex gap-1">
                        @foreach (explode(',', $tournament->specific_days ?? '') as $day)
                            <span class="rounded-[5px] bg-[#096] px-1.5 py-0.5 text-[10px] font-bold text-white">{{ trim($day) }}</span>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-5">
                <div class="flex w-[125px] flex-col gap-1">
                    <span class="text-[11px] text-black">Location</span>
                    <span class="text-xs font-bold text-black">{{ $tournament->location ?? '—' }}</span>
                </div>
                <div class="flex w-[125px] flex-col gap-1">
                    <span class="text-[11px] text-black">Registration Start</span>
                    <span class="text-xs font-bold text-black">{{ $tournament->registration_start->format('M. j, Y') }}</span>
                </div>
                <div class="flex w-[125px] flex-col gap-1">
                    <span class="text-[11px] text-black">Deadline</span>
                    <span class="text-xs font-bold text-black">{{ $tournament->registration_end->format('M. j, Y') }}</span>
                </div>
                <div class="flex flex-col gap-1">
                    <span class="text-[11px] text-black">Cover Photo File</span>
                    <div class="rounded-[3px] border-[0.5px] border-[#3e948d] px-2.5 py-1">
                        <span class="text-[9px] text-black">{{ $tournament->poster_path ? basename($tournament->poster_path) : 'No file uploaded' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-[15px]">
            <div class="flex items-end justify-between">
                <span class="text-sm font-bold text-black">Tournament Details</span>
                <a href="{{ route('admin.events.wizard.categories', $tournament) }}" wire:navigate aria-label="Edit tournament details">
                    <flux:icon.pencil-square class="size-5 text-black" />
                </a>
            </div>

            <div class="flex gap-2.5 text-white">
                <div class="flex flex-1 flex-col gap-1 rounded-[5px] bg-[#78b29f] px-5 py-1.5">
                    <span class="text-[11px]">Pointing System</span>
                    <span class="text-xs font-bold">{{ $tournament->scoring_system ?: '—' }}</span>
                </div>
                <div class="flex flex-1 flex-col gap-1 rounded-[5px] bg-[#78b29f] px-5 py-1.5">
                    <span class="text-[11px]">No. of Categories</span>
                    <span class="text-xs font-bold">{{ $this->categories->count() }}</span>
                </div>
            </div>

            <div class="flex flex-col">
                <div class="flex gap-1.5 border-b border-[#8cc7c1] bg-[#e7f0ef]">
                    <span class="flex-1 p-2.5 text-center text-xs text-black">Category</span>
                    <span class="flex-1 p-2.5 text-center text-xs text-black">Level</span>
                    <span class="flex-1 p-2.5 text-center text-xs text-black">Age</span>
                    <span class="flex-1 p-2.5 text-center text-xs text-black">Limit</span>
                    <span class="flex-1 p-2.5 text-center text-xs text-black">Registration Fee</span>
                </div>

                @foreach ($this->categories as $tournamentCategory)
                    <div class="flex gap-1.5 border-b border-[#ddefed] bg-white">
                        <span class="flex-1 p-2.5 text-center text-xs text-black">{{ $tournamentCategory->category->name }}</span>
                        <span class="flex-1 p-2.5 text-center text-xs text-black">{{ $tournamentCategory->category->level ?? '—' }}</span>
                        <span class="flex-1 p-2.5 text-center text-xs text-black">{{ $tournamentCategory->category->age_min ?? '—' }} - {{ $tournamentCategory->category->age_max ?? '—' }}</span>
                        <span class="flex-1 p-2.5 text-center text-xs text-black">{{ $tournamentCategory->max_slots }}</span>
                        <span class="flex-1 p-2.5 text-center text-xs text-black">PHP {{ number_format((float) $tournamentCategory->registration_fee, 2) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="flex w-[814px] justify-end gap-6">
        <a href="{{ route('admin.events.wizard.categories', $tournament) }}" wire:navigate class="flex w-[126px] items-center justify-center rounded-[5px] border border-[#a3a3a3] bg-white px-[30px] py-[10px] text-xs font-medium text-[#a3a3a3]">
            Back
        </a>
        <a href="{{ route('admin.events.show', $tournament) }}" wire:navigate class="flex w-[126px] items-center justify-center rounded-[5px] bg-[#096] px-[30px] py-[10px] text-xs font-medium text-white">
            Create Event
        </a>
    </div>
</div>
