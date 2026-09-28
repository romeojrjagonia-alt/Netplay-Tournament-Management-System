@props(['title' => 'Dashboard'])

<x-layouts.portal
    :title="$title"
    :brand-route="route('umpire.dashboard')"
    :role-name="auth()->user()->name"
    role-label="UMPIRE"
    suffix="NetPlay Umpire"
>
    <x-slot:nav>
        <x-portal.sidebar-link icon="layout-grid" :href="route('umpire.dashboard')" :active="request()->routeIs('umpire.dashboard')" wire:navigate>
            Dashboard
        </x-portal.sidebar-link>

        <p class="px-2.5 pt-4 pb-1 text-xs text-black">Tournament</p>
        <x-portal.sidebar-link icon="flag" :href="route('umpire.events.index')" :active="request()->routeIs('umpire.events.*')" wire:navigate>
            Events
        </x-portal.sidebar-link>
        <x-portal.sidebar-link icon="clipboard-document-check" :href="route('umpire.matches')" :active="request()->routeIs('umpire.matches')" wire:navigate>
            Assigned Matches
        </x-portal.sidebar-link>
    </x-slot:nav>

    {{ $slot }}
</x-layouts.portal>
