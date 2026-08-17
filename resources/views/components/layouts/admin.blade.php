<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => ($title ?? 'Dashboard') . ' — NetPlay Admin'])
    </head>
    <body class="min-h-screen bg-white font-sans antialiased">
        <div class="flex min-h-screen">
            <aside class="flex w-[222px] shrink-0 flex-col bg-white">
                <div class="flex items-center justify-center py-8">
                    <a href="{{ route('admin.dashboard') }}" wire:navigate class="bg-gradient-to-b from-[#051c1a] to-[#178279] bg-clip-text text-3xl font-bold text-transparent">
                        NetPlay
                    </a>
                </div>

                <nav class="flex flex-1 flex-col gap-1.5 px-2.5">
                    <x-admin.sidebar-link icon="layout-grid" :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')" wire:navigate>
                        Dashboard
                    </x-admin.sidebar-link>

                    <p class="px-2.5 pt-4 pb-1 text-xs text-black">Tournament</p>
                    <x-admin.sidebar-link icon="flag" href="#">Events</x-admin.sidebar-link>
                    <x-admin.sidebar-link icon="plus-circle" href="#">Create New Event</x-admin.sidebar-link>

                    <p class="px-2.5 pt-4 pb-1 text-xs text-black">System</p>
                    <x-admin.sidebar-link icon="users" href="#">Users</x-admin.sidebar-link>
                </nav>

                <div class="border-t-[1.5px] border-[#ebebeb] p-2.5">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="grid w-full grid-cols-[27px_1fr] items-center gap-2.5 p-2.5 text-left text-sm text-black hover:bg-[#f5faf9]">
                            <flux:icon name="arrow-right-start-on-rectangle" class="col-start-1 size-5 justify-self-center" />
                            <span class="col-start-2">Sign Out</span>
                        </button>
                    </form>
                </div>
            </aside>

            <div class="flex flex-1 flex-col bg-gradient-to-b from-[#051c1a] to-[#178279]">
                <header class="flex items-center justify-between border-b border-white/50 px-7 py-4">
                    <h1 class="text-2xl font-bold text-white">{{ $title ?? 'Dashboard' }}</h1>

                    <div class="flex h-[38px] items-center gap-1.5 rounded-full bg-white py-0.5 pr-5 pl-0.5">
                        <span class="flex size-[34px] items-center justify-center rounded-full bg-[#e5f1f0] text-[10px] font-bold text-[#138177]">
                            {{ auth()->user()->initials() }}
                        </span>
                        <span class="flex flex-col justify-center">
                            <span class="text-[10px] font-bold text-black">Administrator</span>
                            <span class="text-[6px] tracking-wide text-black">ADMIN USER</span>
                        </span>
                    </div>
                </header>

                <main class="flex-1 overflow-auto p-7">
                    {{ $slot }}
                </main>
            </div>
        </div>

        @fluxScripts
    </body>
</html>
