<x-mail::message>
# Your application was not approved

Hi {{ $player->name }}, your application for **{{ $application->tournamentCategory->tournament->name }}** ({{ $application->tournamentCategory->category_label }}) was not approved.

@if ($application->rejection_reason)
<x-mail::panel>
Reason: {{ $application->rejection_reason }}
</x-mail::panel>
@endif

You are welcome to apply again from the NetPlay home page.

<x-mail::button :url="route('home')">
Open NetPlay
</x-mail::button>
</x-mail::message>
