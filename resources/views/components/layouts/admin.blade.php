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
        <x-portal.sidebar-link icon="flag" :href="route('admin.events.index')" :active="request()->routeIs('admin.events.index', 'admin.events.show')" wire:navigate>
            Events
        </x-portal.sidebar-link>
        <x-portal.sidebar-link icon="plus-circle" :href="route('admin.events.create')" :active="request()->routeIs('admin.events.create', 'admin.events.wizard.*')" wire:navigate>
            Create New Event
        </x-portal.sidebar-link>

        <x-portal.sidebar-link icon="squares-2x2" :href="route('admin.categories.index')" :active="request()->routeIs('admin.categories.index')" wire:navigate>
            Create Categories
        </x-portal.sidebar-link>

        <p class="px-2.5 pt-4 pb-1 text-xs text-black">System</p>
        <x-portal.sidebar-link icon="users" :href="route('admin.users.index')" :active="request()->routeIs('admin.users.index')" wire:navigate>
            Users
        </x-portal.sidebar-link>
    </x-slot:nav>

    {{ $slot }}
</x-layouts.portal>
