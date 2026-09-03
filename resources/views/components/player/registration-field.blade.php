@props(['label', 'model', 'placeholder' => '', 'type' => 'text', 'live' => false])

<div {{ $attributes->class(['flex flex-col gap-[2px]']) }}>
    <label for="{{ $model }}" class="text-[12px] text-black">{{ $label }}</label>
    <div class="flex h-[30px] items-center gap-[10px] rounded-[5px] bg-[#eaf4f3] px-[10px]">
        <input
            id="{{ $model }}"
            wire:model{{ $live ? '.live' : '' }}="{{ $model }}"
            type="{{ $type }}"
            placeholder="{{ $placeholder }}"
            class="w-full bg-transparent text-[12px] text-black placeholder:text-black/50 focus:outline-none"
        >
    </div>

    @error($model)
        <p class="text-[10px] text-red-600">{{ $message }}</p>
    @enderror
</div>
