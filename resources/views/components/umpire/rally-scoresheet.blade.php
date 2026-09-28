@props(['match'])

@php
    use App\Support\Badminton\GameRules;

    $match->loadMissing([
        'tournamentCategory.tournament',
        'tournamentCategory.category',
        'matchApplications.application.applicationPlayers.player',
        'games.rallies',
        'assignedUmpire',
    ]);

    $tournament = $match->tournamentCategory->tournament;
    $sets = $match->scoresheetSets();
    $sideLabels = ['A' => $match->sideLabel('A') ?: 'Team A', 'B' => $match->sideLabel('B') ?: 'Team B'];
    $winnerSide = $match->winnerSide();
    $lineJudges = collect([$match->line_judge_1_name, $match->line_judge_2_name])->filter()->implode(', ');
    $columns = range(1, GameRules::POINT_CAP + 1);
    $span = count($columns) + 1;
@endphp

{{-- Mirrors OBC's paper score sheet: title, GAME NO / COURT / BRACKET, CATEGORY / TIME,
     a 1-31 point grid per set (one row per side, a side's cells filled up to its score),
     then UMPIRE / Signature / Signature / WINNER. One table, so borders line up. --}}
<div {{ $attributes->class(['overflow-x-auto']) }}>
    <table class="w-full min-w-[860px] table-fixed border-collapse border border-black text-black [-webkit-print-color-adjust:exact] [print-color-adjust:exact]">
        <colgroup>
            <col style="width: 150px">
            @foreach ($columns as $number)
                <col>
            @endforeach
        </colgroup>

        <tbody>
            <tr>
                <td colspan="{{ $span }}" class="border border-black px-2 py-2 text-center">
                    <p class="text-base font-extrabold tracking-wide uppercase">{{ $tournament->name }}</p>
                    <p class="text-xs">{{ $tournament->date_range_label }}</p>
                </td>
            </tr>

            <tr class="text-xs">
                <td colspan="11" class="border border-black px-2 py-2"><span class="font-bold">GAME NO</span> <span class="ml-2">{{ $match->id }}</span></td>
                <td colspan="11" class="border border-black px-2 py-2"><span class="font-bold">COURT</span> <span class="ml-2">{{ $match->court }}</span></td>
                <td colspan="10" class="border border-black px-2 py-2"><span class="font-bold">BRACKET</span> <span class="ml-2">{{ str($match->bracket_type)->headline() }}</span></td>
            </tr>

            <tr class="text-xs">
                <td colspan="22" class="border border-black px-2 py-2"><span class="font-bold">CATEGORY</span> <span class="ml-2">{{ $match->tournamentCategory->categoryLabel }}</span></td>
                <td colspan="10" class="border border-black px-2 py-2"><span class="font-bold">TIME</span> <span class="ml-2">{{ $match->scheduled_at?->format('g:i A, M j, Y') }}</span></td>
            </tr>

            @foreach ($sets as $set)
                <tr class="bg-black/5">
                    <td class="border border-black px-2 py-1 text-[10px] font-bold">SET {{ $set['number'] }}</td>
                    @foreach ($columns as $number)
                        <td class="border border-black py-1 text-center text-[10px] font-bold">{{ $number }}</td>
                    @endforeach
                </tr>

                @foreach (['A', 'B'] as $side)
                    <tr>
                        <td class="border border-black px-2 py-1 text-[10px] leading-tight font-bold break-words">{{ $sideLabels[$side] }}</td>
                        @foreach ($columns as $number)
                            <td @class(['h-7 border border-black', 'bg-black' => $number <= $set['scores'][$side]])></td>
                        @endforeach
                    </tr>
                @endforeach
            @endforeach

            <tr class="text-xs">
                <td colspan="10" class="h-24 border border-black px-2 py-1.5 align-top">
                    <span class="font-bold">UMPIRE:</span> {{ $match->assignedUmpire?->name }}
                    @if ($lineJudges)
                        <p class="mt-1 text-[10px]">Line judges: {{ $lineJudges }}</p>
                    @endif
                </td>

                @foreach (['A', 'B'] as $side)
                    <td colspan="6" class="h-24 border border-black px-2 py-1.5 align-top">
                        <div class="flex h-full flex-col justify-between">
                            <span class="text-[10px] leading-tight">{{ $sideLabels[$side] }}</span>
                            <span class="text-center text-[10px]">Signature</span>
                        </div>
                    </td>
                @endforeach

                <td colspan="10" class="h-24 border border-black px-2 py-1.5 align-top">
                    <span class="font-bold">WINNER:</span> {{ $winnerSide ? $sideLabels[$winnerSide] : '' }}
                </td>
            </tr>
        </tbody>
    </table>
</div>
