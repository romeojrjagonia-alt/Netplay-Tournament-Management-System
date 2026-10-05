<?php

use App\Models\Tournament;
use App\Support\Admin\ManagesEventDetailsForm;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.admin', ['title' => 'Create New Events'])] class extends Component {
    use ManagesEventDetailsForm;

    public function mount(?Tournament $tournament = null): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        // On the bare admin/events/create route (no {tournament} segment),
        // Laravel's container still instantiates an empty Tournament here
        // rather than passing null — check ->exists, not truthiness.
        if (! $tournament || ! $tournament->exists) {
            return;
        }

        // Published events are frozen — no editing, and no returning to
        // this step even via a stale bookmarked URL.
        abort_if($tournament->is_published, 403);

        $this->fillFromTournament($tournament);
    }

    /**
     * Validate this step and persist it as a draft, then move on to step 2.
     */
    public function next(): void
    {
        abort_if($this->tournament?->is_published, 403);

        $this->saveDetails($this->validateDetails());

        $this->redirect(route('admin.events.wizard.categories', $this->tournament), navigate: true);
    }
}; ?>

<div class="flex flex-col items-center gap-5">
    <x-admin.wizard-steps :current="1" />

    @include('livewire.admin.events.wizard._details-fields')

    <div class="flex w-full max-w-[814px] justify-end gap-6">
        <a href="{{ route('admin.events.index') }}" wire:navigate class="flex w-[126px] items-center justify-center rounded-[5px] border border-[#a3a3a3] bg-white px-[30px] py-[10px] text-xs font-medium text-[#a3a3a3]">
            Cancel
        </a>
        <button type="button" wire:click="next" class="w-[126px] cursor-pointer rounded-[5px] bg-[#3e948d] px-[30px] py-[10px] text-xs font-medium text-white">
            Next
        </button>
    </div>
</div>
