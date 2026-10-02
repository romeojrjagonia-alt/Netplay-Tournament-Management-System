<?php

use App\Models\Tournament;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.admin', ['title' => 'Schedule Matches'])] class extends Component {
    public Tournament $tournament;

    /**
     * The category dropdown on the schedule page switches between
     * categories, so this entry point just lands on the first one.
     */
    public function mount(Tournament $tournament): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $this->tournament = $tournament;

        $first = $tournament->tournamentCategories()->orderBy('id')->first();

        if ($first) {
            $this->redirect(route('admin.events.schedule.show', [$tournament, $first]), navigate: true);
        }
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-col gap-4 border-b border-white/50 pb-4">
        <h2 class="text-2xl font-bold text-white">{{ $tournament->name }}</h2>

        <x-admin.event-tabs :tournament="$tournament" active="schedule" />
    </div>

    <div class="flex h-[481px] flex-col items-center justify-center gap-6 rounded-[10px] bg-white">
        <p class="text-[22px] text-black/70 italic">No Categories Added Yet</p>
    </div>
</div>
