<?php

namespace App\Support\Reports;

use App\Models\ApplicationPlayer;
use App\Models\PaymentDetail;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Support\Standings\RoundRobinStandings;
use Illuminate\Support\Collection;

/**
 * The numbers behind an event's Sales and Player Participation reports.
 * Payments carry no amount of their own: an entry pays its category's fee
 * once, so revenue is the fee of every entry with a verified payment.
 */
class EventReports
{
    /**
     * @return Collection<int, TournamentCategory>
     */
    private function categories(Tournament $tournament): Collection
    {
        return $tournament->tournamentCategories()
            ->with([
                'category',
                'playerApplications' => fn ($query) => $query
                    ->where('status', '!=', 'rejected')
                    ->with(['applicationPlayers.player', 'paymentDetails']),
            ])
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{
     *     categories: list<array{category: string, entries: int, paid_entries: int, fee: float, collected: float, outstanding: float}>,
     *     ledger: list<array{date: string, reference_number: string, reference_code: string, players: string, category: string, amount: float}>,
     *     totals: array{entries: int, paid_entries: int, collected: float, outstanding: float}
     * }
     */
    public function sales(Tournament $tournament): array
    {
        $rows = [];
        $ledger = [];

        foreach ($this->categories($tournament) as $tournamentCategory) {
            $fee = (float) $tournamentCategory->registration_fee;
            $paid = 0;

            foreach ($tournamentCategory->playerApplications as $application) {
                $payment = $this->verifiedPayment($application);

                if (! $payment) {
                    continue;
                }

                $paid++;

                $ledger[] = [
                    'date' => $payment->date_of_payment_made?->format('Y-m-d') ?? '',
                    'reference_number' => (string) $payment->reference_number,
                    'reference_code' => (string) $application->reference_code,
                    'players' => $this->playerNames($application),
                    'category' => $tournamentCategory->category_label,
                    'amount' => $fee,
                ];
            }

            $entries = $tournamentCategory->playerApplications->count();

            $rows[] = [
                'category' => $tournamentCategory->category_label,
                'entries' => $entries,
                'paid_entries' => $paid,
                'fee' => $fee,
                'collected' => $fee * $paid,
                'outstanding' => $fee * ($entries - $paid),
            ];
        }

        usort($ledger, fn (array $a, array $b) => [$a['date'], $a['reference_code']] <=> [$b['date'], $b['reference_code']]);

        $summary = collect($rows);

        return [
            'categories' => $rows,
            'ledger' => $ledger,
            'totals' => [
                'entries' => (int) $summary->sum('entries'),
                'paid_entries' => (int) $summary->sum('paid_entries'),
                'collected' => (float) $summary->sum('collected'),
                'outstanding' => (float) $summary->sum('outstanding'),
            ],
        ];
    }

    /**
     * @return array{
     *     roster: list<array{category: string, name: string, gender: string, age: ?int, level: string, club: string, partner: string}>,
     *     placements: list<array{category: string, champion: string, runner_up: string}>,
     *     summary: array{players: int, entries: int, new_players: int, returning_players: int, male: int, female: int, other: int, per_category: list<array{category: string, entries: int, players: int}>}
     * }
     */
    public function participation(Tournament $tournament): array
    {
        $roster = [];
        $placements = [];
        $perCategory = [];
        $genders = [];
        $entries = 0;

        foreach ($this->categories($tournament) as $tournamentCategory) {
            $approved = $tournamentCategory->playerApplications->where('status', 'approved');
            $categoryPlayers = 0;

            foreach ($approved as $application) {
                foreach ($application->applicationPlayers as $applicationPlayer) {
                    $player = $applicationPlayer->player;

                    if (! $player) {
                        continue;
                    }

                    $categoryPlayers++;
                    $genders[$player->id] = $player->gender;

                    $roster[] = [
                        'category' => $tournamentCategory->category_label,
                        'name' => $player->name,
                        'gender' => (string) $player->gender,
                        'age' => $player->age,
                        'level' => (string) ($applicationPlayer->level_at_apply ?? $player->level),
                        'club' => (string) $player->club,
                        'partner' => $application->applicationPlayers
                            ->where('player_id', '!=', $player->id)
                            ->map(fn ($other) => $other->player?->name)
                            ->filter()
                            ->implode(' / '),
                    ];
                }
            }

            $entries += $approved->count();
            $perCategory[] = [
                'category' => $tournamentCategory->category_label,
                'entries' => $approved->count(),
                'players' => $categoryPlayers,
            ];

            [$champion, $runnerUp] = $this->placement($tournament, $tournamentCategory);

            $placements[] = [
                'category' => $tournamentCategory->category_label,
                'champion' => $champion ?? '—',
                'runner_up' => $runnerUp ?? '—',
            ];
        }

        $playerIds = array_keys($genders);
        $returning = $this->returningPlayerCount($tournament, $playerIds);

        return [
            'roster' => $roster,
            'placements' => $placements,
            'summary' => [
                'players' => count($playerIds),
                'entries' => $entries,
                'new_players' => count($playerIds) - $returning,
                'returning_players' => $returning,
                'male' => count(array_filter($genders, fn ($gender) => $gender === 'male')),
                'female' => count(array_filter($genders, fn ($gender) => $gender === 'female')),
                'other' => count(array_filter($genders, fn ($gender) => ! in_array($gender, ['male', 'female'], true))),
                'per_category' => $perCategory,
            ],
        ];
    }

    /**
     * Champion and runner-up names, or nulls where the draw hasn't decided
     * them (no schedule, unfinished final, or a level round-robin).
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function placement(Tournament $tournament, TournamentCategory $tournamentCategory): array
    {
        if (! $tournamentCategory->hasGeneratedDraw()) {
            return [null, null];
        }

        if ($tournament->format === 'round_robin') {
            $rows = (new RoundRobinStandings)->for($tournamentCategory);
            $complete = $tournamentCategory->matches()->where('status', '!=', 'completed')->doesntExist();

            if (! $complete || $rows === []) {
                return [null, null];
            }

            $first = $rows[0];
            $second = $rows[1] ?? null;

            return [
                $first['tied'] ? null : $first['name'],
                $second && ! $second['tied'] && ! $first['tied'] ? $second['name'] : null,
            ];
        }

        if ($tournament->format !== 'single_elimination') {
            return [null, null];
        }

        $maxRound = $tournamentCategory->matches()->max('round');

        $final = $tournamentCategory->matches()
            ->where('round', $maxRound)
            ->where('status', 'completed')
            ->whereNotNull('winner_application_id')
            ->with(['matchApplications.application.applicationPlayers.player', 'winnerApplication.applicationPlayers.player'])
            ->first();

        if (! $final) {
            return [null, null];
        }

        $champion = $this->playerNames($final->winnerApplication);
        $runnerUp = $final->matchApplications
            ->map(fn ($matchApplication) => $matchApplication->application)
            ->filter(fn (?PlayerApplication $application) => $application && $application->id !== $final->winner_application_id)
            ->map(fn (PlayerApplication $application) => $this->playerNames($application))
            ->first();

        return [$champion ?: null, $runnerUp ?: null];
    }

    /**
     * Players of this event who also had an approved entry in another event.
     *
     * @param  list<int>  $playerIds
     */
    private function returningPlayerCount(Tournament $tournament, array $playerIds): int
    {
        if ($playerIds === []) {
            return 0;
        }

        return ApplicationPlayer::query()
            ->whereIn('player_id', $playerIds)
            ->whereHas('application', fn ($query) => $query
                ->where('status', 'approved')
                ->whereHas('tournamentCategory', fn ($tc) => $tc->where('tournament_id', '!=', $tournament->id)))
            ->distinct()
            ->count('player_id');
    }

    private function verifiedPayment(PlayerApplication $application): ?PaymentDetail
    {
        return $application->paymentDetails->where('payment_status', 'verified')->sortBy('id')->last();
    }

    private function playerNames(?PlayerApplication $application): string
    {
        return $application?->applicationPlayers
            ->map(fn ($applicationPlayer) => $applicationPlayer->player?->name)
            ->filter()
            ->implode(' / ') ?? '';
    }
}
