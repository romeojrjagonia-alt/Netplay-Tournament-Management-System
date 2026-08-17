@props(['title' => 'Dashboard'])

<x-layouts.portal
    :title="$title"
    :brand-route="route('admin.dashboard')"
    role-name="Administrator"
    role-label="ADMIN USER"
    suffix="NetPlay Admin"
>
    <x-slot:nav>
        <x-portal.sidebar-link icon="layout-grid" :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')" wire:navigate>
            Dashboard
        </x-portal.sidebar-link>

        <p class="px-2.5 pt-4 pb-1 text-xs text-black">Tournament</p>
        <x-portal.sidebar-link icon="flag" href="#">Events</x-portal.sidebar-link>
        <x-portal.sidebar-link icon="plus-circle" href="#">Create New Event</x-portal.sidebar-link>

        <p class="px-2.5 pt-4 pb-1 text-xs text-black">System</p>
        <x-portal.sidebar-link icon="users" href="#">Users</x-portal.sidebar-link>
    </x-slot:nav>

    {{ $slot }}
</x-layouts.portal>
