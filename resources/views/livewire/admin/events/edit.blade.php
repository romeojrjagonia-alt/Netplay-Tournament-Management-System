<?php

use App\Models\Tournament;
use App\Support\Admin\ManagesEventDetailsForm;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.admin', ['title' => 'Edit Event'])] class extends Component {
    use ManagesEventDetailsForm;

    public function mount(Tournament $tournament): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        // Published events are frozen — no editing (NETPLAY.md admin
        // workflow), and the Edit Event button is hidden once published, so
        // this only guards a stale bookmarked URL.
        abort_if($tournament->is_published, 403);

        $this->fillFromTournament($tournament);
    }

    public function save(): void
    {
        abort_if($this->tournament->is_published, 403);

        $this->saveDetails($this->validateDetails());

        $this->redirect(route('admin.events.show', $this->tournament), navigate: true);
    }
}; ?>

<div class="flex flex-col items-center gap-5">
    @include('livewire.admin.events.wizard._details-fields')

    <div class="flex w-full max-w-[814px] items-center justify-end gap-6">
        <a href="{{ route('admin.events.show', ['tournament' => $tournament, 'manage' => 'categories']) }}" wire:navigate class="mr-auto flex items-center gap-1.5 rounded-[5px] bg-[#3e948d] px-[30px] py-[10px] text-xs font-medium text-white">
            <flux:icon.pencil-square class="size-4" />
            Edit Categories
        </a>

        <a href="{{ route('admin.events.show', $tournament) }}" wire:navigate class="flex w-[126px] items-center justify-center rounded-[5px] border border-[#a3a3a3] bg-white px-[30px] py-[10px] text-xs font-medium text-[#a3a3a3]">
            Cancel
        </a>
        <button type="button" wire:click="save" class="w-[126px] cursor-pointer rounded-[5px] bg-[#3e948d] px-[30px] py-[10px] text-xs font-medium text-white">
            Save Changes
        </button>
    </div>
</div>
