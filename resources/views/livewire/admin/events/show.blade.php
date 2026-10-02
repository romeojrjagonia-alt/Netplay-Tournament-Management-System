<?php

use App\Models\PaymentDetail;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.admin', ['title' => 'Event Tournaments'])] class extends Component {
    public Tournament $tournament;

    public ?string $publishError = null;

    public function mount(Tournament $tournament): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $this->tournament = $tournament;
    }

    /**
     * Publishing requires at least one category — categories can no longer
     * be added once published, so an admin must add them before publishing
     * (they may leave a draft empty and add categories later).
     */
    public function togglePublish(): void
    {
        if (! $this->tournament->is_published && $this->tournament->tournamentCategories()->doesntExist()) {
            $this->publishError = 'Add at least one category before publishing this event.';

            return;
        }

        $this->publishError = null;

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
        <div class="flex items-center gap-5">
            <h2 class="flex-1 text-2xl font-bold text-white">{{ $tournament->name }}</h2>

            @unless ($tournament->is_published)
                <a href="{{ route('admin.events.edit', $tournament) }}" wire:navigate class="rounded-full border border-[#138177] bg-white px-4 py-2 text-xs text-black">
                    Edit Event
                </a>
            @endunless

            <button type="button" wire:click="togglePublish" class="cursor-pointer rounded-full border border-[#138177] bg-white px-4 py-2 text-xs text-black">
                {{ $tournament->is_published ? 'Unpublish Event' : 'Publish Event' }}
            </button>
        </div>

        @if ($publishError)
            <p class="text-right text-xs font-medium text-red-300">{{ $publishError }}</p>
        @endif

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

    <div class="flex flex-wrap items-center gap-[30px]">
        <div class="relative flex h-[67px] items-center gap-1.5 overflow-hidden rounded-[10px] px-[30px]">
            <div class="absolute inset-0 bg-white mix-blend-soft-light"></div>
            <span class="relative text-[10px] text-white">NO. OF CATEGORIES</span>
            <span class="relative text-[33px] font-bold text-white">{{ $this->totals['categories'] }}</span>
        </div>
        <div class="relative flex h-[67px] items-center gap-1.5 overflow-hidden rounded-[10px] px-[30px]">
            <div class="absolute inset-0 bg-white mix-blend-soft-light"></div>
            <span class="relative text-[10px] text-white">PENDING APPLICATIONS</span>
            <span class="relative text-[33px] font-bold text-white">{{ $this->totals['pendingApplications'] }}</span>
        </div>
        <div class="relative flex h-[67px] items-center gap-1.5 overflow-hidden rounded-[10px] px-[30px]">
            <div class="absolute inset-0 bg-white mix-blend-soft-light"></div>
            <span class="relative text-[10px] text-white">PENDING PAYMENTS</span>
            <span class="relative text-[33px] font-bold text-white">{{ $this->totals['pendingPayments'] }}</span>
        </div>
        <div class="relative flex h-[67px] items-center gap-1.5 overflow-hidden rounded-[10px] px-[30px]">
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
            <div class="flex items-center gap-[100px] rounded-[10px] bg-white px-[30px] py-2.5 shadow-[0px_6px_2px_rgba(0,0,0,0.25)]">
                <span class="text-lg font-bold text-black">{{ $tournamentCategory->category->name }}</span>

                <div class="flex flex-col gap-1">
                    <span class="text-xs text-black">LEVEL</span>
                    <span class="text-xs font-bold text-black uppercase">{{ $tournamentCategory->category->level ?? '—' }}</span>
                </div>

                <div class="flex flex-col gap-1">
                    <span class="text-xs text-black">AGE RANGE</span>
                    <span class="text-xs font-bold text-black">
                        {{ $tournamentCategory->category->age_min ?? '—' }} - {{ $tournamentCategory->category->age_max ?? '—' }} YRS. OLD
                    </span>
                </div>

                <div class="flex flex-1 flex-col gap-1">
                    <span class="text-xs text-black">{{ $registered }}/{{ $tournamentCategory->max_slots }} PLAYERS REGISTERED</span>
                    <div class="h-[9px] w-full overflow-hidden rounded-full bg-[#d9d9d9]">
                        <div class="h-full bg-[#9cee69]" style="width: {{ $percent }}%"></div>
                    </div>
                </div>
            </div>
        @empty
            <div class="flex h-[481px] flex-col items-center justify-center gap-6 rounded-[10px] bg-white">
                <p class="text-[22px] text-black/70 italic">No Categories Added Yet</p>

                @unless ($tournament->is_published)
                    <a href="{{ route('admin.events.wizard.categories', $tournament) }}" wire:navigate class="flex items-center gap-1.5 rounded-[10px] bg-[#3e948d] px-[30px] py-[10px] text-xs font-medium text-white">
                        <flux:icon.plus class="size-4" />
                        Add Category
                    </a>
                @endunless
            </div>
        @endforelse
    </div>
</div>
