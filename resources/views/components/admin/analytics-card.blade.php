@props(['label', 'value'])

<div {{ $attributes->class(['relative flex h-[118px] items-center justify-center overflow-hidden rounded-[5px] px-8 py-4 text-center']) }}>
    <div class="absolute inset-0 bg-white mix-blend-soft-light"></div>
    <div class="relative flex flex-col items-center gap-1.5">
        <span class="text-xs text-white">{{ $label }}</span>
        <span class="text-3xl font-bold text-white">{{ $value }}</span>
    </div>
</div>
