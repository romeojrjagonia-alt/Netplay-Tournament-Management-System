<?php

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.admin', ['title' => 'Dashboard'])] class extends Component {
    public string $activeTab = 'approvals';

    public string $search = '';

    public string $eventFilter = 'All Events';

    public string $categoryFilter = 'All Categories';

    public function selectTab(string $tab): void
    {
        $this->activeTab = $tab;

        $this->reset(['search', 'eventFilter', 'categoryFilter']);
    }

    /**
     * Mock applications awaiting admin review, until the applications table exists.
     *
     * @return array<int, array<string, string>>
     */
    protected function approvals(): array
    {
        return [
            ['id' => '#0001', 'player' => 'Gwen D. Aninao', 'event' => 'OBC Summer Open', 'category' => "Women's Singles", 'date' => 'June 3, 2026', 'contact' => '09203920697', 'status' => 'Verified'],
            ['id' => '#0002', 'player' => 'Marcus T. Okoye', 'event' => 'Ozamiz City Open', 'category' => "Men's Doubles", 'date' => 'June 5, 2026', 'contact' => '09778890011', 'status' => 'Pending'],
            ['id' => '#0003', 'player' => 'Lina M. Santos', 'event' => 'Barangay Cup', 'category' => 'Mixed Doubles Level B', 'date' => 'June 6, 2026', 'contact' => '06655443322', 'status' => 'Rejected'],
            ['id' => '#0004', 'player' => 'Javier R. Mendes', 'event' => 'OBC Summer Open', 'category' => "Men's Singles", 'date' => 'June 6, 2026', 'contact' => '07788990011', 'status' => 'Rejected'],
            ['id' => '#0005', 'player' => 'Aisha T. Patel', 'event' => 'Provincial Meet', 'category' => "Women's Doubles", 'date' => 'June 7, 2026', 'contact' => '05566778899', 'status' => 'Pending'],
            ['id' => '#0006', 'player' => 'Marco L. Rivera', 'event' => 'Barangay Cup', 'category' => "Men's Singles Level A", 'date' => 'June 8, 2026', 'contact' => '04455667788', 'status' => 'Verified'],
            ['id' => '#0007', 'player' => 'Aisha T. Nguyen', 'event' => 'Provincial Meet', 'category' => 'Mixed Doubles Level A', 'date' => 'June 9, 2026', 'contact' => '03334445566', 'status' => 'Pending'],
            ['id' => '#0008', 'player' => 'Carlos M. Dominguez', 'event' => 'OBC Summer Open', 'category' => "Men's Doubles", 'date' => 'June 10, 2026', 'contact' => '02223334455', 'status' => 'Pending'],
            ['id' => '#0009', 'player' => 'Emily R. Stone', 'event' => 'Ozamiz City Open', 'category' => "Women's Singles", 'date' => 'June 11, 2026', 'contact' => '01112223344', 'status' => 'Verified'],
            ['id' => '#0010', 'player' => 'Jamal K. Patel', 'event' => 'Barangay Cup', 'category' => "Men's Doubles Level B", 'date' => 'June 12, 2026', 'contact' => '05566778899', 'status' => 'Verified'],
        ];
    }

    /**
     * Mock applications awaiting payment verification, until the payments table exists.
     *
     * @return array<int, array<string, string>>
     */
    protected function payments(): array
    {
        return [
            ['id' => '#0011', 'player' => 'Sophia L. Bennett', 'event' => 'OBC Summer Open', 'category' => "Women's Doubles", 'date' => 'June 13, 2026', 'contact' => '06677889900', 'status' => 'Receipt #67948'],
            ['id' => '#0012', 'player' => "Liam G. O'Connor", 'event' => 'Provincial Meet', 'category' => "Men's Singles", 'date' => 'June 14, 2026', 'contact' => '07788990011', 'status' => 'Receipt #67959'],
            ['id' => '#0013', 'player' => 'Nina S. Kovac', 'event' => 'Barangay Cup', 'category' => 'Mixed Doubles Level B', 'date' => 'June 15, 2026', 'contact' => '08899001122', 'status' => 'Receipt #67960'],
            ['id' => '#0014', 'player' => 'Omar D. El-Sayed', 'event' => 'Ozamiz City Open', 'category' => "Men's Doubles", 'date' => 'June 16, 2026', 'contact' => '09900112233', 'status' => 'Receipt #67971'],
            ['id' => '#0015', 'player' => 'Isabel K. Romero', 'event' => 'OBC Summer Open', 'category' => "Women's Singles", 'date' => 'June 17, 2026', 'contact' => '01011223344', 'status' => 'Receipt #67982'],
            ['id' => '#0016', 'player' => 'Nathan D. Cruz', 'event' => 'Provincial Meet', 'category' => "Men's Doubles", 'date' => 'June 18, 2026', 'contact' => '02122334455', 'status' => 'Pending'],
            ['id' => '#0017', 'player' => 'Priya S. Kapoor', 'event' => 'Barangay Cup', 'category' => "Women's Doubles", 'date' => 'June 19, 2026', 'contact' => '03233445566', 'status' => 'Verified'],
        ];
    }

    /**
     * @return array{events: int, players: int, pendingApprovals: int, pendingPayments: int}
     */
    #[Computed]
    public function totals(): array
    {
        $applications = collect($this->approvals())->merge($this->payments());

        return [
            'events' => $applications->pluck('event')->unique()->count(),
            'players' => $applications->pluck('player')->unique()->count(),
            'pendingApprovals' => collect($this->approvals())->where('status', 'Pending')->count(),
            'pendingPayments' => collect($this->payments())->reject(fn (array $row) => $row['status'] === 'Verified')->count(),
        ];
    }

    #[Computed]
    public function rows(): Collection
    {
        $rows = collect($this->activeTab === 'approvals' ? $this->approvals() : $this->payments());

        return $rows
            ->when($this->search !== '', fn (Collection $rows) => $rows->filter(
                fn (array $row) => str_contains(strtolower($row['player']), strtolower($this->search))
                    || str_contains(strtolower($row['id']), strtolower($this->search))
            ))
            ->when($this->eventFilter !== 'All Events', fn (Collection $rows) => $rows->where('event', $this->eventFilter))
            ->when($this->categoryFilter !== 'All Categories', fn (Collection $rows) => $rows->where('category', $this->categoryFilter))
            ->values();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function events(): array
    {
        $rows = $this->activeTab === 'approvals' ? $this->approvals() : $this->payments();

        return collect($rows)->pluck('event')->unique()->sort()->values()->all();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function categories(): array
    {
        $rows = $this->activeTab === 'approvals' ? $this->approvals() : $this->payments();

        return collect($rows)->pluck('category')->unique()->sort()->values()->all();
    }
}; ?>

<div>
    <div class="mb-5 grid grid-cols-2 gap-5 lg:grid-cols-4">
        <x-admin.analytics-card label="TOTAL EVENTS" :value="$this->totals['events']" />
        <x-admin.analytics-card label="TOTAL PLAYERS" :value="$this->totals['players']" />
        <x-admin.analytics-card label="PENDING APPROVALS" :value="$this->totals['pendingApprovals']" />
        <x-admin.analytics-card label="PENDING PAYMENTS" :value="$this->totals['pendingPayments']" />
    </div>

    <div class="overflow-x-auto rounded-[5px] bg-white p-2.5">
        <div class="flex min-w-[900px] flex-wrap items-center justify-between gap-4 rounded-[5px] bg-[#e5f1f0] px-3 py-1">
            <div class="flex items-center gap-10">
                <button type="button" wire:click="selectTab('approvals')" @class([
                    'px-2.5 py-1.5 text-sm whitespace-nowrap',
                    'border-b-2 border-[#138177] font-semibold text-[#138177]' => $activeTab === 'approvals',
                    'rounded-[10px] font-normal text-black hover:bg-black/5' => $activeTab !== 'approvals',
                ])>
                    Pending Approvals ({{ $this->totals['pendingApprovals'] }})
                </button>
                <button type="button" wire:click="selectTab('payments')" @class([
                    'px-2.5 py-1.5 text-sm whitespace-nowrap',
                    'border-b-2 border-[#138177] font-semibold text-[#138177]' => $activeTab === 'payments',
                    'rounded-[10px] font-normal text-black hover:bg-black/5' => $activeTab !== 'payments',
                ])>
                    Pending Payments ({{ $this->totals['pendingPayments'] }})
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-2.5 py-2.5">
                <select wire:model.live="eventFilter" class="rounded-[5px] border border-[#138177] bg-white p-2.5 text-xs text-black">
                    <option>All Events</option>
                    @foreach ($this->events as $event)
                        <option value="{{ $event }}">{{ $event }}</option>
                    @endforeach
                </select>

                <select wire:model.live="categoryFilter" class="rounded-[5px] border border-[#138177] bg-white p-2.5 text-xs text-black">
                    <option>All Categories</option>
                    @foreach ($this->categories as $category)
                        <option value="{{ $category }}">{{ $category }}</option>
                    @endforeach
                </select>

                <label class="flex w-[265px] items-center gap-2.5 rounded-[5px] bg-white px-5 py-2.5">
                    <flux:icon name="magnifying-glass" class="size-4 shrink-0 text-black/50" />
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search"
                        class="w-full bg-transparent text-xs text-black placeholder:text-black/50 focus:outline-none"
                    >
                </label>
            </div>
        </div>

        <div class="grid min-w-[900px] grid-cols-[0.4fr_0.6fr_0.5fr_0.5fr_0.5fr_0.4fr_0.4fr] gap-x-[15px] border-b border-[#8cc7c1] bg-[#e7f0ef] px-2.5 py-2">
            <span class="text-xs font-semibold text-black">Application ID</span>
            <span class="text-xs font-semibold text-black">Player Name</span>
            <span class="text-xs font-semibold text-black">Event</span>
            <span class="text-xs font-semibold text-black">Category</span>
            <span class="text-xs font-semibold text-black">Date Applied</span>
            <span class="text-xs font-semibold text-black">Contact</span>
            <span class="text-xs font-semibold text-black">Status</span>
        </div>

        <div class="max-h-[450px] min-w-[900px] overflow-y-auto">
            @forelse ($this->rows as $row)
                <div wire:key="{{ $activeTab }}-{{ $row['id'] }}" class="grid grid-cols-[0.4fr_0.6fr_0.5fr_0.5fr_0.5fr_0.4fr_0.4fr] items-center gap-x-[15px] border-b border-[#8cc7c1] bg-white px-2.5 py-2">
                    <span class="text-xs text-black">{{ $row['id'] }}</span>
                    <span class="text-xs text-black">{{ $row['player'] }}</span>
                    <span class="text-xs font-medium text-black">{{ $row['event'] }}</span>
                    <span class="text-xs text-black">{{ $row['category'] }}</span>
                    <span class="text-xs text-black">{{ $row['date'] }}</span>
                    <span class="text-xs text-black">{{ $row['contact'] }}</span>
                    <span class="text-xs text-black">{{ $row['status'] }}</span>
                </div>
            @empty
                <p class="px-2.5 py-6 text-center text-xs text-black/50">No applications match your filters.</p>
            @endforelse
        </div>
    </div>
</div>
