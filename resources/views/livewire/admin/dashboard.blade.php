<?php

use App\Models\PaymentDetail;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.admin', ['title' => 'Dashboard'])] class extends Component {
    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
    }

    public string $activeTab = 'approvals';

    public string $search = '';

    public string $eventFilter = 'All Events';

    public string $categoryFilter = 'All Categories';

    public ?int $viewingApplicationId = null;

    public ?int $viewingPaymentId = null;

    public function selectTab(string $tab): void
    {
        $this->activeTab = $tab;

        $this->reset(['search', 'eventFilter', 'categoryFilter']);
    }

    public function viewApplication(int $applicationId): void
    {
        $this->viewingApplicationId = $applicationId;
    }

    public function viewPayment(int $paymentId): void
    {
        $this->viewingPaymentId = $paymentId;
    }

    /**
     * Admin verified the applicant's eligibility; the applicant now needs to pay.
     */
    public function approveApplication(int $applicationId): void
    {
        PlayerApplication::where('status', 'pending_verification')
            ->findOrFail($applicationId)
            ->update(['status' => 'payment_pending']);

        $this->viewingApplicationId = null;

        $this->closeModalAndNotify('application-details', 'Application approved. The applicant can now proceed to payment.');
    }

    public function rejectApplication(int $applicationId): void
    {
        PlayerApplication::where('status', 'pending_verification')
            ->findOrFail($applicationId)
            ->update(['status' => 'rejected']);

        $this->viewingApplicationId = null;

        $this->closeModalAndNotify('application-details', 'Application rejected.', 'danger');
    }

    /**
     * Admin verified the payment proof; the application is now fully approved.
     */
    public function approvePayment(int $paymentId): void
    {
        $payment = PaymentDetail::where('payment_status', 'pending')->findOrFail($paymentId);

        $payment->update(['payment_status' => 'verified']);
        $payment->application->update(['status' => 'approved']);

        $this->viewingPaymentId = null;

        $this->closeModalAndNotify('payment-details', 'Payment verified. The application is now fully approved.');
    }

    /**
     * Payment proof rejected; the application stays payment_pending so the
     * player can resubmit, per the audit-trail design in NETPLAY.md 4.8.
     */
    public function rejectPayment(int $paymentId): void
    {
        PaymentDetail::where('payment_status', 'pending')
            ->findOrFail($paymentId)
            ->update(['payment_status' => 'rejected']);

        $this->viewingPaymentId = null;

        $this->closeModalAndNotify('payment-details', 'Payment rejected. The applicant can resubmit proof of payment.', 'danger');
    }

    private function closeModalAndNotify(string $modal, string $message, string $variant = 'success'): void
    {
        $this->dispatch('modal-close', name: $modal);
        $this->dispatch('admin-toast', message: $message, variant: $variant);
    }

    /**
     * Applications awaiting admin verification of eligibility.
     *
     * @return Collection<int, PlayerApplication>
     */
    #[Computed]
    public function applications(): Collection
    {
        return PlayerApplication::query()
            ->where('status', 'pending_verification')
            ->with(['tournamentCategory.tournament', 'tournamentCategory.category', 'applicationPlayers.player'])
            ->latest()
            ->get();
    }

    /**
     * Payment proofs awaiting admin verification.
     *
     * @return Collection<int, PaymentDetail>
     */
    #[Computed]
    public function pendingPayments(): Collection
    {
        return PaymentDetail::query()
            ->where('payment_status', 'pending')
            ->with(['application.tournamentCategory.tournament', 'application.tournamentCategory.category', 'application.applicationPlayers.player'])
            ->latest()
            ->get();
    }

    /**
     * @return array{events: int, players: int, pendingApprovals: int, pendingPayments: int}
     */
    #[Computed]
    public function totals(): array
    {
        return [
            'events' => Tournament::count(),
            'players' => Player::count(),
            'pendingApprovals' => $this->applications->count(),
            'pendingPayments' => $this->pendingPayments->count(),
        ];
    }

    /**
     * Table rows for the active tab, before search/filter are applied.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function baseRows(): Collection
    {
        return $this->activeTab === 'approvals'
            ? $this->applications->map(fn (PlayerApplication $application) => $this->mapRow($application))
            : $this->pendingPayments->map(fn (PaymentDetail $payment) => $this->mapRow($payment->application, $payment));
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRow(PlayerApplication $application, ?PaymentDetail $payment = null): array
    {
        $players = $application->applicationPlayers;

        return [
            'application_id' => $application->id,
            'payment_id' => $payment?->id,
            'player' => $players->pluck('player.name')->implode(', '),
            'contact' => $players->pluck('player.phone_number')->filter()->implode(', '),
            'event' => $application->tournamentCategory->tournament->name,
            'category' => $application->tournamentCategory->category_label,
            'date' => $application->created_at->format('F j, Y'),
            'status' => 'Pending',
        ];
    }

    #[Computed]
    public function rows(): Collection
    {
        return $this->baseRows()
            ->when($this->search !== '', fn (Collection $rows) => $rows->filter(
                fn (array $row) => str_contains(strtolower($row['player']), strtolower($this->search))
                    || str_contains((string) $row['application_id'], $this->search)
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
        return $this->baseRows()->pluck('event')->unique()->sort()->values()->all();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function categories(): array
    {
        return $this->baseRows()->pluck('category')->unique()->sort()->values()->all();
    }

    #[Computed]
    public function viewingApplication(): ?PlayerApplication
    {
        if (! $this->viewingApplicationId) {
            return null;
        }

        return PlayerApplication::query()
            ->where('status', 'pending_verification')
            ->with(['tournamentCategory.tournament', 'tournamentCategory.category', 'applicationPlayers.player'])
            ->find($this->viewingApplicationId);
    }

    #[Computed]
    public function viewingPayment(): ?PaymentDetail
    {
        if (! $this->viewingPaymentId) {
            return null;
        }

        return PaymentDetail::query()
            ->where('payment_status', 'pending')
            ->with(['application.tournamentCategory.tournament', 'application.tournamentCategory.category', 'application.applicationPlayers.player'])
            ->find($this->viewingPaymentId);
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
                <flux:modal.trigger :name="$activeTab === 'approvals' ? 'application-details' : 'payment-details'" wire:key="{{ $activeTab }}-{{ $row['application_id'] }}-{{ $row['payment_id'] }}">
                    <div
                        wire:click="{{ $activeTab === 'approvals' ? 'viewApplication('.$row['application_id'].')' : 'viewPayment('.$row['payment_id'].')' }}"
                        class="grid cursor-pointer grid-cols-[0.4fr_0.6fr_0.5fr_0.5fr_0.5fr_0.4fr_0.4fr] items-center gap-x-[15px] border-b border-[#8cc7c1] bg-white px-2.5 py-2 hover:bg-black/2.5"
                    >
                        <span class="text-xs text-black">#{{ str_pad($row['application_id'], 4, '0', STR_PAD_LEFT) }}</span>
                        <span class="text-xs text-black">{{ $row['player'] }}</span>
                        <span class="text-xs font-medium text-black">{{ $row['event'] }}</span>
                        <span class="text-xs text-black">{{ $row['category'] }}</span>
                        <span class="text-xs text-black">{{ $row['date'] }}</span>
                        <span class="text-xs text-black">{{ $row['contact'] }}</span>
                        <span class="text-xs text-black">{{ $row['status'] }}</span>
                    </div>
                </flux:modal.trigger>
            @empty
                <p class="px-2.5 py-6 text-center text-xs text-black/50">No applications match your filters.</p>
            @endforelse
        </div>
    </div>

    <flux:modal name="application-details" class="w-full max-w-2xl dark:bg-white! dark:ring-black/5!">
        <div wire:loading wire:target="viewApplication" class="flex items-center justify-center py-24">
            <flux:icon name="arrow-path" class="size-6 animate-spin text-black/40" />
        </div>

        <div wire:loading.remove wire:target="viewApplication">
            @if ($this->viewingApplication)
                <div class="flex flex-col gap-[25px]">
                    <h2 class="text-xl font-bold text-black">Tournament Application</h2>

                    <div class="flex flex-col gap-[16px]">
                        <div class="flex h-[25px] items-center justify-between rounded-[5px] bg-[#ffe48b] px-[15px]">
                            <span class="text-[11px] text-[#d7b135]">Pending Approval</span>
                        </div>

                        <div class="flex flex-wrap items-center gap-[10px] px-[5px] text-[11px]">
                            <div class="flex w-[139px] flex-col gap-1">
                                <span class="text-[#646464]">Event Applied</span>
                                <span class="font-bold text-black">{{ $this->viewingApplication->tournamentCategory->tournament->name }}</span>
                            </div>
                            <div class="flex w-[139px] flex-col gap-1">
                                <span class="text-[#646464]">Category</span>
                                <span class="font-bold text-black">{{ $this->viewingApplication->tournamentCategory->category_label }}</span>
                            </div>
                            <div class="flex w-[140px] flex-col gap-1">
                                <span class="text-[#646464]">Team</span>
                                <span class="font-bold text-black">{{ $this->viewingApplication->applicationPlayers->pluck('player.name')->implode(' & ') }}</span>
                            </div>
                            <div class="flex w-[140px] flex-col gap-1">
                                <span class="text-[#646464]">Date Applied</span>
                                <span class="font-bold text-black">{{ $this->viewingApplication->created_at->format('F j, Y') }}</span>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-start gap-5">
                            @foreach ($this->viewingApplication->applicationPlayers as $index => $applicationPlayer)
                                <x-admin.application-player-card :application-player="$applicationPlayer" :index="$index + 1" />
                            @endforeach
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-[22px]">
                        <button type="button" wire:click="rejectApplication({{ $this->viewingApplication->id }})" class="w-[126px] cursor-pointer rounded-[5px] border border-[#bd6b6c] bg-white px-[30px] py-[10px] text-xs font-medium text-[#bd6b6c]">
                            Reject
                        </button>
                        <button type="button" wire:click="approveApplication({{ $this->viewingApplication->id }})" class="w-[131px] cursor-pointer rounded-[5px] bg-[#3e948d] px-[30px] py-[10px] text-xs font-medium text-white">
                            Approve
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </flux:modal>

    <flux:modal name="payment-details" class="w-full max-w-2xl dark:bg-white! dark:ring-black/5!">
        <div wire:loading wire:target="viewPayment" class="flex items-center justify-center py-24">
            <flux:icon name="arrow-path" class="size-6 animate-spin text-black/40" />
        </div>

        <div wire:loading.remove wire:target="viewPayment">
            @if ($this->viewingPayment)
                @php $application = $this->viewingPayment->application; @endphp
                <div class="flex flex-col gap-[25px]">
                    <h2 class="text-xl font-bold text-black">Tournament Application</h2>

                    <div class="flex flex-col gap-[16px]">
                        <div class="flex h-[25px] items-center justify-between rounded-[5px] bg-[#ffe48b] px-[15px]">
                            <span class="text-[11px] text-[#d7b135]">Pending Approval - Payment Registration</span>
                        </div>

                        <div class="flex flex-wrap items-center gap-[10px] px-[5px] text-[11px]">
                            <div class="flex w-[139px] flex-col gap-1">
                                <span class="text-[#646464]">Event Applied</span>
                                <span class="font-bold text-black">{{ $application->tournamentCategory->tournament->name }}</span>
                            </div>
                            <div class="flex w-[139px] flex-col gap-1">
                                <span class="text-[#646464]">Category</span>
                                <span class="font-bold text-black">{{ $application->tournamentCategory->category_label }}</span>
                            </div>
                            <div class="flex w-[140px] flex-col gap-1">
                                <span class="text-[#646464]">Team</span>
                                <span class="font-bold text-black">{{ $application->applicationPlayers->pluck('player.name')->implode(' & ') }}</span>
                            </div>
                            <div class="flex w-[140px] flex-col gap-1">
                                <span class="text-[#646464]">Date Applied</span>
                                <span class="font-bold text-black">{{ $application->created_at->format('F j, Y') }}</span>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-start gap-5">
                            @foreach ($application->applicationPlayers as $index => $applicationPlayer)
                                <x-admin.application-player-card :application-player="$applicationPlayer" :index="$index + 1" />
                            @endforeach
                        </div>
                    </div>

                    <div class="flex flex-col gap-[15px] text-[11px] text-black">
                        <div class="flex flex-wrap gap-x-[29px] gap-y-[10px] px-[5px]">
                            <div class="flex items-center gap-[29px]">
                                <span>Date Paid</span>
                                <span class="font-bold">{{ $this->viewingPayment->date_of_payment_made->format('F j, Y') }}</span>
                            </div>
                            <div class="flex items-center gap-[29px]">
                                <span>Payment ID</span>
                                <span class="font-bold">{{ $this->viewingPayment->reference_number ?? '—' }}</span>
                            </div>
                        </div>

                        <a href="{{ $this->viewingPayment->proof_url }}" target="_blank" rel="noopener" class="flex items-start gap-[29px] rounded-[8px] border-[0.5px] border-[#a9a9a9] px-5 py-[10px]">
                            <span class="flex-1 text-black">Payment Receipt Attachment</span>
                            <span class="text-right text-[#a9a9a9] italic">View Attachment</span>
                        </a>
                    </div>

                    <div class="flex items-center justify-end gap-[22px]">
                        <button type="button" wire:click="rejectPayment({{ $this->viewingPayment->id }})" class="w-[126px] cursor-pointer rounded-[5px] border border-[#bd6b6c] bg-white px-[30px] py-[10px] text-xs font-medium text-[#bd6b6c]">
                            Reject
                        </button>
                        <button type="button" wire:click="approvePayment({{ $this->viewingPayment->id }})" class="w-[131px] cursor-pointer rounded-[5px] bg-[#3e948d] px-[30px] py-[10px] text-xs font-medium text-white">
                            Approve
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </flux:modal>

    <flux:toast />

    @script
    <script>
        this.$on('admin-toast', (event) => {
            window.Flux.toast({ text: event.detail.message, variant: event.detail.variant });
        });
    </script>
    @endscript
</div>
