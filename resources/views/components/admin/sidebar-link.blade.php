@props(['icon', 'href' => '#', 'active' => false])

<a
    href="{{ $href }}"
    {{ $attributes->class([
        'grid w-full grid-cols-[27px_1fr] items-center gap-2.5 border-l-[3px] p-2.5 text-sm',
        'border-[#3e948d] bg-white font-medium text-[#3e948d]' => $active,
        'border-transparent text-black hover:bg-[#f5faf9]' => ! $active,
    ]) }}
>
    <flux:icon :name="$icon" class="col-start-1 size-5 justify-self-center" />
    <span class="col-start-2">{{ $slot }}</span>
</a>
