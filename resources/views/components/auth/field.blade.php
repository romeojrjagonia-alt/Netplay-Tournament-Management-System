@props(['label', 'model', 'placeholder' => '', 'type' => 'text'])

<div {{ $attributes->class(['flex flex-col gap-0.5']) }}>
    <label for="{{ $model }}" class="text-xs text-black">{{ $label }}</label>
    <div class="flex items-center gap-2 rounded-[5px] bg-[#11776e]/15 px-2.5 py-2.5">
        <input
            id="{{ $model }}"
            wire:model="{{ $model }}"
            type="{{ $type }}"
            placeholder="{{ $placeholder }}"
            class="w-full min-w-0 bg-transparent text-xs text-black placeholder:text-black/50 focus:outline-none"
        >

        {{ $slot ?? '' }}
    </div>

    @error($model)
        <p class="text-[11px] text-red-600">{{ $message }}</p>
    @enderror
</div>
