<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => 'NetPlay — Sign In'])
    </head>
    <body class="min-h-screen bg-gradient-to-b from-teal-50 to-white font-sans antialiased">
        <div class="flex min-h-svh items-center justify-center p-6">
            {{ $slot }}
        </div>
        @fluxScripts
    </body>
</html>
