@props(['placeholder' => 'Search'])

{{-- Icon button on mobile that reveals the search field; a plain field from sm up. --}}
<div x-data="{ open: false }" class="contents">
    <button type="button" x-on:click="open = !open; if (open) $nextTick(() => $refs.input.focus())"
            aria-label="Search" :aria-expanded="open"
            class="flex size-10 shrink-0 cursor-pointer items-center justify-center rounded-full bg-white text-[#0b4742] sm:hidden">
        <flux:icon.magnifying-glass class="size-[18px]" />
    </button>

    <div :class="open ? 'flex' : 'hidden sm:flex'"
         class="order-last w-full items-center gap-2.5 rounded-[10px] bg-white px-5 py-2.5 sm:order-none sm:w-[265px]">
        <flux:icon.magnifying-glass class="size-[17px] shrink-0 text-black/50" />
        <input x-ref="input" type="search" {{ $attributes->whereStartsWith('wire:model') }} placeholder="{{ $placeholder }}"
               class="w-full min-w-0 bg-transparent text-xs text-black placeholder:text-black/50 focus:outline-none">
    </div>
</div>
