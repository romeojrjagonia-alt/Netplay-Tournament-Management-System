@props(['match'])

@php
    $match->loadMissing([
        'tournamentCategory.tournament',
        'tournamentCategory.category',
        'matchApplications.application.applicationPlayers.player',
        'games.rallies',
        'assignedUmpire',
    ]);

    $tournament = $match->tournamentCategory->tournament;
    $sets = $match->scoresheetSets();
    $teams = [
        'A' => ['label' => 'Team 1', 'players' => $match->sidePlayers('A'), 'names' => $match->sideLabel('A')],
        'B' => ['label' => 'Team 2', 'players' => $match->sidePlayers('B'), 'names' => $match->sideLabel('B')],
    ];
    $lineJudges = collect([$match->line_judge_1_name, $match->line_judge_2_name])->filter()->implode(', ');
@endphp

{{-- Mirrors OBC's paper score sheet, one sheet per set: title, GAME NO / Set / COURT / BRACKET,
     CATEGORY / TIME, then a Team 1 and a Team 2 block. Each block is a numbered header row
     (rally 1, 2, 3 ...) with one row per player; a black cell means that player won that rally.
     Footer: UMPIRE / Signature / Signature / WINNER. One table per sheet, so borders line up. --}}
<div {{ $attributes->class(['flex flex-col gap-8']) }}>
    @foreach ($sets as $set)
        @php
            $columns = range(1, \App\Models\Matches::SCORESHEET_RALLIES);
            $total = count($columns) + 1;
        @endphp

        <div class="overflow-x-auto {{ ! $loop->last ? 'print:break-after-page' : '' }}">
            <table class="w-full min-w-[860px] table-fixed border-collapse border border-black text-black [-webkit-print-color-adjust:exact] [print-color-adjust:exact]">
                <colgroup>
                    <col style="width: 176px">
                    @foreach ($columns as $number)
                        <col>
                    @endforeach
                </colgroup>

                <tbody>
                    <tr>
                        <td colspan="{{ $total }}" class="border border-black px-2 py-1.5 text-center">
                            <p class="text-base font-extrabold tracking-wide uppercase">{{ $tournament->name }}</p>
                            <p class="text-xs">{{ $tournament->date_range_label }}</p>
                        </td>
                    </tr>

                    <tr class="text-xs">
                        <td colspan="3" class="border border-black px-2 py-1.5"><span class="font-bold">GAME NO:</span> {{ $match->id }}</td>
                        <td colspan="8" class="border border-black px-2 py-1.5">Set: {{ $set['number'] }}</td>
                        <td colspan="11" class="border border-black px-2 py-1.5"><span class="font-bold">COURT:</span> {{ $match->court }}</td>
                        <td colspan="{{ $total - 22 }}" class="border border-black px-2 py-1.5"><span class="font-bold">BRACKET:</span> {{ str($match->bracket_type)->headline() }}</td>
                    </tr>

                    <tr class="text-xs">
                        <td colspan="{{ $total - 10 }}" class="border border-black px-2 py-1.5"><span class="font-bold">CATEGORY:</span> {{ $match->tournamentCategory->categoryLabel }}</td>
                        <td colspan="10" class="border border-black px-2 py-1.5"><span class="font-bold">TIME:</span> {{ $match->scheduled_at?->format('g:i A, M j, Y') }}</td>
                    </tr>

                    @foreach ($teams as $team)
                        <tr class="bg-[#eeeeee]">
                            <td class="border border-black px-2 py-0.5 text-[10px] font-bold">{{ $team['label'] }}</td>
                            @foreach ($columns as $number)
                                <td class="border border-black py-0.5 text-center text-[10px] font-bold">{{ $number }}</td>
                            @endforeach
                        </tr>

                        @foreach ($team['players'] as $player)
                            <tr>
                                <td class="border border-black px-2 py-0.5 text-[10px] leading-tight font-bold break-words">{{ $player['name'] }}</td>
                                @foreach ($columns as $number)
                                    <td @class(['h-[30px] border border-black', 'bg-black' => ($set['scorers'][$number] ?? null) === $player['id']])></td>
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

                        @foreach ($teams as $team)
                            <td colspan="6" class="h-24 border border-black px-2 py-1.5 align-top">
                                <div class="flex h-full flex-col justify-between">
                                    <span class="text-[10px] leading-tight">{{ $team['names'] }}</span>
                                    <span class="text-center text-[10px]">Signature</span>
                                </div>
                            </td>
                        @endforeach

                        <td colspan="{{ $total - 22 }}" class="h-24 border border-black px-2 py-1.5 align-top">
                            <span class="font-bold">WINNER:</span> {{ $set['winner'] === 'A' ? $teams['A']['label'] : ($set['winner'] === 'B' ? $teams['B']['label'] : '') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endforeach
</div>
