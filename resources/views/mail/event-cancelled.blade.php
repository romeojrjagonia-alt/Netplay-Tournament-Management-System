<x-mail::message>
# This event was cancelled

Hi {{ $playerName }}, **{{ $tournamentName }}** has been cancelled by the organizers, so your application for it no longer stands.

For questions about payments already made, please contact the OBC Badminton Facility staff.

<x-mail::button :url="route('home')">
Open NetPlay
</x-mail::button>
</x-mail::message>
