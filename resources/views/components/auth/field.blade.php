@props(['label', 'model', 'placeholder' => '', 'type' => 'text', 'start' => 'days', 'min' => '', 'max' => ''])

@php $isPhone = $type === 'tel' || str_contains(strtolower($model), 'phone'); @endphp

<div {{ $attributes->class(['flex flex-col gap-0.5']) }}>
    <label for="{{ $model }}" class="text-xs text-black">{{ $label }}</label>
    <div class="flex items-center gap-2 rounded-[5px] bg-[#11776e]/15 px-2.5 py-2.5">
        @if ($type === 'date')
            <x-date-picker :model="$model" :start="$start" :min="$min" :max="$max" size="xs" />
        @else
            <input
                id="{{ $model }}"
                wire:model="{{ $model }}"
                type="{{ $isPhone ? 'tel' : $type }}"
                @if ($isPhone) inputmode="numeric" maxlength="11" autocomplete="tel" oninput="this.value = this.value.replace(/\D/g, '').slice(0, 11)" @endif
                placeholder="{{ $placeholder }}"
                class="w-full min-w-0 bg-transparent text-xs text-black placeholder:text-black/50 focus:outline-none"
            >
        @endif

        {{ $slot ?? '' }}
    </div>

    @error($model)
        <p class="text-[11px] text-red-600">{{ $message }}</p>
    @enderror
</div>
