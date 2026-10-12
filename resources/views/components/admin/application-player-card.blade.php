@props(['applicationPlayer', 'index'])

@php $player = $applicationPlayer->player; @endphp

<div {{ $attributes->class(['flex w-full max-w-[284px] shrink-0 flex-col gap-[15px] rounded-[5px] border border-[#d3d3d3] px-5 pt-2.5 pb-5']) }}>
    <div class="flex w-full flex-col gap-[5px]">
        <p class="text-[9px] text-black">Player {{ $index }}</p>
        <div class="flex items-center gap-3">
            <div class="flex size-[35px] shrink-0 items-center justify-center rounded-full bg-[#138177]">
                <span class="text-[17px] font-bold text-white">{{ mb_substr($player->initials(), 0, 2) }}</span>
            </div>
            <div class="flex flex-col text-black">
                <span class="text-xs font-bold">{{ $player->name }}</span>
                <span class="text-[10px]">{{ $applicationPlayer->level_at_apply }}</span>
            </div>
        </div>
    </div>

    <div class="flex w-full flex-col gap-1 text-[11px] text-black">
        <div class="flex items-start justify-between gap-4">
            <span class="shrink-0">Birthday</span>
            <span class="min-w-0 text-right font-bold break-words">{{ $player->birth_date?->format('F j, Y') ?? '—' }}</span>
        </div>
        <div class="flex items-start justify-between gap-4">
            <span class="shrink-0">Age</span>
            <span class="min-w-0 text-right font-bold break-words">{{ $player->age ? $player->age.' yrs. old' : '—' }}</span>
        </div>
        <div class="flex items-start justify-between gap-4">
            <span class="shrink-0">Gender</span>
            <span class="min-w-0 text-right font-bold break-words">{{ $player->gender ? ucfirst($player->gender) : '—' }}</span>
        </div>
        <div class="flex items-start justify-between gap-4">
            <span class="shrink-0">Contact Number</span>
            <span class="min-w-0 text-right font-bold break-words">{{ $player->phone_number ?? '—' }}</span>
        </div>
        <div class="flex items-start justify-between gap-4">
            <span class="shrink-0">Address</span>
            <span class="min-w-0 text-right font-bold break-words">{{ $player->address ?? '—' }}</span>
        </div>
    </div>
</div>
