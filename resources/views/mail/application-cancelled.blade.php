<x-mail::message>
# Your application was cancelled

Hi {{ $playerName }}, the application you were part of for **{{ $tournamentName }}** ({{ $categoryLabel }}) has been cancelled, so it no longer holds a slot.

You are welcome to apply again from the NetPlay home page while registration is open.

<x-mail::button :url="route('home')">
Open NetPlay
</x-mail::button>
</x-mail::message>
