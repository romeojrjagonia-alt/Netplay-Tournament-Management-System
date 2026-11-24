<x-layouts.public title="Privacy Policy" max-width="max-w-3xl">
    <article class="flex flex-col gap-6 rounded-[10px] bg-white p-6 text-sm leading-6 text-neutral-800 shadow-sm sm:p-8">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-bold text-black">Privacy Policy</h1>
            <p class="text-xs text-neutral-700">OBC Badminton Facility · NetPlay Tournament Management System</p>
        </header>

        <section class="flex flex-col gap-2">
            <h2 class="text-base font-bold text-black">What we collect</h2>
            <ul class="list-disc pl-5">
                <li><strong>Profile and contact information:</strong> name, birth date, gender, address, phone number, email, club and skill level.</li>
                <li><strong>Identity verification:</strong> a photo of a valid ID.</li>
                <li><strong>Tournament records:</strong> your applications, payments, match results and performance statistics.</li>
            </ul>
        </section>

        <section class="flex flex-col gap-2">
            <h2 class="text-base font-bold text-black">Why we collect it</h2>
            <p>
                We use your information only to run tournaments: verifying entries, grouping players into the right
                categories and draws, contacting you about your applications, and recording results. Your valid ID is
                used only to confirm your identity and age bracket, so categories stay fair.
            </p>
        </section>

        <section class="flex flex-col gap-2">
            <h2 class="text-base font-bold text-black">Who can see it</h2>
            <p>
                Only authorized facility organizers and umpires. We never sell your information or share it with third parties.
            </p>
        </section>

        <section class="flex flex-col gap-2">
            <h2 class="text-base font-bold text-black">How long we keep it</h2>
            <p>
                We keep your information for as long as your account and tournament records exist. If you ask us to
                delete your data, we delete it, including your ID photo.
            </p>
        </section>

        <section class="flex flex-col gap-2">
            <h2 class="text-base font-bold text-black">Your rights</h2>
            <p>
                You may ask the facility organizers to show you the data we hold about you, correct it, or delete it,
                and your consent may be withdrawn at any time.
            </p>
        </section>

        <section class="flex flex-col gap-2">
            <h2 class="text-base font-bold text-black">Security</h2>
            <p>
                Accounts are protected by passwords you set yourself, and uploaded documents are not publicly listed.
                Access is limited to authorized staff.
            </p>
        </section>

        <section class="flex flex-col gap-2">
            <h2 class="text-base font-bold text-black">Minors</h2>
            <p>
                If you register a player under 18, you confirm that you are their parent or legal guardian and that
                you consent on their behalf.
            </p>
        </section>

        <section class="flex flex-col gap-2">
            <h2 class="text-base font-bold text-black">Contact</h2>
            <p>To make a request about your data, contact the OBC Badminton Facility organizers in Ozamiz City.</p>
        </section>

        <div class="border-t border-black/10 pt-5">
            <a href="{{ auth()->check() ? route(auth()->user()->homeRouteName()) : route('home') }}"
               class="inline-flex items-center gap-2 rounded-[5px] bg-[#0b4742] px-6 py-2.5 text-sm font-medium text-white transition hover:bg-[#0d5750]">
                <flux:icon.arrow-left class="size-4" />
                Back to NetPlay
            </a>
        </div>
    </article>
</x-layouts.public>
