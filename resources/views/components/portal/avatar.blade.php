@props([
    'photoUrl' => null,
    'initials' => '',
])

@if ($photoUrl)
    <img src="{{ $photoUrl }}" alt="" {{ $attributes->class(['shrink-0 rounded-full object-cover']) }}>
@else
    <span {{ $attributes->class(['flex shrink-0 items-center justify-center rounded-full bg-[#e5f1f0] font-bold text-[#138177]']) }}>
        {{ $initials }}
    </span>
@endif
