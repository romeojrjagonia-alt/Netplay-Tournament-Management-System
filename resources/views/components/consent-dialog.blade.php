@props(['accept' => 'acceptConsent', 'decline' => 'declineConsent'])

<div {{ $attributes->class(['flex w-full flex-col gap-4 text-black']) }}
     x-data="{ agreed: false, guardian: false, declined: false }"
     role="alertdialog" aria-labelledby="consent-title" data-consent-dialog>
    <div class="flex flex-col gap-1">
        <h2 id="consent-title" class="text-xl font-bold">Data Privacy Consent</h2>
        <p class="text-xs text-neutral-700">OBC Badminton Facility · NetPlay Tournament Management System</p>
    </div>

    {{-- Shown after "Decline & Exit" is pressed, so the choice has a clear consequence. --}}
    <div x-show="declined" x-cloak class="flex flex-col gap-4">
        <div class="rounded-[8px] border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-neutral-800">
            <p class="font-bold">We can't continue without your consent.</p>
            <p class="mt-1">Without it we can't process your registration, so nothing will be saved. You can review the notice again, or exit.</p>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <button type="button" x-on:click="declined = false"
                    class="cursor-pointer rounded-[5px] border border-[#138177] px-4 py-3 text-center text-sm font-medium text-[#0b4742]">
                Review notice
            </button>
            <button type="button" wire:click="{{ $decline }}" wire:loading.attr="disabled"
                    class="cursor-pointer rounded-[5px] bg-neutral-700 px-4 py-3 text-center text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-60">
                Exit
            </button>
        </div>
    </div>

    <div x-show="!declined" class="flex flex-col gap-4">
        <div class="flex max-h-[45vh] flex-col gap-4 overflow-y-auto pr-1 text-sm text-neutral-800">
            <p>To process your registration, we collect and store:</p>

            <ul class="flex flex-col gap-3">
                <li class="flex gap-3">
                    <flux:icon.user-circle class="mt-0.5 size-5 shrink-0 text-[#138177]" />
                    <span><strong>Profile &amp; contact info:</strong> name, birth date, gender, address, phone number, email, club and skill level.</span>
                </li>
                <li class="flex gap-3">
                    <flux:icon.identification class="mt-0.5 size-5 shrink-0 text-[#138177]" />
                    <span><strong>Identity verification:</strong> a photo of a valid ID. We use it only to confirm who you are and your age bracket, which keeps every category fair (for example, stopping players from entering a lower level or age group than they belong in).</span>
                </li>
                <li class="flex gap-3">
                    <flux:icon.trophy class="mt-0.5 size-5 shrink-0 text-[#138177]" />
                    <span><strong>Tournament records:</strong> your match results and performance statistics.</span>
                </li>
            </ul>

            <div class="flex gap-3 rounded-[8px] bg-[#eaf4f3] px-3 py-3">
                <flux:icon.shield-check class="mt-0.5 size-5 shrink-0 text-[#138177]" />
                <div class="flex flex-col gap-2">
                    <p>
                        <strong>How we use and protect it:</strong> your information is used only to run tournaments: organizing draws,
                        verifying entries and contacting you about your applications. Only authorized facility organizers and
                        umpires can see it, and it is never sold or shared with third parties.
                    </p>
                    <p>
                        <strong>How long we keep it:</strong> for as long as your account and tournament records exist.
                    </p>
                    <p>
                        <strong>Your rights:</strong> you may ask the facility organizers to show, correct or delete your data,
                        or withdraw this consent, at any time.
                    </p>
                </div>
            </div>

            <p>
                <a href="{{ route('privacy') }}" target="_blank" rel="noopener" class="font-medium text-[#0b4742] underline">Read our full Privacy Policy</a>
                for details on retention and security.
            </p>
        </div>

        <div class="flex flex-col gap-2 border-t border-black/10 pt-3 text-sm text-neutral-800">
            <label class="flex cursor-pointer items-start gap-2">
                <input type="checkbox" x-model="agreed" class="mt-0.5 size-4 shrink-0 cursor-pointer accent-[#138177]">
                <span>I have read and agree to the collection and use of my data as described above.</span>
            </label>
            <label class="flex cursor-pointer items-start gap-2">
                <input type="checkbox" x-model="guardian" class="mt-0.5 size-4 shrink-0 cursor-pointer accent-[#138177]">
                <span>If I am registering a minor (under 18), I confirm I am their parent or legal guardian.</span>
            </label>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <button type="button" x-on:click="declined = true"
                    class="cursor-pointer rounded-[5px] border border-neutral-500 px-4 py-3 text-center text-sm font-medium text-neutral-800">
                Decline &amp; Exit
            </button>
            <button type="button" wire:click="{{ $accept }}" wire:loading.attr="disabled" x-bind:disabled="!agreed"
                    class="cursor-pointer rounded-[5px] bg-[#00a89a] px-4 py-3 text-center text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-50">
                I Consent &amp; Continue
            </button>
        </div>
    </div>
</div>
