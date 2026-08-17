@props(['title' => 'Dashboard'])

<x-layouts.portal
    :title="$title"
    :brand-route="route('player.dashboard')"
    :role-name="auth()->user()->name"
    role-label="PLAYER"
    suffix="NetPlay"
>
    <x-slot:nav>
        <x-portal.sidebar-link icon="layout-grid" :href="route('player.dashboard')" :active="request()->routeIs('player.dashboard')" wire:navigate>
            Dashboard
        </x-portal.sidebar-link>

        <p class="px-2.5 pt-4 pb-1 text-xs text-black">Tournament</p>
        <x-portal.sidebar-link icon="flag" href="#">Events</x-portal.sidebar-link>
        <x-portal.sidebar-link icon="trophy" href="#">My Matches</x-portal.sidebar-link>

        <x-portal.sidebar-link icon="folder" href="#">My Applications</x-portal.sidebar-link>
    </x-slot:nav>

    {{ $slot }}
</x-layouts.portal>
