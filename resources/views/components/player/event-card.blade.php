@props([
    'name',
    'posterUrl' => null,
    'dates',
    'days' => null,
    'time' => null,
    'deadline' => null,
])

<div {{ $attributes->class(['relative flex cursor-pointer flex-col gap-3 overflow-hidden rounded-[5px] p-2.5 sm:flex-row sm:gap-5']) }}>
    <div class="pointer-events-none absolute inset-0 rounded-[5px] bg-white/50 mix-blend-soft-light"></div>

    <div class="relative h-[160px] w-full shrink-0 overflow-hidden rounded-[5px] bg-white/20 sm:h-[146px] sm:w-[280px]">
        @if ($posterUrl)
            <img src="{{ $posterUrl }}" alt="" class="size-full object-cover">
        @endif
    </div>

    <div class="relative flex min-w-0 flex-1 flex-col justify-between gap-4 px-1 py-1">
        <div class="flex flex-col gap-2.5">
            <span class="text-base font-bold text-white">{{ $name }}</span>

            <div class="flex flex-wrap items-center gap-6">
                <span class="flex items-center gap-2.5 text-xs text-white">
                    <flux:icon.calendar class="size-[15px] shrink-0" />
                    {{ $dates }}
                </span>

                @if ($days)
                    <span class="text-xs text-white">{{ $days }}</span>
                @endif
            </div>

            @if ($time)
                <span class="flex items-center gap-2.5 text-xs text-white">
                    <flux:icon.clock class="size-[15px] shrink-0" />
                    {{ $time }}
                </span>
            @endif
        </div>

        @if ($deadline)
            <span class="w-fit rounded-[5px] bg-[#096] px-4 py-1.5 text-[10px] text-white">
                ACCEPTING REGISTRATIONS TILL <span class="font-bold">{{ $deadline }}</span>
            </span>
        @endif
    </div>
</div>
