@props([
    'title' => 'Dashboard',
    'brandRoute' => '#',
    'roleName' => '',
    'roleLabel' => '',
    'suffix' => 'NetPlay',
    'nav' => null,
    'profilePhotoUrl' => null,
])

{{--
    Shared authenticated shell for the admin and player portals.
    Both roles use the same chrome in Figma — a white 222px sidebar,
    gradient content area, and a header carrying the page title plus a
    user pill — so the nav items are the only per-role difference.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => $title.' — '.$suffix])
    </head>
    <body class="min-h-screen bg-white font-sans antialiased">
        <div class="flex min-h-screen">
            <aside class="flex w-[222px] shrink-0 flex-col bg-white">
                <div class="flex items-center justify-center py-8">
                    <a href="{{ $brandRoute }}" wire:navigate class="bg-gradient-to-b from-[#051c1a] to-[#178279] bg-clip-text text-3xl font-bold text-transparent">
                        NetPlay
                    </a>
                </div>

                <nav class="flex flex-1 flex-col gap-1.5 px-2.5">
                    {{ $nav }}
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
                    <h1 class="text-2xl font-bold text-white">{{ $title }}</h1>

                    <div>
                        @isset($profileModal)
                            <flux:modal.trigger name="player-profile">
                                <button type="button" class="flex h-[38px] cursor-pointer items-center gap-1.5 rounded-full bg-white py-0.5 pr-5 pl-0.5" aria-label="Open your profile">
                                    <x-portal.avatar :photo-url="$profilePhotoUrl" :initials="auth()->user()->initials()" class="size-[34px] text-[10px]" />
                                    <span class="flex flex-col justify-center text-left" x-data="{ roleName: @js($roleName) }" x-on:profile-updated.window="roleName = $event.detail.name">
                                        <span class="text-[10px] font-bold text-black" x-text="roleName"></span>
                                        <span class="text-[6px] tracking-wide text-black">{{ $roleLabel }}</span>
                                    </span>
                                </button>
                            </flux:modal.trigger>

                            {{ $profileModal }}
                        @else
                            <div class="flex h-[38px] items-center gap-1.5 rounded-full bg-white py-0.5 pr-5 pl-0.5">
                                <x-portal.avatar :photo-url="$profilePhotoUrl" :initials="auth()->user()->initials()" class="size-[34px] text-[10px]" />
                                <span class="flex flex-col justify-center">
                                    <span class="text-[10px] font-bold text-black">{{ $roleName }}</span>
                                    <span class="text-[6px] tracking-wide text-black">{{ $roleLabel }}</span>
                                </span>
                            </div>
                        @endisset
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
