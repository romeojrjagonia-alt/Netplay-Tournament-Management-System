@props(['tournament', 'active' => 'dashboard'])

@php
    $activeClasses = 'bg-white/50 text-[#9cee69] mix-blend-soft-light';
    $linkClasses = 'text-white';
@endphp

<div class="flex flex-wrap items-center justify-center gap-x-5 gap-y-1">
    <a href="{{ route('admin.events.show', $tournament) }}" wire:navigate @class(['flex h-10 items-center gap-2 overflow-hidden rounded-[3px] px-2.5 text-sm', $activeClasses => $active === 'dashboard', $linkClasses => $active !== 'dashboard'])>
        <flux:icon.squares-2x2 class="size-5 shrink-0" />
        Dashboard
    </a>

    <a href="{{ route('admin.events.schedule.index', $tournament) }}" wire:navigate @class(['flex h-10 items-center gap-2 overflow-hidden rounded-[3px] px-2.5 text-sm', $activeClasses => $active === 'schedule', $linkClasses => $active !== 'schedule'])>
        <flux:icon.share class="size-5 shrink-0" />
        Schedule Matches
    </a>

    <a href="{{ route('admin.events.officials', $tournament) }}" wire:navigate @class(['flex h-10 items-center gap-2 overflow-hidden rounded-[3px] px-2.5 text-sm', $activeClasses => $active === 'officials', $linkClasses => $active !== 'officials'])>
        <flux:icon.users class="size-5 shrink-0" />
        Officials
    </a>
</div>
