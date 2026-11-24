<?php

use App\Models\Tournament;
use App\Support\Reports\EventReports;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.admin', ['title' => 'Event Reports'])] class extends Component {
    public Tournament $tournament;

    /** 'sales' or 'participation'. */
    public string $report = 'sales';

    public function mount(Tournament $tournament): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        abort_unless($tournament->is_concluded, 404);

        $this->tournament = $tournament;
    }

    public function selectReport(string $report): void
    {
        $this->report = in_array($report, ['sales', 'participation'], true) ? $report : 'sales';
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function sales(): array
    {
        return app(EventReports::class)->sales($this->tournament);
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function participation(): array
    {
        return app(EventReports::class)->participation($this->tournament);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-col gap-4 border-b border-white/50 pb-4 print:hidden">
        <h2 class="text-2xl font-bold text-white">{{ $tournament->name }}</h2>

        <x-admin.event-tabs :tournament="$tournament" active="reports" />
    </div>

    <div class="flex flex-wrap items-center justify-between gap-4 print:hidden">
        <div class="flex h-10 max-w-full items-center gap-2 overflow-x-auto rounded-full bg-white px-2.5 py-1.5 [scrollbar-width:none] sm:gap-4">
            @foreach (['sales' => 'Sales Report', 'participation' => 'Player Participation'] as $key => $label)
                <button type="button" wire:click="selectReport('{{ $key }}')" @class([
                    'flex h-full shrink-0 items-center justify-center rounded-full px-4 text-xs whitespace-nowrap',
                    'bg-[#0b4742] text-white' => $report === $key,
                    'cursor-pointer text-[#0b4742]' => $report !== $key,
                ])>
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="flex items-center gap-3">
            <button type="button" x-on:click="window.print()" class="cursor-pointer rounded-full border border-[#138177] bg-white px-4 py-2 text-xs text-black">
                Print
            </button>
            <a href="{{ route($report === 'sales' ? 'admin.events.reports.sales' : 'admin.events.reports.participation', $tournament) }}"
               class="rounded-full border border-[#138177] bg-white px-4 py-2 text-xs text-black">
                Download CSV
            </a>
        </div>
    </div>

    <div class="flex flex-col gap-6 rounded-[10px] bg-white p-5 text-black sm:p-[30px] print:rounded-none print:p-0">
        <div>
            <h3 class="text-lg font-bold">{{ $report === 'sales' ? 'Sales Report' : 'Player Participation Report' }}</h3>
            <p class="text-xs text-black/70">{{ $tournament->name }} · {{ $tournament->date_range_label }}</p>
        </div>

        @if ($report === 'sales')
            @php $sales = $this->sales; @endphp

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-[10px] bg-[#eaf4f3] p-3">
                    <p class="text-[10px] text-black/70">COLLECTED</p>
                    <p class="text-xl font-bold">₱{{ number_format($sales['totals']['collected'], 2) }}</p>
                </div>
                <div class="rounded-[10px] bg-[#eaf4f3] p-3">
                    <p class="text-[10px] text-black/70">OUTSTANDING</p>
                    <p class="text-xl font-bold">₱{{ number_format($sales['totals']['outstanding'], 2) }}</p>
                </div>
                <div class="rounded-[10px] bg-[#eaf4f3] p-3">
                    <p class="text-[10px] text-black/70">ENTRIES</p>
                    <p class="text-xl font-bold">{{ $sales['totals']['entries'] }}</p>
                </div>
                <div class="rounded-[10px] bg-[#eaf4f3] p-3">
                    <p class="text-[10px] text-black/70">PAID ENTRIES</p>
                    <p class="text-xl font-bold">{{ $sales['totals']['paid_entries'] }}</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[560px] text-left text-xs">
                    <thead>
                        <tr class="border-b border-[#ddefed] text-black/70">
                            <th class="py-2 pr-3 font-medium">Category</th>
                            <th class="py-2 pr-3 text-right font-medium">Entries</th>
                            <th class="py-2 pr-3 text-right font-medium">Paid</th>
                            <th class="py-2 pr-3 text-right font-medium">Fee</th>
                            <th class="py-2 pr-3 text-right font-medium">Collected</th>
                            <th class="py-2 text-right font-medium">Outstanding</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sales['categories'] as $row)
                            <tr class="border-b border-[#ddefed]">
                                <td class="py-2 pr-3 font-bold">{{ $row['category'] }}</td>
                                <td class="py-2 pr-3 text-right">{{ $row['entries'] }}</td>
                                <td class="py-2 pr-3 text-right">{{ $row['paid_entries'] }}</td>
                                <td class="py-2 pr-3 text-right">₱{{ number_format($row['fee'], 2) }}</td>
                                <td class="py-2 pr-3 text-right">₱{{ number_format($row['collected'], 2) }}</td>
                                <td class="py-2 text-right">₱{{ number_format($row['outstanding'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-6 text-center text-black/60 italic">No categories in this event.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-2">
                <h4 class="text-sm font-bold">Payment ledger</h4>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-left text-xs">
                        <thead>
                            <tr class="border-b border-[#ddefed] text-black/70">
                                <th class="py-2 pr-3 font-medium">Date</th>
                                <th class="py-2 pr-3 font-medium">Reference No.</th>
                                <th class="py-2 pr-3 font-medium">Code</th>
                                <th class="py-2 pr-3 font-medium">Player(s)</th>
                                <th class="py-2 pr-3 font-medium">Category</th>
                                <th class="py-2 text-right font-medium">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($sales['ledger'] as $row)
                                <tr class="border-b border-[#ddefed]">
                                    <td class="py-2 pr-3">{{ $row['date'] }}</td>
                                    <td class="py-2 pr-3">{{ $row['reference_number'] }}</td>
                                    <td class="py-2 pr-3">{{ $row['reference_code'] }}</td>
                                    <td class="py-2 pr-3">{{ $row['players'] }}</td>
                                    <td class="py-2 pr-3">{{ $row['category'] }}</td>
                                    <td class="py-2 text-right">₱{{ number_format($row['amount'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="py-6 text-center text-black/60 italic">No verified payments.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            @php $participation = $this->participation; $summary = $participation['summary']; @endphp

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-[10px] bg-[#eaf4f3] p-3">
                    <p class="text-[10px] text-black/70">PLAYERS</p>
                    <p class="text-xl font-bold">{{ $summary['players'] }}</p>
                </div>
                <div class="rounded-[10px] bg-[#eaf4f3] p-3">
                    <p class="text-[10px] text-black/70">ENTRIES</p>
                    <p class="text-xl font-bold">{{ $summary['entries'] }}</p>
                </div>
                <div class="rounded-[10px] bg-[#eaf4f3] p-3">
                    <p class="text-[10px] text-black/70">NEW / RETURNING</p>
                    <p class="text-xl font-bold">{{ $summary['new_players'] }} / {{ $summary['returning_players'] }}</p>
                </div>
                <div class="rounded-[10px] bg-[#eaf4f3] p-3">
                    <p class="text-[10px] text-black/70">MALE / FEMALE / OTHER</p>
                    <p class="text-xl font-bold">{{ $summary['male'] }} / {{ $summary['female'] }} / {{ $summary['other'] }}</p>
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <h4 class="text-sm font-bold">Final placement</h4>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[480px] text-left text-xs">
                        <thead>
                            <tr class="border-b border-[#ddefed] text-black/70">
                                <th class="py-2 pr-3 font-medium">Category</th>
                                <th class="py-2 pr-3 font-medium">Champion</th>
                                <th class="py-2 font-medium">Runner-up</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($participation['placements'] as $row)
                                <tr class="border-b border-[#ddefed]">
                                    <td class="py-2 pr-3 font-bold">{{ $row['category'] }}</td>
                                    <td class="py-2 pr-3">{{ $row['champion'] }}</td>
                                    <td class="py-2">{{ $row['runner_up'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="py-6 text-center text-black/60 italic">No categories in this event.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <h4 class="text-sm font-bold">Player roster</h4>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-left text-xs">
                        <thead>
                            <tr class="border-b border-[#ddefed] text-black/70">
                                <th class="py-2 pr-3 font-medium">Category</th>
                                <th class="py-2 pr-3 font-medium">Player</th>
                                <th class="py-2 pr-3 font-medium">Gender</th>
                                <th class="py-2 pr-3 font-medium">Age</th>
                                <th class="py-2 pr-3 font-medium">Level</th>
                                <th class="py-2 pr-3 font-medium">Club</th>
                                <th class="py-2 font-medium">Partner</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($participation['roster'] as $row)
                                <tr class="border-b border-[#ddefed]">
                                    <td class="py-2 pr-3">{{ $row['category'] }}</td>
                                    <td class="py-2 pr-3 font-bold">{{ $row['name'] }}</td>
                                    <td class="py-2 pr-3">{{ \Illuminate\Support\Str::headline($row['gender']) ?: '—' }}</td>
                                    <td class="py-2 pr-3">{{ $row['age'] ?? '—' }}</td>
                                    <td class="py-2 pr-3">{{ $row['level'] ?: '—' }}</td>
                                    <td class="py-2 pr-3">{{ $row['club'] ?: '—' }}</td>
                                    <td class="py-2">{{ $row['partner'] ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="py-6 text-center text-black/60 italic">No approved players.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
