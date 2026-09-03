@props(['label', 'model', 'options' => [], 'placeholder' => 'Select', 'live' => false])

<div {{ $attributes->class(['flex flex-col gap-[2px]']) }}>
    <label for="{{ $model }}" class="text-[12px] text-black">{{ $label }}</label>
    <div class="flex h-[30px] items-center rounded-[5px] bg-[rgba(17,119,110,0.15)] px-[10px]">
        <select
            id="{{ $model }}"
            wire:model{{ $live ? '.live' : '' }}="{{ $model }}"
            class="w-full bg-transparent text-[12px] text-black focus:outline-none"
        >
            <option value="">{{ $placeholder }}</option>
            @foreach ($options as $value => $optionLabel)
                <option value="{{ $value }}">{{ $optionLabel }}</option>
            @endforeach
        </select>
    </div>

    @error($model)
        <p class="text-[10px] text-red-600">{{ $message }}</p>
    @enderror
</div>
