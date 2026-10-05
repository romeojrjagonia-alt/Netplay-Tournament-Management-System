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

    // Landscape A4 with 0.4in margins leaves 1044px: a 176px name column and
    // 31 number cells of 28px.
    $nameWidth = 176;
    $cellWidth = 28;

    $border = 'border:1px solid #000000;';
    $text = 'font-family:Arial,sans-serif;color:#000000;';
    $box = $border.$text.'font-size:10pt;padding:4px 6px;';
@endphp
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
    <head>
        <meta charset="utf-8">
        <title>Match {{ $match->id }} Rally Score Sheet</title>
        <!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom></w:WordDocument></xml><![endif]-->
        <style>
            @page Section1 { size: 11.69in 8.27in; mso-page-orientation: landscape; margin: 0.4in; }
            div.Section1 { page: Section1; }
        </style>
    </head>
    <body>
        <div class="Section1">
            @foreach ($sets as $set)
                @php
                    $columns = range(1, \App\Models\Matches::SCORESHEET_RALLIES);
                    $total = count($columns) + 1;
                @endphp

                @if (! $loop->first)
                    <br clear="all" style="mso-special-character:line-break;page-break-before:always">
                @endif

                <table cellspacing="0" cellpadding="0" style="border-collapse:collapse;width:{{ $nameWidth + $cellWidth * count($columns) }}px;">
                    <tr>
                        <td colspan="{{ $total }}" align="center" style="{{ $box }}text-align:center;">
                            <b style="font-size:14pt;">{{ strtoupper($tournament->name) }}</b><br>
                            {{ $tournament->date_range_label }}
                        </td>
                    </tr>

                    <tr>
                        <td colspan="3" style="{{ $box }}"><b>GAME NO:</b>&nbsp;&nbsp;{{ $match->id }}</td>
                        <td colspan="8" style="{{ $box }}">Set: {{ $set['number'] }}</td>
                        <td colspan="11" style="{{ $box }}"><b>COURT:</b>&nbsp;{{ $match->court }}</td>
                        <td colspan="{{ $total - 22 }}" style="{{ $box }}"><b>BRACKET:</b>&nbsp;&nbsp;{{ str($match->bracket_type)->headline() }}</td>
                    </tr>

                    <tr>
                        <td colspan="{{ $total - 10 }}" style="{{ $box }}"><b>CATEGORY:</b>&nbsp;{{ $match->tournamentCategory->categoryLabel }}</td>
                        <td colspan="10" style="{{ $box }}"><b>TIME:</b>&nbsp;{{ $match->scheduled_at?->format('g:i A, M j, Y') }}</td>
                    </tr>

                    @foreach ($teams as $team)
                        <tr bgcolor="#eeeeee" style="background:#eeeeee;">
                            <td width="{{ $nameWidth }}" style="{{ $border.$text }}font-size:8pt;padding:2px 6px;"><b>{{ $team['label'] }}</b></td>
                            @foreach ($columns as $number)
                                <td width="{{ $cellWidth }}" align="center" style="{{ $border.$text }}font-size:8pt;text-align:center;"><b>{{ $number }}</b></td>
                            @endforeach
                        </tr>

                        @foreach ($team['players'] as $player)
                            <tr style="height:30px;">
                                <td height="30" style="{{ $border.$text }}font-size:8pt;padding:2px 6px;"><b>{{ $player['name'] }}</b></td>
                                @foreach ($columns as $number)
                                    @if (($set['scorers'][$number] ?? null) === $player['id'])
                                        <td height="30" bgcolor="#000000" style="{{ $border }}background:#000000;">&nbsp;</td>
                                    @else
                                        <td height="30" style="{{ $border }}">&nbsp;</td>
                                    @endif
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach

                    <tr style="height:90px;">
                        <td colspan="10" height="90" valign="top" style="{{ $box }}">
                            <b>UMPIRE:</b> {{ $match->assignedUmpire?->name }}
                            @if ($lineJudges)
                                <br><span style="font-size:8pt;">Line judges: {{ $lineJudges }}</span>
                            @endif
                        </td>
                        @foreach ($teams as $team)
                            <td colspan="6" height="90" valign="bottom" style="{{ $box }}font-size:8pt;">
                                {{ $team['names'] }}<br><br><br><br><br><span align="center" style="display:block;text-align:center;">Signature</span>
                            </td>
                        @endforeach
                        <td colspan="{{ $total - 22 }}" height="90" valign="top" style="{{ $box }}"><b>WINNER:</b>&nbsp;&nbsp;{{ $set['winner'] === 'A' ? $teams['A']['label'] : ($set['winner'] === 'B' ? $teams['B']['label'] : '') }}</td>
                    </tr>
                </table>
            @endforeach
        </div>
    </body>
</html>
