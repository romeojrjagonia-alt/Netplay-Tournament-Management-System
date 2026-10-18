<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => 'NetPlay — Sign Up'])
    </head>
    <body class="overflow-hidden bg-emerald-950 font-sans antialiased">
        {{-- The landing page stays visible (and inert) behind the modal. --}}
        <div aria-hidden="true" inert class="pointer-events-none select-none">
            @include('partials.landing')
        </div>

        {{-- Glass overlay + modal. Closing the modal (the X) returns to the landing page. --}}
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/70" role="dialog" aria-modal="true">
            <div class="flex min-h-full items-center justify-center p-3 sm:p-6">
                {{ $slot }}
            </div>
        </div>

        <x-system-modal />

        @fluxScripts
    </body>
</html>
