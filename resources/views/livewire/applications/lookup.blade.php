<?php

use App\Models\PlayerApplication;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component {
    public string $code = '';

    /** The code that was successfully looked up; locked so it can't be swapped from the browser. */
    #[Locked]
    public ?string $foundCode = null;

    public function search(): void
    {
        $this->validate(['code' => ['required', 'string', 'max:20']], [
            'code.required' => 'Enter your application reference code.',
        ]);

        $throttleKey = 'application-lookup:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 15)) {
            $this->addError('code', 'Too many attempts. Please wait a minute and try again.');

            return;
        }

        RateLimiter::hit($throttleKey, 60);

        $code = strtoupper(trim($this->code));

        if (! PlayerApplication::where('reference_code', $code)->exists()) {
            $this->foundCode = null;
            $this->addError('code', 'We couldn\'t find an application with that code.');

            return;
        }

        $this->foundCode = $code;
        unset($this->application);

        $this->dispatch('modal-show', name: 'application-lookup');
    }

    #[Computed]
    public function application(): ?PlayerApplication
    {
        if (! $this->foundCode) {
            return null;
        }

        return PlayerApplication::query()
            ->with([
                'tournamentCategory.tournament',
                'tournamentCategory.category',
                'applicationPlayers.player',
                'paymentDetails',
            ])
            ->where('reference_code', $this->foundCode)
            ->first();
    }

    /**
     * What the applicant should see and be able to do next, derived from the
     * application's status and its most recent payment proof.
     *
     * @return array{label: string, tone: string, message: string, canPay: bool, payLabel: string}
     */
    #[Computed]
    public function progress(): array
    {
        $application = $this->application;
        $latestPayment = $application?->paymentDetails->sortByDesc('id')->first();

        return match (true) {
            $application === null => ['label' => '', 'tone' => 'pending', 'message' => '', 'canPay' => false, 'payLabel' => ''],
            $application->status === 'rejected' => [
                'label' => 'Rejected',
                'tone' => 'rejected',
                'message' => 'Your application was not approved.',
                'canPay' => false,
                'payLabel' => '',
            ],
            $application->status === 'approved' => [
                'label' => 'Approved',
                'tone' => 'approved',
                'message' => 'Your payment is verified. You\'re officially in — see you at the tournament!',
                'canPay' => false,
                'payLabel' => '',
            ],
            $application->status === 'payment_pending' && $latestPayment?->payment_status === 'pending' => [
                'label' => 'Payment under review',
                'tone' => 'pending',
                'message' => 'We received your proof of payment. The organizers are verifying it.',
                'canPay' => false,
                'payLabel' => '',
            ],
            $application->status === 'payment_pending' && $latestPayment?->payment_status === 'rejected' => [
                'label' => 'Payment rejected',
                'tone' => 'rejected',
                'message' => 'Your last proof of payment was rejected. Please send a new one.',
                'canPay' => true,
                'payLabel' => 'Resubmit Payment',
            ],
            $application->status === 'payment_pending' => [
                'label' => 'Approved — payment needed',
                'tone' => 'approved',
                'message' => 'Your application is approved. Pay the registration fee to secure your slot.',
                'canPay' => true,
                'payLabel' => 'Proceed to Payment',
            ],
            default => [
                'label' => 'Waiting for verification',
                'tone' => 'pending',
                'message' => 'The organizers are still reviewing your application.',
                'canPay' => false,
                'payLabel' => '',
            ],
        };
    }
}; ?>

<div>
    <form wire:submit="search" class="flex flex-col gap-1.5">
        <label for="reference_code" class="text-sm text-white">Enter Application Reference Code</label>

        <div class="flex items-center gap-3">
            <input id="reference_code" type="text" wire:model="code" placeholder="e.g. NP-7K4Q9X" autocomplete="off"
                   class="h-11 min-w-0 flex-1 rounded-[10px] bg-white px-2.5 text-sm text-black uppercase placeholder:text-black/30 placeholder:normal-case focus:ring-2 focus:ring-emerald-600 focus:outline-none lg:w-60 lg:flex-none">
            <button type="submit"
                    class="h-9 shrink-0 cursor-pointer rounded-[36px] bg-white px-9 text-sm font-bold text-teal-700 transition hover:bg-slate-100">
                <span wire:loading.remove wire:target="search">Search</span>
                <span wire:loading wire:target="search">Searching…</span>
            </button>
        </div>

        @error('code')
            <p class="text-xs font-medium text-red-200">{{ $message }}</p>
        @enderror
    </form>

    <flux:modal name="application-lookup" class="w-full max-w-lg dark:bg-white! dark:ring-black/5!">
        @if ($this->application)
            @php
                $application = $this->application;
                $progress = $this->progress;
                $toneClasses = [
                    'approved' => 'bg-[#9cee69]/40 text-[#0b4742]',
                    'rejected' => 'bg-[#f6d1d2] text-[#bd6b6c]',
                    'pending' => 'bg-[#ffe48b] text-[#9a7a12]',
                ][$progress['tone']];
            @endphp

            <div class="flex flex-col gap-5">
                <div>
                    <p class="text-xs text-black/60">Application {{ $application->reference_code }}</p>
                    <h2 class="text-xl font-bold text-black">{{ $application->tournamentCategory->tournament->name }}</h2>
                    <p class="text-sm text-black/70">{{ $application->tournamentCategory->category_label }}</p>
                </div>

                <div class="flex flex-col gap-1 rounded-[5px] px-[15px] py-[10px] {{ $toneClasses }}">
                    <span class="text-sm font-bold">{{ $progress['label'] }}</span>
                    <span class="text-xs">{{ $progress['message'] }}</span>
                </div>

                @if ($application->status === 'rejected')
                    <div class="flex flex-col gap-1 rounded-[5px] border border-[#bd6b6c]/40 p-[15px]">
                        <span class="text-xs font-bold text-black">Reason</span>
                        <span class="text-xs text-black/80">{{ $application->rejection_reason ?: 'No reason provided.' }}</span>
                    </div>
                @endif

                <dl class="grid grid-cols-2 gap-4 text-xs text-black">
                    <div>
                        <dt class="text-black/60">{{ $application->applicationPlayers->count() > 1 ? 'Team' : 'Player' }}</dt>
                        <dd class="font-bold">{{ $application->applicationPlayers->pluck('player.name')->implode(' & ') }}</dd>
                    </div>
                    <div>
                        <dt class="text-black/60">Date Applied</dt>
                        <dd class="font-bold">{{ $application->created_at->format('F j, Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-black/60">Registration Fee</dt>
                        <dd class="font-bold">₱{{ number_format((float) $application->tournamentCategory->registration_fee, 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-black/60">Event Date</dt>
                        <dd class="font-bold">{{ $application->tournamentCategory->tournament->date_start->format('M j') }} - {{ $application->tournamentCategory->tournament->date_end->format('M j, Y') }}</dd>
                    </div>
                </dl>

                @if ($progress['canPay'])
                    <a href="{{ route('applications.payment', $application->reference_code) }}" wire:navigate
                       class="rounded-[5px] bg-[#3e948d] px-[30px] py-[10px] text-center text-xs font-medium text-white transition hover:bg-[#0d5750]">
                        {{ $progress['payLabel'] }}
                    </a>
                @endif
            </div>
        @endif
    </flux:modal>
</div>
