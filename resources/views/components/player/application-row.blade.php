@props([
    'name',
    'status',
    'note' => null,
    'href' => null,
])

{{-- With an `href` the whole row is a link; otherwise it is a plain row the caller makes clickable. --}}
<{{ $href ? 'a' : 'div' }} @if ($href) href="{{ $href }}" wire:navigate @endif {{ $attributes->class(['flex items-center gap-3 rounded-[5px] border border-[#d3d3d3] bg-white px-4 py-5 sm:gap-6 sm:px-[54px]', 'cursor-pointer transition hover:bg-[#f3faf9]' => $href]) }}>
    <div class="flex min-w-0 flex-1 flex-col gap-2.5">
        <span class="text-xs font-bold text-black">{{ $name }}</span>
        <span class="text-[10px] text-black">{{ $status }}</span>
    </div>

    @if ($note)
        <p class="max-w-[40%] min-w-0 text-xs text-black italic sm:max-w-none sm:shrink-0">{{ $note }}</p>
    @endif

    <span class="shrink-0 text-black" aria-hidden="true">
        <flux:icon.chevron-right class="size-5" />
    </span>
</{{ $href ? 'a' : 'div' }}>
