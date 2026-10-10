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
        <div class="flex min-h-screen" x-data="{ sidebarOpen: false }">
            <div x-show="sidebarOpen" x-transition.opacity x-on:click="sidebarOpen = false"
                 class="fixed inset-0 z-30 bg-black/40 lg:hidden" style="display: none;"></div>

            <aside
                class="fixed inset-y-0 left-0 z-40 flex h-dvh w-[222px] shrink-0 -translate-x-full flex-col overflow-hidden bg-white transition-transform duration-200 lg:sticky lg:top-0 lg:z-auto lg:translate-x-0 lg:self-start"
                :class="sidebarOpen && 'translate-x-0'"
            >
                <div class="flex items-center justify-between py-8 pr-4 pl-6 lg:justify-center lg:px-0">
                    <a href="{{ $brandRoute }}" wire:navigate class="bg-gradient-to-b from-[#051c1a] to-[#178279] bg-clip-text text-3xl font-bold text-transparent">
                        NetPlay
                    </a>

                    <button type="button" class="cursor-pointer text-black lg:hidden" x-on:click="sidebarOpen = false" aria-label="Close menu">
                        <flux:icon.x-mark class="size-6" />
                    </button>
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

            <div class="flex min-w-0 flex-1 flex-col bg-gradient-to-b from-[#051c1a] to-[#178279]">
                <header class="border-b border-white/50 px-4 py-4 sm:px-7">
                  <div class="mx-auto flex w-full max-w-[1440px] items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <button type="button" class="cursor-pointer text-white lg:hidden" x-on:click="sidebarOpen = true" aria-label="Open menu">
                            <flux:icon.bars-3 class="size-6" />
                        </button>

                        <h1 class="text-lg font-bold text-white sm:text-2xl">{{ $title }}</h1>
                    </div>

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
                  </div>
                </header>

                <main class="flex-1 overflow-auto p-4 sm:p-7">
                    <div class="mx-auto w-full max-w-[1440px]">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>

        <livewire:verify-email-prompt />

        <x-system-modal />

        @fluxScripts
    </body>
</html>
