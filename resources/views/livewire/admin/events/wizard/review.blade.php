<?php

use App\Models\Tournament;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.admin', ['title' => 'Create New Events'])] class extends Component {
    public Tournament $tournament;

    public bool $showCategoryPrompt = false;

    public function mount(Tournament $tournament): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        // Published events are frozen — no returning to the wizard.
        abort_if($tournament->is_published, 403);

        $this->tournament = $tournament;
    }

    /**
     * The draft was already saved in step 1 — confirming just asks the
     * admin whether to add categories now or later.
     */
    public function confirm(): void
    {
        $this->showCategoryPrompt = true;
    }

    public function addCategories(): void
    {
        $this->redirect(route('admin.categories.index'), navigate: true);
    }

    public function addLater(): void
    {
        $this->redirect(route('admin.events.show', $this->tournament), navigate: true);
    }
}; ?>

<div class="flex flex-col items-center gap-5">
    <x-admin.wizard-steps :current="2" />

    <div class="flex w-full max-w-[814px] flex-col gap-[26px] rounded-[10px] bg-white px-4 py-6 sm:px-[33px] sm:py-[35px]">
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
            <span class="text-sm font-bold text-black">Tournament Details</span>

            <div class="flex gap-2.5 text-white">
                <div class="flex flex-1 flex-col gap-1 rounded-[5px] bg-[#78b29f] px-5 py-1.5">
                    <span class="text-[11px]">Format</span>
                    <span class="text-xs font-bold">{{ str($tournament->format)->replace('_', ' ')->title() }}</span>
                </div>
                <div class="flex flex-1 flex-col gap-1 rounded-[5px] bg-[#78b29f] px-5 py-1.5">
                    <span class="text-[11px]">Pointing System</span>
                    <span class="text-xs font-bold">{{ $tournament->scoring_system ?: '—' }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="flex w-full max-w-[814px] justify-end gap-6">
        <a href="{{ route('admin.events.wizard.details', $tournament) }}" wire:navigate class="flex w-[126px] items-center justify-center rounded-[5px] border border-[#a3a3a3] bg-white px-[30px] py-[10px] text-xs font-medium text-[#a3a3a3]">
            Back
        </a>
        <button type="button" wire:click="confirm" class="w-[126px] cursor-pointer rounded-[5px] bg-[#096] px-[30px] py-[10px] text-xs font-medium text-white">
            Create Event
        </button>
    </div>

    <flux:modal wire:model.self="showCategoryPrompt" :dismissible="false" :closable="false" class="max-w-md dark:bg-white! dark:ring-black/5!">
        <div class="flex flex-col gap-5">
            <div class="flex flex-col gap-2">
                <h2 class="text-base font-bold text-black">Event created</h2>
                <p class="text-sm text-black/70">
                    "{{ $tournament->name }}" was saved as a draft. Would you like to add categories to it now, or add them later from the event page?
                </p>
            </div>

            <div class="flex justify-end gap-3">
                <flux:button wire:click="addLater">Add Later</flux:button>
                <flux:button variant="primary" wire:click="addCategories">Add Categories</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
