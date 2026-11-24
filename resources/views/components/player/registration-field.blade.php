@props(['label', 'model', 'placeholder' => '', 'type' => 'text', 'live' => false, 'start' => 'days', 'min' => '', 'max' => ''])

@php $isPhone = $type === 'tel' || str_contains(strtolower($model), 'phone'); @endphp

<div {{ $attributes->class(['flex flex-col gap-[2px]']) }}>
    <label for="{{ $model }}" class="text-sm text-black">{{ $label }}</label>
    <div class="flex h-11 items-center gap-[10px] rounded-[5px] bg-[#eaf4f3] px-[10px]">
        @if ($type === 'date')
            <x-date-picker :model="$model" :live="$live" :start="$start" :min="$min" :max="$max" />
        @else
            <input
                id="{{ $model }}"
                wire:model{{ $live ? '.live' : '' }}="{{ $model }}"
                type="{{ $isPhone ? 'tel' : $type }}"
                @if ($isPhone) inputmode="numeric" maxlength="11" autocomplete="tel" oninput="this.value = this.value.replace(/\D/g, '').slice(0, 11)" @endif
                placeholder="{{ $placeholder }}"
                class="w-full bg-transparent text-sm text-black placeholder:text-black/50 focus:outline-none"
            >
        @endif
    </div>

    @error($model)
        <p class="text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
