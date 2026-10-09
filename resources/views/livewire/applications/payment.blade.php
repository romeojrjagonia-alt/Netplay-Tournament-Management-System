<?php

use App\Models\PaymentDetail;
use App\Models\PlayerApplication;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.public', ['title' => 'Pay Registration Fee'])] class extends Component {
    use WithFileUploads;

    public PlayerApplication $application;

    public string $referenceNumber = '';

    public string $datePaid = '';

    public $proof = null;

    public bool $submitted = false;

    public function mount(string $reference): void
    {
        $application = PlayerApplication::query()
            ->with(['tournamentCategory.tournament', 'tournamentCategory.category', 'applicationPlayers.player', 'paymentDetails'])
            ->where('reference_code', strtoupper($reference))
            ->firstOrFail();

        $this->application = $application;
        $this->datePaid = now()->format('Y-m-d');
    }

    /**
     * Whether this application may currently receive a payment proof: it must
     * be approved by the organizers and not already have a proof awaiting
     * verification. A rejected proof can be resubmitted (NETPLAY.md 4.8).
     */
    public function canPay(): bool
    {
        if ($this->application->status !== 'payment_pending') {
            return false;
        }

        return $this->application->paymentDetails->where('payment_status', 'pending')->isEmpty();
    }

    public function submitPayment(): void
    {
        $this->application->refresh()->load('paymentDetails');

        if (! $this->canPay()) {
            $this->addError('payment', 'This application can\'t accept a payment right now.');

            return;
        }

        $this->validate([
            'referenceNumber' => ['required', 'string', 'max:255'],
            'datePaid' => ['required', 'date', 'before_or_equal:today'],
            'proof' => ['required', 'image', 'max:4096'],
        ], [
            'proof.required' => 'Upload a screenshot of your GCash payment.',
        ]);

        $throttleKey = 'application-payment:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            $this->addError('payment', 'Too many attempts. Please try again later.');

            return;
        }

        RateLimiter::hit($throttleKey, 3600);

        PaymentDetail::create([
            'application_id' => $this->application->id,
            'date_of_payment_made' => $this->datePaid,
            'reference_number' => $this->referenceNumber,
            'payment_status' => 'pending',
            'proof_of_payment' => $this->proof->store('payment-proofs', 'public'),
        ]);

        $this->submitted = true;
    }
}; ?>

<div class="flex flex-col gap-5">
    @php
        $tournamentCategory = $application->tournamentCategory;
        $latestPayment = $application->paymentDetails->sortByDesc('id')->first();
    @endphp

    <div>
        <p class="text-sm text-teal-800">Registration Payment</p>
        <h1 class="text-2xl font-bold text-black">{{ $tournamentCategory->tournament->name }}</h1>
        <p class="text-xs text-black/70">
            {{ $tournamentCategory->category_label }} &middot; {{ $application->applicationPlayers->pluck('player.name')->implode(' & ') }}
            &middot; {{ $application->reference_code }}
        </p>
    </div>

    @if ($submitted)
        <div class="flex flex-col items-center gap-4 rounded-[10px] border border-[#138177] bg-white p-6 text-center">
            <p class="text-lg font-bold text-black">Payment proof sent!</p>
            <p class="max-w-md text-sm text-black/70">
                The organizers will verify your payment. Check back with your reference code
                (<span class="font-bold">{{ $application->reference_code }}</span>) on the home page to see the result.
            </p>
            <a href="{{ route('home') }}#check-application" wire:navigate
               class="rounded-[5px] bg-[#00a89a] px-[30px] py-[10px] text-center text-[12px] text-white">
                Back to home
            </a>
        </div>
    @elseif ($this->canPay())
        <div class="flex items-center justify-between rounded-[5px] bg-[#eaf4f3] px-[15px] py-[12px] text-sm text-black">
            <span>Registration fee</span>
            <span class="text-lg font-bold">₱{{ number_format((float) $tournamentCategory->registration_fee, 2) }}</span>
        </div>

        @if ($latestPayment?->payment_status === 'rejected')
            <p class="rounded-[5px] bg-[#f6d1d2] px-[15px] py-[10px] text-xs text-[#bd6b6c]">
                Your previous proof of payment was rejected. Please upload a new, clear screenshot.
            </p>
        @endif

        <p class="text-xs text-black/70">
            Pay the registration fee through GCash, then upload a screenshot of the payment and enter its reference number.
        </p>

        <form wire:submit="submitPayment" class="flex flex-col gap-[15px] rounded-[5px] border border-[#138177] bg-white p-[15px]">
            <div class="grid grid-cols-1 gap-[9px] sm:grid-cols-2">
                <x-player.registration-field label="GCash Reference Number" model="referenceNumber" placeholder="e.g. 1234 567 890123" />
                <x-player.registration-field label="Date Paid" model="datePaid" type="date" />
            </div>

            <div x-data="{ fileName: '' }" class="flex flex-col gap-[2px]">
                <label class="text-[12px] text-black">Screenshot of payment</label>
                <label for="proof"
                       class="flex h-[100px] cursor-pointer items-center justify-center rounded-[5px] border border-dashed border-[#777] px-[10px] text-center text-[12px] text-[#838988]">
                    <span x-text="fileName || 'Upload screenshot (image, up to 4 MB)'"></span>
                </label>
                <input id="proof" type="file" wire:model="proof" accept="image/*" class="hidden"
                       x-on:change="fileName = $event.target.files[0]?.name ?? ''">
                @error('proof')
                    <p class="text-[10px] text-red-600">{{ $message }}</p>
                @enderror
            </div>

            @error('payment')
                <p class="text-xs text-red-600">{{ $message }}</p>
            @enderror

            <button type="submit" wire:loading.attr="disabled" wire:target="submitPayment,proof"
                    class="cursor-pointer rounded-[5px] bg-[#00a89a] px-[30px] py-[10px] text-center text-[12px] text-white disabled:cursor-not-allowed disabled:opacity-60">
                <span wire:loading.remove wire:target="submitPayment">Submit Payment Proof</span>
                <span wire:loading wire:target="submitPayment">Submitting…</span>
            </button>
        </form>
    @else
        <div class="flex flex-col items-center gap-3 rounded-[10px] border border-[#d3d3d3] bg-white p-6 text-center">
            <p class="text-sm text-black/80">
                @if ($application->status === 'pending_verification')
                    This application hasn't been approved yet, so payment isn't open. Check back after the organizers review it.
                @elseif ($application->status === 'rejected')
                    This application was rejected, so no payment is needed.
                @elseif ($application->status === 'approved')
                    This application is already fully paid and approved.
                @else
                    Your payment proof is being verified by the organizers.
                @endif
            </p>
            <a href="{{ route('home') }}#check-application" wire:navigate
               class="rounded-[5px] bg-[#00a89a] px-[30px] py-[10px] text-center text-[12px] text-white">
                Back to home
            </a>
        </div>
    @endif
</div>
