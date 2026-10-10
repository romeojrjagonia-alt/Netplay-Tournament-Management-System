@php
    $tournamentCategory = $match->tournamentCategory;
    $opponent = $match->matchApplications
        ->map(fn ($matchApplication) => $matchApplication->application)
        ->first(fn ($application) => $application && ! $application->applicationPlayers->contains('player_id', $player->id));
    $opponentLabel = $opponent?->applicationPlayers->map(fn ($applicationPlayer) => $applicationPlayer->player?->name)->filter()->implode(' / ');
@endphp
<x-mail::message>
# Your match is scheduled

Hi {{ $player->name }}, your match in **{{ $tournamentCategory->tournament->name }}** ({{ $tournamentCategory->category_label }}) has been scheduled.

<x-mail::panel>
**Round {{ $match->round }}**@if ($opponentLabel) vs **{{ $opponentLabel }}**@endif

When: **{{ $match->scheduled_at->format('l, F j, Y \a\t g:i A') }}**

@if ($match->court)
Court: **{{ $match->court }}**
@endif
</x-mail::panel>

Please arrive at least 15 minutes before your match. If anything changes you will receive another email.

<x-mail::button :url="route('home')">
Open NetPlay
</x-mail::button>
</x-mail::message>
