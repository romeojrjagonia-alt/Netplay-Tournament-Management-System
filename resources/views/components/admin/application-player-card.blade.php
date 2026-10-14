@props(['applicationPlayer', 'index'])

@php
    $player = $applicationPlayer->player;
    $documentUrl = $applicationPlayer->id_document_url;
    $documentTypes = ['national_id' => 'Philippine National ID', 'passport' => 'Passport', 'birth_certificate' => 'Birth Certificate'];
    $documentLabel = $documentTypes[$applicationPlayer->id_document_type] ?? 'Valid ID document';
@endphp

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

    <div class="flex w-full flex-col gap-1.5 border-t border-[#ececec] pt-3 text-[11px] text-black">
        <span class="text-black/60">Valid ID document</span>
        @if ($documentUrl)
            <a href="{{ $documentUrl }}" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-[5px] border border-[#d3d3d3] p-2 hover:bg-[#f3faf9]">
                <img src="{{ $documentUrl }}" alt="{{ $documentLabel }}" class="size-12 shrink-0 rounded-[3px] object-cover">
                <span class="flex min-w-0 flex-col">
                    <span class="font-bold">{{ $documentLabel }}</span>
                    <span class="text-[#138177] underline">View document</span>
                </span>
            </a>
        @else
            <span class="text-black/50 italic">No document uploaded</span>
        @endif
    </div>
</div>
