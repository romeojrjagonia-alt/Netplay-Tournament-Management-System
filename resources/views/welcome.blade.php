<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => 'NetPlay — Play. Compete. Excel'])
    </head>
    <body class="bg-emerald-950 font-sans antialiased">
        @include('partials.landing')

        <livewire:tournaments.register />

        @if ($registerTournamentId = (int) request()->query('register'))
            <div x-data x-init="$nextTick(() => { Livewire.dispatch('open-guest-registration', { tournamentId: {{ $registerTournamentId }} }); history.replaceState(null, '', window.location.pathname) })"></div>
        @endif

        @fluxScripts
    </body>
</html>
