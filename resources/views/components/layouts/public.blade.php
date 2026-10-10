@props(['title' => 'NetPlay'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => $title.' — NetPlay'])
    </head>
    <body class="min-h-screen bg-gradient-to-b from-teal-50 to-white font-sans antialiased">
        <div class="mx-auto flex min-h-svh w-full max-w-3xl flex-col gap-6 px-4 py-8 sm:px-6">
            <a href="{{ route('home') }}" wire:navigate class="w-fit bg-gradient-to-b from-[#051c1a] to-[#178279] bg-clip-text text-3xl font-bold text-transparent">
                NetPlay
            </a>

            {{ $slot }}
        </div>

        <x-system-modal />

        @fluxScripts
    </body>
</html>
