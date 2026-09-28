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

    $nameWidth = 170;
    $cellWidth = 27;

    $border = 'border:1px solid #000000;';
    $text = 'font-family:Arial,sans-serif;font-size:10pt;color:#000000;';
    $box = $border.$text.'padding:4px 6px;';
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
            <table cellspacing="0" cellpadding="0" style="border-collapse:collapse;width:{{ $nameWidth + $cellWidth * count($columns) }}px;">
                <tr>
                    <td colspan="{{ $span }}" align="center" style="{{ $box }}text-align:center;">
                        <b style="font-size:14pt;">{{ strtoupper($tournament->name) }}</b><br>
                        {{ $tournament->date_range_label }}
                    </td>
                </tr>

                <tr>
                    <td colspan="11" style="{{ $box }}"><b>GAME NO</b>&nbsp;&nbsp;{{ $match->id }}</td>
                    <td colspan="11" style="{{ $box }}"><b>COURT</b>&nbsp;&nbsp;{{ $match->court }}</td>
                    <td colspan="10" style="{{ $box }}"><b>BRACKET</b>&nbsp;&nbsp;{{ str($match->bracket_type)->headline() }}</td>
                </tr>

                <tr>
                    <td colspan="22" style="{{ $box }}"><b>CATEGORY</b>&nbsp;&nbsp;{{ $match->tournamentCategory->categoryLabel }}</td>
                    <td colspan="10" style="{{ $box }}"><b>TIME</b>&nbsp;&nbsp;{{ $match->scheduled_at?->format('g:i A, M j, Y') }}</td>
                </tr>

                @foreach ($sets as $set)
                    <tr bgcolor="#eeeeee" style="background:#eeeeee;">
                        <td width="{{ $nameWidth }}" style="{{ $border.$text }}font-size:8pt;padding:3px 6px;"><b>SET {{ $set['number'] }}</b></td>
                        @foreach ($columns as $number)
                            <td width="{{ $cellWidth }}" align="center" style="{{ $border.$text }}font-size:8pt;text-align:center;"><b>{{ $number }}</b></td>
                        @endforeach
                    </tr>

                    @foreach (['A', 'B'] as $side)
                        <tr style="height:30px;">
                            <td height="30" style="{{ $border.$text }}font-size:8pt;padding:3px 6px;"><b>{{ $sideLabels[$side] }}</b></td>
                            @foreach ($columns as $number)
                                @if ($number <= $set['scores'][$side])
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
                    @foreach (['A', 'B'] as $side)
                        <td colspan="6" height="90" valign="bottom" style="{{ $box }}font-size:8pt;">
                            {{ $sideLabels[$side] }}<br><br><br><br><br><span align="center" style="display:block;text-align:center;">Signature</span>
                        </td>
                    @endforeach
                    <td colspan="10" height="90" valign="top" style="{{ $box }}"><b>WINNER:</b> {{ $winnerSide ? $sideLabels[$winnerSide] : '' }}</td>
                </tr>
            </table>
        </div>
    </body>
</html>
