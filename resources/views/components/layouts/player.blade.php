@props(['title' => 'Dashboard'])

<x-layouts.portal
    :title="$title"
    :brand-route="route('player.dashboard')"
    :role-name="auth()->user()->name"
    role-label="PLAYER"
    suffix="NetPlay"
    :profile-photo-url="auth()->user()->player?->photo_url"
>
    <x-slot:profileModal>
        <livewire:player.profile-modal />
    </x-slot:profileModal>

    <x-slot:nav>
        <x-portal.sidebar-link icon="layout-grid" :href="route('player.dashboard')" :active="request()->routeIs('player.dashboard')" wire:navigate>
            Dashboard
        </x-portal.sidebar-link>

        <p class="px-2.5 pt-4 pb-1 text-xs text-black">Tournament</p>
        <x-portal.sidebar-link icon="flag" :href="route('player.events')" :active="request()->routeIs('player.events')" wire:navigate>Events</x-portal.sidebar-link>
        <x-portal.sidebar-link icon="trophy" :href="route('player.matches')" :active="request()->routeIs('player.matches')" wire:navigate>My Matches</x-portal.sidebar-link>
    </x-slot:nav>

    {{ $slot }}
</x-layouts.portal>
