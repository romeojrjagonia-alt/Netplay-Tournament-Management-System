<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Support\Reports\EventReports;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventReportController extends Controller
{
    public function sales(Tournament $tournament, EventReports $reports): StreamedResponse
    {
        $this->authorizeReport($tournament);

        $data = $reports->sales($tournament);

        return $this->csv($tournament, 'sales', [
            ['Date', 'Reference No.', 'Reference Code', 'Player(s)', 'Category', 'Amount (PHP)'],
            ...array_map(fn (array $row) => [
                $row['date'], $row['reference_number'], $row['reference_code'], $row['players'], $row['category'], number_format($row['amount'], 2, '.', ''),
            ], $data['ledger']),
            [],
            ['Category', 'Entries', 'Paid entries', 'Fee (PHP)', 'Collected (PHP)', 'Outstanding (PHP)'],
            ...array_map(fn (array $row) => [
                $row['category'], $row['entries'], $row['paid_entries'],
                number_format($row['fee'], 2, '.', ''), number_format($row['collected'], 2, '.', ''), number_format($row['outstanding'], 2, '.', ''),
            ], $data['categories']),
            ['Total', $data['totals']['entries'], $data['totals']['paid_entries'], '', number_format($data['totals']['collected'], 2, '.', ''), number_format($data['totals']['outstanding'], 2, '.', '')],
        ]);
    }

    public function participation(Tournament $tournament, EventReports $reports): StreamedResponse
    {
        $this->authorizeReport($tournament);

        $data = $reports->participation($tournament);
        $summary = $data['summary'];

        return $this->csv($tournament, 'participation', [
            ['Category', 'Player', 'Gender', 'Age', 'Level', 'Club', 'Partner'],
            ...array_map(fn (array $row) => [
                $row['category'], $row['name'], $row['gender'], $row['age'], $row['level'], $row['club'], $row['partner'],
            ], $data['roster']),
            [],
            ['Category', 'Champion', 'Runner-up'],
            ...array_map(fn (array $row) => [$row['category'], $row['champion'], $row['runner_up']], $data['placements']),
            [],
            ['Summary'],
            ['Players', $summary['players']],
            ['Entries', $summary['entries']],
            ['New players', $summary['new_players']],
            ['Returning players', $summary['returning_players']],
            ['Male', $summary['male']],
            ['Female', $summary['female']],
            ['Other / not stated', $summary['other']],
        ]);
    }

    private function authorizeReport(Tournament $tournament): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        abort_unless($tournament->is_concluded, 404);
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function csv(Tournament $tournament, string $report, array $rows): StreamedResponse
    {
        $filename = Str::slug($tournament->name).'-'.$report.'-report.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            foreach ($rows as $row) {
                // Keep a spreadsheet from running a value like "=cmd()" as a formula.
                fputcsv($handle, array_map(
                    fn ($cell) => is_string($cell) && preg_match('/^[=+\-@]/', $cell) ? "'".$cell : $cell,
                    $row,
                ));
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
