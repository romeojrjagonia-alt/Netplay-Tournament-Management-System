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

    public function mount(Tournament $tournament): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $this->tournament = $tournament;
    }

    public function togglePublish(): void
    {
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

            <button type="button" disabled title="Editing a tournament isn't available yet" class="cursor-not-allowed rounded-full border border-[#138177] bg-white px-4 py-2 text-xs text-black/40">
                Edit Event
            </button>

            <button type="button" wire:click="togglePublish" class="cursor-pointer rounded-full border border-[#138177] bg-white px-4 py-2 text-xs text-black">
                {{ $tournament->is_published ? 'Unpublish Event' : 'Publish Event' }}
            </button>
        </div>

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

        <div class="flex items-center justify-center gap-5">
            <div class="flex h-10 items-center gap-2 overflow-hidden rounded-[3px] bg-white/50 px-2.5 text-sm text-[#9cee69] mix-blend-soft-light">
                <flux:icon.squares-2x2 class="size-5 shrink-0" />
                Dashboard
            </div>

            <span title="Bracket management is coming soon" class="flex h-10 cursor-not-allowed items-center gap-2 px-2.5 text-sm text-white/40">
                <flux:icon.share class="size-5 shrink-0" />
                Bracket
            </span>

            <span title="Per-event applications are coming soon" class="flex h-10 cursor-not-allowed items-center gap-2 px-2.5 text-sm text-white/40">
                <flux:icon.folder class="size-5 shrink-0" />
                Applications
            </span>

            <span title="Officials management is coming soon" class="flex h-10 cursor-not-allowed items-center gap-2 px-2.5 text-sm text-white/40">
                <flux:icon.users class="size-5 shrink-0" />
                Officials
            </span>
        </div>
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
            <p class="py-10 text-center text-sm text-white">This event has no categories yet.</p>
        @endforelse
    </div>
</div>
