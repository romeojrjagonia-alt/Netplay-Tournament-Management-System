<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => 'NetPlay — Play. Compete. Excel'])
    </head>
    <body class="bg-emerald-950 font-sans antialiased">
        @include('partials.landing')

        @fluxScripts
    </body>
</html>
