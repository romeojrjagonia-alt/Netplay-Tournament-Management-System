@props(['label', 'model', 'options' => [], 'placeholder' => 'Select', 'live' => false, 'disabledOptions' => []])

<div {{ $attributes->class(['flex flex-col gap-[2px]']) }}>
    <label for="{{ $model }}" class="text-[12px] text-black">{{ $label }}</label>
    <div class="flex h-[30px] items-center rounded-[5px] bg-[rgba(17,119,110,0.15)] px-[10px]">
        <select
            id="{{ $model }}"
            wire:model{{ $live ? '.live' : '' }}="{{ $model }}"
            class="w-full bg-transparent text-[12px] text-black focus:outline-none"
        >
            {{-- A prompt, not a choice: shown until something is picked and never listed in the dropdown.
                 Hidden but NOT disabled: with no value the browser falls back to the first non-disabled
                 option, and a disabled prompt would let it display a real option the form never received. --}}
            <option value="" hidden>{{ $placeholder }}</option>
            @foreach ($options as $value => $optionLabel)
                <option value="{{ $value }}" @disabled(in_array($value, (array) $disabledOptions, true))>{{ $optionLabel }}</option>
            @endforeach
        </select>
    </div>

    @error($model)
        <p class="text-[10px] text-red-600">{{ $message }}</p>
    @enderror
</div>
