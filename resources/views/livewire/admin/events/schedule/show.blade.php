<?php

use App\Actions\Applications\RerouteApplication;
use App\Actions\Matches\NotifyPlayersOfSchedule;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use App\Support\Scheduling\RoundRobinScheduler;
use App\Support\Scheduling\SingleEliminationScheduler;
use App\Support\Standings\RoundRobinStandings;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.admin', ['title' => 'Schedule Matches'])] class extends Component {
    public Tournament $tournament;

    public TournamentCategory $tournamentCategory;

    public int $selectedCategoryId;

    public ?int $reroutingApplicationId = null;

    public ?int $rerouteDestinationId = null;

    public bool $overrideEligibility = false;

    public ?int $editingMatchId = null;

    public string $editDate = '';

    public string $editTime = '';

    public string $editCourt = '';

    public ?int $editUmpireId = null;

    public function mount(Tournament $tournament, TournamentCategory $tournamentCategory): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        abort_unless($tournamentCategory->tournament_id === $tournament->id, 404);

        $this->tournament = $tournament;
        $this->tournamentCategory = $tournamentCategory;
        $this->selectedCategoryId = $tournamentCategory->id;
    }

    public function updatedSelectedCategoryId(int $id): void
    {
        $category = $this->tournament->tournamentCategories()->findOrFail($id);

        $this->redirect(route('admin.events.schedule.show', [$this->tournament, $category]), navigate: true);
    }

    public function generate(RoundRobinScheduler $roundRobin, SingleEliminationScheduler $singleElimination): void
    {
        abort_unless(in_array($this->tournament->format, ['round_robin', 'single_elimination'], true), 403);

        $scheduler = $this->tournament->format === 'round_robin' ? $roundRobin : $singleElimination;


        try {
            $count = $scheduler->generate($this->tournamentCategory);
        } catch (InvalidArgumentException $exception) {
            $this->say($exception->getMessage(), 'danger');

            return;
        }

        $this->cancelReroute();
        $this->say("Schedule generated: {$count} matches created.");
    }

    /**
     * Show the admin a message in the shared system modal.
     */
    private function say(string $message, string $variant = 'success'): void
    {
        $this->dispatch('system-message', message: $message, variant: $variant);
    }

    public function editMatch(int $matchId): void
    {
        $match = $this->tournamentCategory->matches()->findOrFail($matchId);

        $this->resetValidation();

        $this->editingMatchId = $match->id;
        $this->editDate = $match->scheduled_at?->format('Y-m-d') ?? '';
        $this->editTime = $match->scheduled_at?->format('H:i') ?? '';
        $this->editCourt = $match->court ?? '';
        $this->editUmpireId = $match->assigned_umpire_id;
    }

    public function cancelEditMatch(): void
    {
        $this->reset('editingMatchId', 'editDate', 'editTime', 'editCourt', 'editUmpireId');
        $this->resetValidation();
    }

    /**
     * Save a match's date, time, court and umpire. A changed date, time or
     * court clears the "players notified" mark so the new details are
     * emailed again.
     */
    public function saveMatch(): void
    {
        $this->validate([
            'editDate' => ['nullable', 'date', 'required_with:editTime'],
            'editTime' => ['nullable', 'date_format:H:i', 'required_with:editDate'],
            'editCourt' => ['nullable', 'string', 'max:50'],
            'editUmpireId' => ['nullable', Rule::exists('users', 'id')->where('role', 'umpire')],
        ], [
            'editDate.required_with' => 'Choose a date as well as a time.',
            'editTime.required_with' => 'Choose a time as well as a date.',
        ]);

        $match = $this->tournamentCategory->matches()->findOrFail($this->editingMatchId);

        abort_if($match->status === 'completed', 403);

        $scheduledAt = $this->editDate !== '' ? Carbon::parse($this->editDate.' '.$this->editTime) : null;
        $court = trim($this->editCourt) !== '' ? trim($this->editCourt) : null;

        $match->fill([
            'scheduled_at' => $scheduledAt,
            'court' => $court,
            'assigned_umpire_id' => $this->editUmpireId,
        ]);

        if ($match->isDirty(['scheduled_at', 'court'])) {
            $match->schedule_notified_at = null;
        }

        $match->save();

        $this->cancelEditMatch();
        unset($this->rounds, $this->pendingNotificationCount);
        $this->say('Match details saved.');
    }

    /**
     * Email the players of every match in this category that has a time but
     * hasn't been announced (or has changed since).
     */
    public function notifyPlayers(NotifyPlayersOfSchedule $notify): void
    {

        $result = $notify->handle($this->tournamentCategory);

        unset($this->rounds, $this->pendingNotificationCount);

        if ($result['matches'] === 0) {
            $this->say('There are no scheduled matches with a new date and time to announce.', 'danger');

            return;
        }

        $this->say("Sent {$result['emails']} ".Str::plural('email', $result['emails'])." for {$result['matches']} ".Str::plural('match', $result['matches']).'.'
            .($result['without_email'] > 0 ? " {$result['without_email']} ".Str::plural('player', $result['without_email']).' had no email address on file.' : ''));
    }

    public function startReroute(int $applicationId): void
    {
        $this->reroutingApplicationId = $applicationId;
        $this->rerouteDestinationId = null;
        $this->overrideEligibility = false;
    }

    public function cancelReroute(): void
    {
        $this->reset('reroutingApplicationId', 'rerouteDestinationId', 'overrideEligibility');
    }

    public function updatedRerouteDestinationId(): void
    {
        $this->overrideEligibility = false;
    }

    public function reroute(RerouteApplication $reroute): void
    {

        $application = $this->tournamentCategory->playerApplications()
            ->where('status', 'approved')
            ->find($this->reroutingApplicationId);
        $destination = $this->tournament->tournamentCategories()->find($this->rerouteDestinationId);

        if (! $application || ! $destination) {
            $this->say('Choose a destination category.', 'danger');

            return;
        }

        try {
            $result = $reroute->handle($application, $destination, $this->overrideEligibility);
        } catch (InvalidArgumentException $exception) {
            $this->say($exception->getMessage(), 'danger');

            return;
        }

        $this->say('Entry moved to '.$destination->load('category')->category_label.'.'
            .($result['fee_changed'] ? " Note: the registration fee changes from ₱{$result['from_fee']} to ₱{$result['to_fee']}; reconcile this with the player." : ''));

        $this->cancelReroute();
    }

    /**
     * @return Collection<int, TournamentCategory>
     */
    #[Computed]
    public function categories(): Collection
    {
        return $this->tournament->tournamentCategories()->with('category')->orderBy('id')->get();
    }

    /**
     * @return Collection<int, PlayerApplication>
     */
    #[Computed]
    public function entries(): Collection
    {
        return $this->tournamentCategory->playerApplications()
            ->where('status', 'approved')
            ->with('applicationPlayers.player')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function umpires(): Collection
    {
        return User::query()->where('role', 'umpire')->orderBy('name')->get();
    }

    /**
     * Matches with a date and time whose players haven't been emailed yet.
     */
    #[Computed]
    public function pendingNotificationCount(): int
    {
        return $this->tournamentCategory->matches()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->whereNull('schedule_notified_at')
            ->count();
    }

    #[Computed]
    public function hasDraw(): bool
    {
        return $this->tournamentCategory->hasGeneratedDraw();
    }

    /**
     * Matches grouped by round.
     *
     * @return Collection<int, Collection<int, \App\Models\Matches>>
     */
    #[Computed]
    public function rounds(): Collection
    {
        return $this->tournamentCategory->matches()
            ->with(['games.rallies', 'assignedUmpire', 'matchApplications.application.applicationPlayers.player'])
            ->orderBy('round')
            ->orderBy('position')
            ->get()
            ->groupBy('round');
    }

    /**
     * @return list<array{application: PlayerApplication, name: string, wins: int, losses: int, point_diff: int, rank: int, tied: bool}>
     */
    #[Computed]
    public function standings(): array
    {
        return app(RoundRobinStandings::class)->for($this->tournamentCategory);
    }

    /**
     * What moving the selected entry to the selected destination would
     * involve, shown before the admin confirms.
     *
     * @return array{team_size_mismatch: bool, ineligible_players: Collection, destination_has_draw: bool, fee_changed: bool, from_fee: string, to_fee: string}|null
     */
    #[Computed]
    public function rerouteIssues(): ?array
    {
        if (! $this->reroutingApplicationId || ! $this->rerouteDestinationId) {
            return null;
        }

        $application = $this->entries->firstWhere('id', $this->reroutingApplicationId);
        $destination = $this->categories->firstWhere('id', $this->rerouteDestinationId);

        if (! $application || ! $destination) {
            return null;
        }

        return [
            ...$destination->eligibilityIssuesFor($application),
            'destination_has_draw' => $destination->hasGeneratedDraw(),
            'fee_changed' => (float) $this->tournamentCategory->registration_fee !== (float) $destination->registration_fee,
            'from_fee' => (string) $this->tournamentCategory->registration_fee,
            'to_fee' => (string) $destination->registration_fee,
        ];
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-col gap-4 border-b border-white/50 pb-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 class="text-2xl font-bold text-white">{{ $tournament->name }}</h2>
            <span class="flex items-center gap-2 text-xs font-bold text-white">
                <flux:icon.calendar class="size-[18px] shrink-0" />
                {{ $tournament->date_range_label }}
            </span>
        </div>

        <x-admin.event-tabs :tournament="$tournament" active="schedule" />
    </div>

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex h-[30px] items-center rounded-[5px] bg-white px-[10px]">
            <select wire:model.live="selectedCategoryId" aria-label="Category" class="bg-transparent text-[12px] text-black focus:outline-none">
                @foreach ($this->categories as $category)
                    <option value="{{ $category->id }}">{{ $category->category_label }}</option>
                @endforeach
            </select>
        </div>

        @if ($this->hasDraw)
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    x-on:click="$dispatch('system-confirm', {
                        title: 'Email players?',
                        message: @js('Email the players of '.$this->pendingNotificationCount.' scheduled '.Str::plural('match', $this->pendingNotificationCount).' about their match time?'),
                        confirmLabel: 'Send emails',
                        confirm: () => $wire.notifyPlayers(),
                    })"
                    @disabled($this->pendingNotificationCount === 0)
                    class="flex items-center gap-1.5 rounded-[5px] bg-[#9cee69] px-[20px] py-[7px] text-xs font-medium text-[#051c1a] enabled:cursor-pointer disabled:cursor-not-allowed disabled:opacity-40"
                >
                    <flux:icon.envelope class="size-4" />
                    Email players ({{ $this->pendingNotificationCount }})
                </button>
                <span title="Export is coming soon" class="flex cursor-not-allowed items-center gap-1.5 rounded-[5px] bg-white/20 px-[20px] py-[7px] text-xs font-medium text-white/50">
                    <flux:icon.arrow-down-tray class="size-4" />
                    Export
                </span>
            </div>
        @endif
    </div>


    @if (! in_array($tournament->format, ['round_robin', 'single_elimination'], true))
        <div class="flex h-[300px] flex-col items-center justify-center gap-2 rounded-[10px] bg-white">
            <p class="text-[22px] text-black/70 italic">Schedule generation for {{ str_replace('_', ' ', $tournament->format) }} is coming in a later phase</p>
        </div>
    @elseif (! $this->hasDraw)
        <div class="flex flex-col gap-4 rounded-[10px] bg-white p-[30px]">
            <div class="flex items-center justify-between gap-4">
                <div class="flex flex-col">
                    <span class="text-lg font-bold text-black">Confirmed Entries</span>
                    <span class="text-xs text-black/60">{{ $this->entries->count() }} {{ Str::plural('entry', $this->entries->count()) }} · matches are generated in approval order</span>
                </div>

                <button
                    type="button"
                    x-on:click="$dispatch('system-confirm', {
                        title: 'Generate schedule?',
                        message: @js('Generate the '.str_replace('_', ' ', $tournament->format).' schedule? This can\'t be undone, and entries can no longer be added or moved afterward.'),
                        confirmLabel: 'Generate',
                        confirm: () => $wire.generate(),
                    })"
                    @disabled($this->entries->count() < 2)
                    class="rounded-[10px] bg-[#3e948d] px-[30px] py-[10px] text-xs font-medium text-white enabled:cursor-pointer disabled:cursor-not-allowed disabled:opacity-40"
                >
                    Generate Schedule
                </button>
            </div>

            @if ($this->entries->count() < 2)
                <p class="text-xs text-black/60">At least 2 confirmed entries are needed to generate a schedule.</p>
            @endif

            @if ($reroutingApplicationId)
                <div class="flex flex-col gap-3 rounded-[10px] border border-[#3e948d] bg-[#eaf4f3] p-4">
                    <span class="text-sm font-bold text-black">
                        Move {{ $this->entries->firstWhere('id', $reroutingApplicationId)?->applicationPlayers->map->player->filter()->pluck('name')->implode(' / ') }} to another category
                    </span>

                    <div class="flex h-[30px] w-fit items-center rounded-[5px] bg-white px-[10px]">
                        <select wire:model.live="rerouteDestinationId" aria-label="Destination category" class="bg-transparent text-[12px] text-black focus:outline-none">
                            <option value="">Select destination</option>
                            @foreach ($this->categories->where('id', '!=', $tournamentCategory->id) as $category)
                                <option value="{{ $category->id }}">{{ $category->category_label }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if ($issues = $this->rerouteIssues)
                        @if ($issues['team_size_mismatch'])
                            <p class="text-xs font-medium text-red-700">Team size doesn't match the destination category — this entry can't be moved there.</p>
                        @endif

                        @if ($issues['destination_has_draw'])
                            <p class="text-xs font-medium text-red-700">The destination category already has a generated schedule.</p>
                        @endif

                        @if ($issues['ineligible_players']->isNotEmpty())
                            <p class="text-xs font-medium text-amber-700">
                                Not eligible by gender or age: {{ $issues['ineligible_players']->pluck('name')->implode(', ') }}.
                            </p>
                            <label class="flex items-center gap-2 text-xs text-black">
                                <input type="checkbox" wire:model.live="overrideEligibility" class="size-[15px] rounded-[4px] border-2 border-[#0b4742]">
                                Move anyway
                            </label>
                        @endif

                        @if ($issues['fee_changed'])
                            <p class="text-xs font-medium text-amber-700">
                                The registration fee changes from ₱{{ $issues['from_fee'] }} to ₱{{ $issues['to_fee'] }}. Reconcile this with the player outside the system.
                            </p>
                        @endif
                    @endif

                    <div class="flex gap-3">
                        <button type="button" wire:click="reroute" @disabled(! $rerouteDestinationId) class="rounded-[5px] bg-[#3e948d] px-5 py-2 text-xs font-medium text-white enabled:cursor-pointer disabled:cursor-not-allowed disabled:opacity-40">
                            Move Entry
                        </button>
                        <button type="button" wire:click="cancelReroute" class="cursor-pointer rounded-[5px] border border-[#a3a3a3] bg-white px-5 py-2 text-xs font-medium text-[#a3a3a3]">
                            Cancel
                        </button>
                    </div>
                </div>
            @endif

            @forelse ($this->entries as $entry)
                <div wire:key="entry-{{ $entry->id }}" class="flex items-center gap-4 rounded-[10px] bg-[#eaf4f3] px-5 py-3">
                    <span class="flex size-6 items-center justify-center rounded-full bg-[#3e948d] text-[11px] font-bold text-white">{{ $loop->iteration }}</span>
                    <span class="flex-1 text-sm font-bold text-black">
                        {{ $entry->applicationPlayers->map->player->filter()->pluck('name')->implode(' / ') }}
                    </span>

                    @if ($this->categories->count() > 1)
                        <button type="button" wire:click="startReroute({{ $entry->id }})" class="cursor-pointer text-xs font-medium text-[#3e948d] underline">
                            Reroute
                        </button>
                    @endif
                </div>
            @empty
                <p class="py-10 text-center text-[22px] text-black/70 italic">No confirmed entries yet</p>
            @endforelse
        </div>
    @else
        <div class="flex flex-col gap-8">
            @foreach ($this->rounds as $round => $matches)
                <section wire:key="round-{{ $round }}" class="flex flex-col gap-3">
                    <h3 class="text-sm font-bold text-white">Round {{ $round }}</h3>

                    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($matches as $match)
                            <div wire:key="match-{{ $match->id }}" class="flex flex-col gap-2">
                                <x-admin.schedule-match-card :match="$match" />

                                @if ($editingMatchId === $match->id)
                                    <form wire:submit="saveMatch" class="flex flex-col gap-3 rounded-[10px] bg-white p-4">
                                        <div class="grid grid-cols-2 gap-3">
                                            <label class="flex flex-col gap-1 text-[11px] text-black">
                                                Date
                                                <input type="date" wire:model="editDate" class="rounded-[5px] border border-black/20 px-2 py-1.5 text-xs">
                                                @error('editDate') <span class="text-[10px] text-red-600">{{ $message }}</span> @enderror
                                            </label>
                                            <label class="flex flex-col gap-1 text-[11px] text-black">
                                                Time
                                                <input type="time" wire:model="editTime" class="rounded-[5px] border border-black/20 px-2 py-1.5 text-xs">
                                                @error('editTime') <span class="text-[10px] text-red-600">{{ $message }}</span> @enderror
                                            </label>
                                        </div>
                                        <div class="grid grid-cols-2 gap-3">
                                            <label class="flex flex-col gap-1 text-[11px] text-black">
                                                Court
                                                <input type="text" wire:model="editCourt" placeholder="e.g. 1" class="rounded-[5px] border border-black/20 px-2 py-1.5 text-xs">
                                                @error('editCourt') <span class="text-[10px] text-red-600">{{ $message }}</span> @enderror
                                            </label>
                                            <label class="flex flex-col gap-1 text-[11px] text-black">
                                                Umpire
                                                <select wire:model="editUmpireId" class="rounded-[5px] border border-black/20 px-2 py-1.5 text-xs">
                                                    <option value="">Unassigned</option>
                                                    @foreach ($this->umpires as $umpire)
                                                        <option value="{{ $umpire->id }}">{{ $umpire->name }}</option>
                                                    @endforeach
                                                </select>
                                                @error('editUmpireId') <span class="text-[10px] text-red-600">{{ $message }}</span> @enderror
                                            </label>
                                        </div>
                                        <div class="flex justify-end gap-2">
                                            <button type="button" wire:click="cancelEditMatch" class="cursor-pointer rounded-[5px] border border-black/20 px-3 py-1.5 text-xs text-black">Cancel</button>
                                            <button type="submit" class="cursor-pointer rounded-[5px] bg-[#3e948d] px-3 py-1.5 text-xs font-medium text-white">Save</button>
                                        </div>
                                    </form>
                                @elseif ($match->status !== 'completed')
                                    <div class="flex items-center justify-between gap-2 px-1 text-[11px] text-white">
                                        <span>Umpire: {{ $match->assignedUmpire?->name ?? 'Unassigned' }}</span>
                                        <button type="button" wire:click="editMatch({{ $match->id }})" class="cursor-pointer font-medium underline">Edit time, court &amp; umpire</button>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        @if ($tournament->format === 'round_robin')
        <div class="flex flex-col gap-3 rounded-[10px] bg-white p-[30px]">
            <span class="text-lg font-bold text-black">Standings</span>

            <table class="w-full text-left text-xs text-black">
                <thead>
                    <tr class="border-b border-black/10 text-black/60">
                        <th class="py-2 pr-2">Rank</th>
                        <th class="py-2">Entry</th>
                        <th class="py-2 text-center">W</th>
                        <th class="py-2 text-center">L</th>
                        <th class="py-2 text-center">Point Diff</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->standings as $row)
                        <tr wire:key="standing-{{ $row['application']->id }}" class="border-b border-black/5">
                            <td class="py-2 pr-2 font-bold">{{ $row['rank'] }}@if ($row['tied'])<span title="Unresolved tie" class="text-amber-600">*</span>@endif</td>
                            <td class="py-2">{{ $row['name'] }}</td>
                            <td class="py-2 text-center">{{ $row['wins'] }}</td>
                            <td class="py-2 text-center">{{ $row['losses'] }}</td>
                            <td class="py-2 text-center">{{ $row['point_diff'] > 0 ? '+' : '' }}{{ $row['point_diff'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if (collect($this->standings)->contains('tied', true))
                <p class="text-[10px] text-black/60">* Still tied after the configured tiebreakers.</p>
            @endif
        </div>
        @endif
    @endif
</div>
