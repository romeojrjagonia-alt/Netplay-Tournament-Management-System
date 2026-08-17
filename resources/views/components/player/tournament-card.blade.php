@props([
    'label',
    'name',
    'dates',
    'time',
    'days' => [],
    'badge' => null,
    'badgePlacement' => 'inline',
])

{{-- Tournament banner. `badgePlacement` matches Figma's two arrangements:
     the wide hero keeps its status pill inline on the right, the narrower
     "open for registration" cards float it above the details. --}}
<div {{ $attributes->class(['relative flex flex-col justify-end overflow-hidden rounded-[5px] p-5']) }}>
    <div class="pointer-events-none absolute inset-0 rounded-[5px] bg-white mix-blend-soft-light"></div>

    @if ($badge && $badgePlacement === 'top')
        <div class="relative mb-auto flex justify-end">
            <span class="rounded-[10px] bg-[#096] px-2.5 py-1 text-[7px] font-bold text-white">{{ $badge }}</span>
        </div>
    @endif

    <div class="relative flex flex-col gap-[15px]">
        <div class="flex flex-col">
            <span class="text-[8px] leading-5 text-white">{{ $label }}</span>
            <span class="text-lg leading-5 font-bold text-white">{{ $name }}</span>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-5">
                <span class="flex items-center gap-2.5 text-xs text-white">
                    <flux:icon.calendar class="size-[15px] shrink-0" />
                    {{ $dates }}
                </span>

                <span class="flex items-center gap-2.5 text-xs text-white">
                    <flux:icon.clock class="size-[15px] shrink-0" />
                    {{ $time }}
                </span>

                @if ($days)
                    <span class="flex items-center gap-1 p-[5px]">
                        @foreach ($days as $day)
                            <span class="rounded-[5px] bg-[#9cee69] px-2.5 py-0.5 text-[10px] font-bold text-[#051c1a]">{{ $day }}</span>
                        @endforeach
                    </span>
                @endif
            </div>

            @if ($badge && $badgePlacement === 'inline')
                <span class="rounded-[15px] bg-[#096] px-[15px] py-1.5 text-[10px] font-bold text-white">{{ $badge }}</span>
            @endif
        </div>
    </div>
</div>
