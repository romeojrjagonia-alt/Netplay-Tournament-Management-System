@props(['tournament', 'active' => 'dashboard'])

@php
    // No blend mode here: `mix-blend-soft-light` on the tab itself blends its
    // own label into the background and makes the active tab unreadable.
    $activeClasses = 'bg-white/20 text-[#9cee69] font-bold';
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

    @if ($tournament->is_concluded)
        <a href="{{ route('admin.events.reports', $tournament) }}" wire:navigate @class(['flex h-10 items-center gap-2 overflow-hidden rounded-[3px] px-2.5 text-sm', $activeClasses => $active === 'reports', $linkClasses => $active !== 'reports'])>
            <flux:icon.document-chart-bar class="size-5 shrink-0" />
            Reports
        </a>
    @endif
</div>
