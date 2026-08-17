@props([
    'name',
    'status',
    'note' => null,
])

<div {{ $attributes->class(['flex items-center gap-6 rounded-[5px] border border-[#d3d3d3] bg-white px-[54px] py-5']) }}>
    <div class="flex flex-1 flex-col gap-2.5">
        <span class="text-xs font-bold text-black">{{ $name }}</span>
        <span class="text-[10px] text-black">{{ $status }}</span>
    </div>

    @if ($note)
        <p class="shrink-0 text-xs text-black italic">{{ $note }}</p>
    @endif

    <button type="button" class="shrink-0 text-black" aria-label="Application options for {{ $name }}">
        <flux:icon.ellipsis-vertical class="size-5" />
    </button>
</div>
