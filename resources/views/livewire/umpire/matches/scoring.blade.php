<?php

use App\Models\Matches;
use App\Models\MatchGame;
use App\Models\Rally;
use App\Support\Badminton\GameRules;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.umpire', ['title' => 'Scoring'])] class extends Component {
    public Matches $match;

    /** @var array<int, array{game_id: int, player_id: int, rally_number: int}> */
    public array $redoStack = [];

    public string $lineJudge1Name = '';

    public string $lineJudge2Name = '';

    /**
     * Whether the Rally Score Sheet modal's content should be rendered.
     * Kept false until the umpire actually opens it, since that partial is
     * expensive (a full per-rally tick-mark grid) and would otherwise be
     * recomputed and re-transmitted on every single scoring click even
     * while the modal sits closed.
     */
    public bool $showScoresheet = false;

    public function mount(Matches $match): void
    {
        abort_unless(auth()->user()->isUmpire(), 403);
        abort_unless($match->assigned_umpire_id === auth()->id(), 403);

        $this->match = $match;
        $this->lineJudge1Name = $match->line_judge_1_name ?? '';
        $this->lineJudge2Name = $match->line_judge_2_name ?? '';
    }

    /**
     * player_id => 'A'|'B' for this match's two entries.
     *
     * @return array<int, 'A'|'B'>
     */
    #[Computed]
    public function playerSideMap(): array
    {
        return $this->match->playerSideMap();
    }

    /**
     * Whether this tournament scores a match as one 31-point set (first to
     * 31 wins, no 2-point lead) instead of best of 3 to 21.
     */
    #[Computed]
    public function singleSet(): bool
    {
        return $this->match->usesSinglePointSystem();
    }

    /**
     * @return \Illuminate\Support\Collection<int, MatchGame>
     */
    #[Computed]
    public function games()
    {
        return $this->match->games()->with('rallies')->orderBy('game_number')->get();
    }

    /**
     * The game currently being played: the latest one without a decided
     * winner. Null once every existing game has been decided.
     */
    #[Computed]
    public function currentGame(): ?MatchGame
    {
        return $this->games->first(fn (MatchGame $game) => $game->winningSide($this->playerSideMap, $this->singleSet) === null);
    }

    /**
     * The most recently active game (for undo purposes): the current game,
     * or the last game if every game so far has been decided.
     */
    #[Computed]
    public function lastActiveGame(): ?MatchGame
    {
        return $this->currentGame ?? $this->games->last();
    }

    #[Computed]
    public function currentScores(): array
    {
        return $this->currentGame?->scores($this->playerSideMap) ?? ['A' => 0, 'B' => 0];
    }

    /**
     * @return array{A: int, B: int}
     */
    #[Computed]
    public function setsWon(): array
    {
        $counts = ['A' => 0, 'B' => 0];

        foreach ($this->decidedGameWinners() as $side) {
            $counts[$side]++;
        }

        return $counts;
    }

    #[Computed]
    public function matchWinnerSide(): ?string
    {
        return GameRules::matchWinner($this->decidedGameWinners(), $this->singleSet);
    }

    /**
     * A match that has two entries, is not finished and has no sets yet:
     * the stage where teams are checked in and the match can still start
     * (or be awarded by walkover).
     */
    #[Computed]
    public function awaitingStart(): bool
    {
        return $this->match->status !== 'completed'
            && $this->games->count() === 0
            && $this->match->matchApplications()->count() >= 2;
    }

    public function isTeamReady(string $side): bool
    {
        return $side === 'A'
            ? $this->match->team_a_ready_at !== null
            : $this->match->team_b_ready_at !== null;
    }

    #[Computed]
    public function bothTeamsReady(): bool
    {
        return $this->isTeamReady('A') && $this->isTeamReady('B');
    }

    /**
     * Whether the scheduled start time has passed, which is when a no-show
     * can be declared.
     */
    #[Computed]
    public function scheduledTimePassed(): bool
    {
        return $this->match->scheduled_at === null || $this->match->scheduled_at->isPast();
    }

    /**
     * The umpire marks a team ready once all of its players are present, and
     * can take it back until the match starts.
     */
    public function toggleReady(string $side): void
    {
        abort_unless(in_array($side, ['A', 'B'], true), 404);

        if (! $this->awaitingStart) {
            return;
        }

        $column = $side === 'A' ? 'team_a_ready_at' : 'team_b_ready_at';

        $this->match->update([$column => $this->match->{$column} === null ? now() : null]);

        unset($this->bothTeamsReady, $this->canStartMatch);
    }

    /**
     * A team that is not ready by the scheduled time can be passed over: the
     * team that is ready wins, but only if it is the one that showed up.
     */
    public function canDeclareWalkover(string $winnerSide): bool
    {
        $absentSide = $winnerSide === 'A' ? 'B' : 'A';

        return $this->awaitingStart
            && $this->scheduledTimePassed
            && $this->isTeamReady($winnerSide)
            && ! $this->isTeamReady($absentSide);
    }

    public function declareWalkover(string $winnerSide)
    {
        abort_unless(in_array($winnerSide, ['A', 'B'], true), 404);

        if (! $this->canDeclareWalkover($winnerSide)) {
            return;
        }

        $this->match->update([
            'status' => 'completed',
            'winner_application_id' => $this->match->applicationForSide($winnerSide)?->id,
            'result_type' => 'walkover',
        ]);

        return $this->redirect(route('umpire.events.show', $this->match->tournamentCategory->tournament_id), navigate: true);
    }

    /**
     * Whether the very first set can be created: the match has not started,
     * and the umpire has checked in both teams.
     */
    #[Computed]
    public function canStartMatch(): bool
    {
        return $this->awaitingStart && $this->bothTeamsReady;
    }

    #[Computed]
    public function canAddSet(): bool
    {
        return $this->match->status !== 'completed'
            && $this->matchWinnerSide === null
            && $this->currentGame === null
            && $this->games->count() > 0
            && $this->games->count() < GameRules::maxGames($this->singleSet);
    }

    #[Computed]
    public function canConfirmResults(): bool
    {
        return $this->match->status !== 'completed' && $this->matchWinnerSide !== null;
    }

    #[Computed]
    public function teamAApplication()
    {
        return $this->match->applicationForSide('A');
    }

    #[Computed]
    public function teamBApplication()
    {
        return $this->match->applicationForSide('B');
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    #[Computed]
    public function teamAPlayers(): array
    {
        return $this->teamAApplication?->applicationPlayers->map(fn ($ap) => ['id' => $ap->player_id, 'name' => $ap->player->name])->all() ?? [];
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    #[Computed]
    public function teamBPlayers(): array
    {
        return $this->teamBApplication?->applicationPlayers->map(fn ($ap) => ['id' => $ap->player_id, 'name' => $ap->player->name])->all() ?? [];
    }

    public function startMatch(): void
    {
        if (! $this->canStartMatch) {
            return;
        }

        if ($this->match->status === 'scheduled') {
            $this->match->update(['status' => 'in_progress']);
        }

        MatchGame::create(['match_id' => $this->match->id, 'game_number' => 1]);

        unset($this->games, $this->currentGame, $this->lastActiveGame, $this->canStartMatch);
    }

    public function addSet(): void
    {
        if (! $this->canAddSet) {
            return;
        }

        MatchGame::create([
            'match_id' => $this->match->id,
            'game_number' => $this->games->count() + 1,
        ]);

        $this->redoStack = [];

        unset($this->games, $this->currentGame, $this->lastActiveGame, $this->canAddSet);
    }

    public function recordRally(int $playerId): void
    {
        $game = $this->currentGame;

        if (! $game || $this->match->status === 'completed') {
            return;
        }

        $nextRallyNumber = (int) ($game->rallies->max('rally_number') ?? 0) + 1;

        Rally::create([
            'match_game_id' => $game->id,
            'scoring_player_id' => $playerId,
            'rally_number' => $nextRallyNumber,
        ]);

        $gameId = $game->id;

        $this->redoStack = [];

        unset($this->games, $this->currentGame, $this->lastActiveGame, $this->currentScores, $this->setsWon, $this->matchWinnerSide, $this->canAddSet, $this->canConfirmResults);

        // Look the game up by id rather than via currentGame: the instant
        // the winning rally lands, this game's score-based winningSide() is
        // no longer null, so currentGame (by definition, "the first
        // undecided game") would already exclude it. Reuses the recompute
        // the unset() above forces anyway (needed for the render regardless)
        // instead of a separate fresh() fetch of the same games+rallies data.
        if ($freshGame = $this->games->firstWhere('id', $gameId)) {
            $this->settleGameWinner($freshGame);
        }
    }

    public function undoLastRally(): void
    {
        $game = $this->lastActiveGame;
        $rally = $game?->rallies->sortByDesc('rally_number')->first();

        if (! $game || ! $rally) {
            return;
        }

        $this->redoStack[] = [
            'game_id' => $game->id,
            'player_id' => $rally->scoring_player_id,
            'rally_number' => $rally->rally_number,
        ];

        $rally->delete();

        if ($game->winner_application_id !== null) {
            $game->update(['winner_application_id' => null]);
        }

        unset($this->games, $this->currentGame, $this->lastActiveGame, $this->currentScores, $this->setsWon, $this->matchWinnerSide, $this->canAddSet, $this->canConfirmResults);
    }

    public function redoLastRally(): void
    {
        $entry = array_pop($this->redoStack);

        if (! $entry || ! MatchGame::whereKey($entry['game_id'])->exists()) {
            return;
        }

        Rally::create([
            'match_game_id' => $entry['game_id'],
            'scoring_player_id' => $entry['player_id'],
            'rally_number' => $entry['rally_number'],
        ]);

        unset($this->games, $this->currentGame, $this->lastActiveGame, $this->currentScores, $this->setsWon, $this->matchWinnerSide, $this->canAddSet, $this->canConfirmResults);

        // Same reasoning as recordRally(): look the game up by id (not via
        // currentGame, which excludes it once its score decides the game)
        // and reuse the recompute rather than a separate fetch.
        if ($freshGame = $this->games->firstWhere('id', $entry['game_id'])) {
            $this->settleGameWinner($freshGame);
        }
    }

    public function openScoresheet(): void
    {
        $this->showScoresheet = true;
        $this->dispatch('modal-show', name: 'rally-scoresheet');
    }

    public function closeScoresheet(): void
    {
        $this->showScoresheet = false;
    }

    public function confirmResults()
    {
        if (! $this->canConfirmResults) {
            return;
        }

        $winnerApplication = $this->match->applicationForSide($this->matchWinnerSide);

        $this->match->update([
            'status' => 'completed',
            'winner_application_id' => $winnerApplication?->id,
        ]);

        return $this->redirect(route('umpire.events.show', $this->match->tournamentCategory->tournament_id), navigate: true);
    }

    public function updatedLineJudge1Name(string $value): void
    {
        $this->match->update(['line_judge_1_name' => $value !== '' ? $value : null]);
    }

    public function updatedLineJudge2Name(string $value): void
    {
        $this->match->update(['line_judge_2_name' => $value !== '' ? $value : null]);
    }

    /**
     * Mark the given game decided if its current score meets a win
     * condition. Safe to call after either recording or undoing a rally.
     */
    private function settleGameWinner(MatchGame $game): void
    {
        $side = $game->winningSide($this->match->playerSideMap(), $this->singleSet);

        if ($side === null) {
            return;
        }

        $winnerApplication = $this->match->applicationForSide($side);

        $game->update(['winner_application_id' => $winnerApplication?->id]);

        unset($this->games, $this->currentGame, $this->lastActiveGame, $this->setsWon, $this->matchWinnerSide, $this->canAddSet, $this->canConfirmResults);
    }

    /**
     * @return array<int, 'A'|'B'>
     */
    private function decidedGameWinners(): array
    {
        $map = $this->playerSideMap;

        return $this->games
            ->map(fn (MatchGame $game) => $game->winningSide($map, $this->singleSet))
            ->filter()
            ->values()
            ->all();
    }
}; ?>

<div class="flex flex-col gap-5">
    <div class="flex flex-col gap-1 border-b border-white/20 pb-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-bold text-white sm:text-xl">{{ $match->tournamentCategory->tournament->name }}</h2>

            @if ($match->round)
                <span class="flex h-6 w-fit items-center justify-center rounded-[5px] bg-[#caa2ff] px-2.5 text-[11px] text-[#5d3198]">
                    Round {{ $match->round }}
                </span>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-4 text-xs text-white">
            @if ($match->court)
                <span class="flex items-center gap-2"><flux:icon.map-pin class="size-[15px] shrink-0" />{{ $match->court }}</span>
            @endif
            @if ($match->scheduled_at)
                <span class="flex items-center gap-2"><flux:icon.clock class="size-[15px] shrink-0" />{{ $match->scheduled_at->format('g:i A') }}</span>
                <span class="flex items-center gap-2"><flux:icon.calendar class="size-[15px] shrink-0" />{{ $match->scheduled_at->format('F j, Y') }}</span>
            @endif
        </div>
    </div>

    {{-- Everything an umpire needs while scoring lives in this stage, so fullscreen can show just it. --}}
    <div x-data="{ fs: false, walkover: null, supported: !! document.fullscreenEnabled,
                  toggle() { document.fullscreenElement ? document.exitFullscreen() : $root.requestFullscreen() } }"
         x-on:fullscreenchange.window="fs = document.fullscreenElement === $root"
         x-bind:class="fs ? 'h-screen w-screen overflow-auto bg-gradient-to-b from-[#051c1a] to-[#178279] p-4 sm:p-6' : ''"
         class="flex flex-col gap-5">
    <div class="flex flex-col gap-5 lg:flex-row" x-bind:class="fs ? 'min-h-0 flex-1 lg:items-stretch' : 'lg:items-start'">
        <div class="flex w-full flex-col gap-2.5 lg:w-[220px] lg:shrink-0">
            @foreach ($this->games as $game)
                @php $side = $game->winningSide($this->playerSideMap, $this->singleSet); @endphp
                <div class="flex items-center gap-2.5 rounded-[5px] bg-white px-4 py-2.5 text-xs font-bold text-black">
                    <span>{{ $this->singleSet ? '31-POINT GAME' : 'SET '.$game->game_number }}</span>
                    <span class="text-black/60">
                        @if ($side === 'A')
                            Team A Won
                        @elseif ($side === 'B')
                            Team B Won
                        @else
                            In progress
                        @endif
                    </span>
                </div>
            @endforeach

            @if ($this->canAddSet)
                <button type="button" wire:click="addSet"
                        wire:loading.attr="disabled" wire:target="addSet"
                        class="flex cursor-pointer items-center justify-center gap-2 rounded-[5px] bg-[#3e948d] px-4 py-2.5 text-xs font-bold text-white hover:bg-[#0d5750] disabled:cursor-not-allowed disabled:opacity-50">
                    <flux:icon.plus class="size-4" /> Add New Set
                </button>
            @endif
        </div>

        <div class="flex w-full flex-col gap-5 rounded-[5px] bg-white/10 p-5" x-bind:class="fs ? 'min-h-0 flex-1' : ''">
            <div class="flex flex-wrap items-center justify-between gap-3">
                @if ($this->singleSet)
                    <span class="text-xs text-white">First to {{ GameRules::SINGLE_SET_POINTS_TO_WIN }} points wins</span>
                @else
                    <span class="text-xs text-white">Sets won — Team A: {{ $this->setsWon['A'] }} · Team B: {{ $this->setsWon['B'] }}</span>
                @endif

                <div class="flex items-center gap-2">
                    <button type="button" x-show="supported" x-cloak x-on:click="toggle()" x-bind:title="fs ? 'Exit fullscreen' : 'Fullscreen'"
                            class="flex size-9 cursor-pointer items-center justify-center rounded-full bg-white text-black hover:bg-white/80">
                        <flux:icon.arrows-pointing-out class="size-4" x-show="!fs" />
                        <flux:icon.arrows-pointing-in class="size-4" x-show="fs" x-cloak />
                    </button>
                    <button type="button" wire:click="undoLastRally" title="Undo last point"
                            wire:loading.attr="disabled" wire:target="undoLastRally"
                            class="flex size-9 cursor-pointer items-center justify-center rounded-full bg-white text-black hover:bg-white/80 disabled:cursor-not-allowed disabled:opacity-40">
                        <flux:icon.arrow-uturn-left class="size-4" />
                    </button>
                    <button type="button" wire:click="redoLastRally" title="Redo point" @disabled(empty($redoStack))
                            wire:loading.attr="disabled" wire:target="redoLastRally"
                            class="flex size-9 cursor-pointer items-center justify-center rounded-full bg-white text-black hover:bg-white/80 disabled:cursor-not-allowed disabled:opacity-40">
                        <flux:icon.arrow-uturn-right class="size-4" />
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2.5 text-center text-xs font-bold text-white">
                <span>TEAM A</span>
                <span>TEAM B</span>
            </div>

            @if ($this->awaitingStart)
                <div class="grid grid-cols-2 gap-2.5">
                    @foreach (['A', 'B'] as $side)
                        @php $otherSide = $side === 'A' ? 'B' : 'A'; @endphp
                        <div class="flex flex-col gap-2" wire:key="ready-{{ $side }}">
                            <button type="button" wire:click="toggleReady('{{ $side }}')"
                                    wire:loading.attr="disabled" wire:target="toggleReady('{{ $side }}')"
                                    @class([
                                        'flex cursor-pointer items-center justify-center gap-1.5 rounded-[5px] border px-3 py-2.5 text-xs font-bold disabled:cursor-not-allowed disabled:opacity-50',
                                        'border-[#9cee69] bg-[#9cee69] text-[#2f6b13]' => $this->isTeamReady($side),
                                        'border-white/60 bg-transparent text-white hover:bg-white/10' => ! $this->isTeamReady($side),
                                    ])>
                                @if ($this->isTeamReady($side))
                                    <flux:icon.check class="size-4" /> Team {{ $side }} ready
                                @else
                                    Ready Team {{ $side }}
                                @endif
                            </button>

                            @if ($this->canDeclareWalkover($otherSide))
                                <button type="button" x-on:click="walkover = '{{ $otherSide }}'"
                                        class="cursor-pointer rounded-[5px] border border-[#ffb8b8] px-3 py-2 text-[11px] font-bold text-[#ffb8b8] hover:bg-white/10">
                                    No-show: award win to Team {{ $otherSide }}
                                </button>
                            @elseif ($this->isTeamReady($otherSide) && ! $this->isTeamReady($side) && ! $this->scheduledTimePassed)
                                <p class="text-center text-[10px] text-white/70">
                                    A no-show can be declared after {{ $match->scheduled_at->format('g:i A') }}.
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div x-show="walkover" x-cloak class="flex flex-col gap-2 rounded-[5px] border border-[#ffb8b8] bg-black/30 p-3 text-xs text-white">
                    <p>
                        Award the match to <span class="font-bold" x-text="'Team ' + walkover"></span> because the other team did not show up?
                        This ends the match as a walkover and cannot be undone.
                    </p>
                    <div class="flex gap-2">
                        <button type="button" x-on:click="$wire.declareWalkover(walkover)" wire:loading.attr="disabled" wire:target="declareWalkover"
                                class="cursor-pointer rounded-[5px] bg-[#bd6b6c] px-4 py-2 font-bold text-white disabled:opacity-50">
                            Confirm walkover
                        </button>
                        <button type="button" x-on:click="walkover = null" class="cursor-pointer rounded-[5px] border border-white/60 px-4 py-2 font-bold">
                            Cancel
                        </button>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-2 items-center gap-2.5 rounded-[5px] bg-black/40 text-center font-bold text-white" x-bind:class="fs ? 'min-h-0 flex-1 text-[22vh] leading-none' : 'py-6 text-5xl'">
                <span>{{ str_pad((string) $this->currentScores['A'], 2, '0', STR_PAD_LEFT) }}</span>
                <span>{{ str_pad((string) $this->currentScores['B'], 2, '0', STR_PAD_LEFT) }}</span>
            </div>

            <div class="grid grid-cols-2 gap-2.5" x-bind:class="fs ? 'min-h-0 flex-1' : ''">
                <div class="flex flex-col gap-2" x-bind:class="fs ? 'min-h-0' : ''">
                    @forelse ($this->teamAPlayers as $index => $player)
                        <button type="button" wire:click="recordRally({{ $player['id'] }})"
                                @disabled(! $this->currentGame)
                                wire:loading.attr="disabled" wire:target="recordRally({{ $player['id'] }})"
                                x-bind:class="fs ? 'min-h-24 flex-1 justify-center [&_span:last-child]:text-2xl' : ''" class="flex cursor-pointer flex-col items-center gap-1 rounded-[5px] bg-[#caa2ff] px-3 py-4 text-[#5d3198] hover:bg-[#b98cf5] disabled:cursor-not-allowed disabled:opacity-50">
                            <span class="text-[9px] font-medium">Player {{ $index + 1 }}</span>
                            <span class="text-xs font-bold">{{ $player['name'] }}</span>
                        </button>
                    @empty
                        <p class="py-4 text-center text-xs text-white/70">No players entered.</p>
                    @endforelse
                </div>

                <div class="flex flex-col gap-2" x-bind:class="fs ? 'min-h-0' : ''">
                    @forelse ($this->teamBPlayers as $index => $player)
                        <button type="button" wire:click="recordRally({{ $player['id'] }})"
                                @disabled(! $this->currentGame)
                                wire:loading.attr="disabled" wire:target="recordRally({{ $player['id'] }})"
                                x-bind:class="fs ? 'min-h-24 flex-1 justify-center [&_span:last-child]:text-2xl' : ''" class="flex cursor-pointer flex-col items-center gap-1 rounded-[5px] bg-[#9cee69] px-3 py-4 text-[#2f6b13] hover:bg-[#8ade56] disabled:cursor-not-allowed disabled:opacity-50">
                            <span class="text-[9px] font-medium">Player {{ $index + 1 }}</span>
                            <span class="text-xs font-bold">{{ $player['name'] }}</span>
                        </button>
                    @empty
                        <p class="py-4 text-center text-xs text-white/70">No players entered.</p>
                    @endforelse
                </div>
            </div>

            @if ($this->awaitingStart)
                <button type="button" wire:click="startMatch" @disabled(! $this->canStartMatch)
                        wire:loading.attr="disabled" wire:target="startMatch"
                        class="w-full cursor-pointer rounded-[5px] bg-[#3e948d] py-2.5 text-center text-xs font-bold text-white hover:bg-[#0d5750] disabled:cursor-not-allowed disabled:opacity-50">
                    {{ $this->canStartMatch ? 'Start Game' : 'Start Game (mark both teams ready first)' }}
                </button>
            @endif
        </div>
    </div>

        <button type="button" wire:click="confirmResults" @disabled(! $this->canConfirmResults)
                wire:loading.attr="disabled" wire:target="confirmResults"
                class="w-full cursor-pointer rounded-[5px] bg-[#3e948d] py-2.5 text-center text-xs font-bold text-white hover:bg-[#0d5750] disabled:cursor-not-allowed disabled:opacity-50">
            Confirm Match Results
        </button>
    </div>

    <div class="flex flex-col gap-3 rounded-[5px] bg-white/10 p-4">
        <p class="text-xs font-bold text-white">Match Officials</p>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <label class="flex flex-col gap-1 text-xs text-white">
                Umpire
                <span class="rounded-[5px] border border-white/30 bg-white/10 px-3 py-2 text-white/70">{{ $match->assignedUmpire?->name ?? '—' }}</span>
            </label>
            <label class="flex flex-col gap-1 text-xs text-white">
                Line Judge 1
                <input type="text" wire:model.live.debounce.500ms="lineJudge1Name" placeholder="Name"
                       class="rounded-[5px] border border-white/30 bg-white px-3 py-2 text-black focus:border-[#3e948d] focus:outline-none">
            </label>
            <label class="flex flex-col gap-1 text-xs text-white">
                Line Judge 2
                <input type="text" wire:model.live.debounce.500ms="lineJudge2Name" placeholder="Name"
                       class="rounded-[5px] border border-white/30 bg-white px-3 py-2 text-black focus:border-[#3e948d] focus:outline-none">
            </label>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-3">
        <button type="button" wire:click="openScoresheet"
                class="w-full cursor-pointer rounded-[5px] bg-[#0b4742] py-2.5 text-center text-xs font-bold text-white hover:bg-[#083530]">
            View Score Sheet
        </button>

    </div>

    <flux:modal name="rally-scoresheet" wire:close="closeScoresheet" class="w-full max-w-6xl dark:bg-white! dark:ring-black/5!">
        @if ($showScoresheet)
            <div class="flex flex-col gap-4">
                <x-umpire.rally-scoresheet :match="$match" />

                <div class="flex flex-col justify-end gap-3 pt-2 sm:flex-row">
                    <a href="{{ route('umpire.matches.scoresheet.download', $match) }}"
                       class="flex items-center justify-center gap-2 rounded-[5px] bg-[#3e948d] px-6 py-2.5 text-center text-xs font-bold text-white hover:bg-[#0d5750]">
                        <flux:icon.document-arrow-down class="size-4" /> Download
                    </a>
                    <a href="{{ route('umpire.matches.scoresheet', $match) }}?print=1" target="_blank"
                       class="flex items-center justify-center gap-2 rounded-[5px] bg-[#0b4742] px-6 py-2.5 text-center text-xs font-bold text-white hover:bg-[#083530]">
                        <flux:icon.printer class="size-4" /> Print
                    </a>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
