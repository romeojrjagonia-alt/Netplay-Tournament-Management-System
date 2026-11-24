@props([
    'model',
    'live' => false,
    'min' => '',
    'max' => '',
    'start' => 'days',
    'placeholder' => 'Select date',
    'variant' => 'plain',
    'size' => 'sm',
])

{{-- A date picker that drills year, then month, then day, replacing the native
     <input type="date">. It binds to the Livewire property named by `model`,
     which holds an ISO date. `start="years"` opens on the year grid first (for
     birthdates); `min` / `max` are ISO dates that grey out what can't be chosen.
     `variant="bordered"` gives it its own border for forms without a field wrapper. --}}
<div
    x-data="datePicker(@js(['model' => $model, 'live' => (bool) $live, 'min' => $min, 'max' => $max, 'start' => $start]))"
    @keydown.escape.window="close()"
    @click.outside="close()"
    {{ $attributes->class(['relative w-full min-w-0']) }}
>
    <button
        type="button"
        id="{{ $model }}"
        x-ref="trigger"
        @click="toggle()"
        :aria-expanded="open"
        aria-haspopup="dialog"
        @class([
            'flex w-full min-w-0 cursor-pointer items-center justify-between gap-2 bg-transparent text-left text-black focus:outline-none',
            'text-sm' => $size === 'sm',
            'text-xs' => $size === 'xs',
            'rounded-[5px] border border-black/20 px-2 py-1.5' => $variant === 'bordered',
        ])
    >
        <span x-text="label || @js($placeholder)" :class="label ? '' : 'text-black/50'" class="truncate"></span>
        <svg class="size-4 shrink-0 text-black/60" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.75 2a.75.75 0 0 1 .75.75V4h7V2.75a.75.75 0 0 1 1.5 0V4h.25A2.75 2.75 0 0 1 18 6.75v8.5A2.75 2.75 0 0 1 15.25 18H4.75A2.75 2.75 0 0 1 2 15.25v-8.5A2.75 2.75 0 0 1 4.75 4H5V2.75A.75.75 0 0 1 5.75 2Zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75Z" clip-rule="evenodd" />
        </svg>
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition.opacity.duration.100ms
        role="dialog"
        aria-label="Choose a date"
        :class="dropUp ? 'bottom-full mb-1.5' : 'top-full mt-1.5'"
        class="absolute left-0 z-50 w-72 rounded-xl bg-white p-4 text-black shadow-xl ring-1 ring-black/10"
    >
        <div class="flex items-center justify-between gap-2 border-b border-black/10 pb-3">
            <button type="button" @click="previous()" aria-label="Previous" class="flex size-8 cursor-pointer items-center justify-center rounded-lg border border-black/15 hover:bg-black/5">
                <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 0 1-.02 1.06L8.832 10l3.938 3.71a.75.75 0 1 1-1.04 1.08l-4.5-4.25a.75.75 0 0 1 0-1.08l4.5-4.25a.75.75 0 0 1 1.06.02Z" clip-rule="evenodd" /></svg>
            </button>

            <button type="button" @click="up()" x-text="title" :disabled="view === 'years'" class="cursor-pointer rounded-lg px-2 py-1 text-sm font-bold hover:bg-black/5 disabled:cursor-default disabled:hover:bg-transparent"></button>

            <button type="button" @click="next()" aria-label="Next" class="flex size-8 cursor-pointer items-center justify-center rounded-lg border border-black/15 hover:bg-black/5">
                <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd" /></svg>
            </button>
        </div>

        {{-- Years --}}
        <div x-show="view === 'years'" class="grid grid-cols-3 gap-1 pt-3">
            <template x-for="option in years" :key="option">
                <button
                    type="button"
                    @click="pickYear(option)"
                    :disabled="yearDisabled(option)"
                    x-text="option"
                    :class="isSelectedYear(option) ? 'bg-[#0b4742] text-white' : 'hover:bg-black/5'"
                    class="cursor-pointer rounded-lg py-2.5 text-sm disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:bg-transparent"
                ></button>
            </template>
        </div>

        {{-- Months --}}
        <div x-show="view === 'months'" class="grid grid-cols-3 gap-1 pt-3">
            <template x-for="(name, index) in months" :key="name">
                <button
                    type="button"
                    @click="pickMonth(index)"
                    :disabled="monthDisabled(index)"
                    x-text="name.slice(0, 3)"
                    :class="isSelectedMonth(index) ? 'bg-[#0b4742] text-white' : 'hover:bg-black/5'"
                    class="cursor-pointer rounded-lg py-2.5 text-sm disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:bg-transparent"
                ></button>
            </template>
        </div>

        {{-- Days --}}
        <div x-show="view === 'days'" class="pt-3">
            <div class="grid grid-cols-7 pb-1 text-center text-[11px] font-bold text-black/50">
                <template x-for="day in weekdays" :key="day"><span x-text="day"></span></template>
            </div>

            <div class="grid grid-cols-7 gap-y-1 text-center">
                <template x-for="blank in blanks" :key="'blank-' + blank"><span></span></template>
                <template x-for="day in days" :key="day">
                    <button
                        type="button"
                        @click="pickDay(day)"
                        :disabled="dayDisabled(day)"
                        x-text="day"
                        :class="[
                            isSelectedDay(day) ? 'bg-[#0b4742] text-white' : 'hover:bg-black/5',
                            isToday(day) && ! isSelectedDay(day) ? 'ring-1 ring-[#0b4742]/50' : '',
                        ]"
                        class="mx-auto flex size-8 cursor-pointer items-center justify-center rounded-lg text-sm disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:bg-transparent"
                    ></button>
                </template>
            </div>
        </div>

        <div class="mt-3 flex items-center justify-between border-t border-black/10 pt-3 text-xs">
            <button type="button" @click="clear()" class="cursor-pointer text-black/60 hover:text-black">Clear</button>
            <button type="button" @click="pickToday()" :disabled="todayDisabled" class="cursor-pointer font-bold text-[#0b4742] hover:underline disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:no-underline">Today</button>
        </div>
    </div>
</div>
