<x-mail::message>
# Payment verified

Hi {{ $player->name }}, your payment for **{{ $application->tournamentCategory->tournament->name }}** ({{ $application->tournamentCategory->category_label }}) was verified. Your entry is now fully approved.

You will receive another email once your match schedule is set.

<x-mail::button :url="route('home')">
Open NetPlay
</x-mail::button>
</x-mail::message>
