<?php

use App\Models\ApplicationPlayer;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.player', ['title' => 'Events'])] class extends Component {
    use WithFileUploads;

    public string $tab = 'accepting';

    public string $search = '';

    public ?int $viewingTournamentId = null;

    public ?int $viewingApplicationId = null;

    // Registration wizard state.
    public int $regStep = 1;

    public ?int $regTournamentCategoryId = null;

    public string $regFirstName = '';
    public string $regLastName = '';
    public string $regMiddleInitial = '';
    public string $regBirthDate = '';
    public string $regGender = '';
    public string $regAddress = '';
    public string $regSkillLevel = '';
    public string $regClub = '';
    public string $regEmail = '';
    public string $regPhoneNumber = '';

    /** @var 'yes'|'no'|null */
    public ?string $pairHasAccount = null;

    /** @var 'yes'|'no'|null */
    public ?string $pairPlayedBefore = null;

    public string $pairSearch = '';

    public ?int $pairPlayerId = null;

    public string $pairFirstName = '';
    public string $pairLastName = '';
    public string $pairMiddleInitial = '';
    public string $pairBirthDate = '';
    public string $pairGender = '';
    public string $pairAddress = '';
    public string $pairSkillLevel = '';
    public string $pairClub = '';
    public string $pairEmail = '';
    public string $pairPhoneNumber = '';
    public $pairPhoto = null;

    public string $ownDocumentType = '';
    public $ownDocument = null;
    public string $pairDocumentType = '';
    public $pairDocument = null;

    /**
     * @var array<string, string>
     */
    private array $documentTypes = [
        'national_id' => 'Philippine National ID',
        'passport' => 'Passport',
        'birth_certificate' => 'Birth Certificate',
    ];

    public function mount(): void
    {
        abort_unless(auth()->user()->isPlayer(), 403);
    }

    public function selectTab(string $tab): void
    {
        $this->tab = $tab;
    }

    public function viewTournament(int $tournamentId): void
    {
        $this->viewingTournamentId = $tournamentId;
    }

    public function viewApplication(int $applicationId): void
    {
        $this->viewingApplicationId = $applicationId;
    }

    /**
     * Reset and open the tournament registration wizard for the tournament
     * currently being viewed, pre-filling the applicant's own details from
     * their player record when one already exists.
     */
    public function startRegistration(): void
    {
        $this->resetValidation();

        $this->regStep = 1;
        $this->regTournamentCategoryId = null;

        $this->pairHasAccount = null;
        $this->pairPlayedBefore = null;
        $this->pairSearch = '';
        $this->pairPlayerId = null;

        $this->pairFirstName = '';
        $this->pairLastName = '';
        $this->pairMiddleInitial = '';
        $this->pairBirthDate = '';
        $this->pairGender = '';
        $this->pairAddress = '';
        $this->pairSkillLevel = '';
        $this->pairClub = '';
        $this->pairEmail = '';
        $this->pairPhoneNumber = '';
        $this->pairPhoto = null;

        $this->ownDocumentType = '';
        $this->ownDocument = null;
        $this->pairDocumentType = '';
        $this->pairDocument = null;

        $player = auth()->user()->player;

        if ($player) {
            [$this->regFirstName, $this->regLastName, $this->regMiddleInitial] = $this->splitName($player->name);
            $this->regBirthDate = $player->birth_date?->format('Y-m-d') ?? '';
            $this->regGender = $player->gender ?? '';
            $this->regAddress = $player->address ?? '';
            $this->regSkillLevel = $player->level ?? '';
            $this->regClub = $player->club ?? '';
            $this->regEmail = $player->email ?? auth()->user()->email;
            $this->regPhoneNumber = $player->phone_number ?? '';
        } else {
            $this->regFirstName = '';
            $this->regLastName = '';
            $this->regMiddleInitial = '';
            $this->regBirthDate = '';
            $this->regGender = '';
            $this->regAddress = '';
            $this->regSkillLevel = '';
            $this->regClub = '';
            $this->regEmail = auth()->user()->email;
            $this->regPhoneNumber = '';
        }

        $this->dispatch('modal-close', name: 'tournament-details');
        $this->dispatch('modal-show', name: 'tournament-registration');
    }

    public function cancelRegistration(): void
    {
        $this->dispatch('modal-close', name: 'tournament-registration');
    }

    public function updatedPairHasAccount(): void
    {
        $this->pairPlayedBefore = null;
        $this->pairPlayerId = null;
        $this->pairSearch = '';
    }

    public function updatedPairPlayedBefore(): void
    {
        $this->pairPlayerId = null;
        $this->pairSearch = '';
    }

    public function selectPairPlayer(int $playerId): void
    {
        $this->pairPlayerId = $playerId;
    }

    /**
     * Validate the current step and advance the wizard.
     */
    public function registrationNextStep(): void
    {
        if ($this->regStep === 1) {
            $this->validate($this->profileStepRules());
        } elseif ($this->isPairStep) {
            $this->validate($this->pairStepRules());
        }

        $this->regStep = min($this->regStep + 1, $this->totalRegistrationSteps);
    }

    public function registrationPreviousStep(): void
    {
        $this->regStep = max($this->regStep - 1, 1);
    }

    /**
     * Validate the documents step and create the application.
     */
    public function submitRegistration(): void
    {
        $rules = [
            'ownDocumentType' => ['required', Rule::in(array_keys($this->documentTypes))],
            'ownDocument' => ['required', 'image', 'max:4096'],
        ];

        if ($this->isDoublesRegistration) {
            $rules['pairDocumentType'] = ['required', Rule::in(array_keys($this->documentTypes))];
            $rules['pairDocument'] = ['required', 'image', 'max:4096'];
        }

        $this->validate($rules);

        $tournamentCategoryId = $this->regTournamentCategoryId;

        try {
            DB::transaction(function () use ($tournamentCategoryId): void {
                $applicant = $this->resolveApplicantPlayer();

                $this->assertNotAlreadyEntered($applicant, $tournamentCategoryId);

                $partner = null;

                if ($this->isDoublesRegistration) {
                    $partner = $this->resolvePartnerPlayer();
                    $this->assertNotAlreadyEntered($partner, $tournamentCategoryId);
                }

                $application = PlayerApplication::create([
                    'tournament_category_id' => $tournamentCategoryId,
                    'status' => 'pending_verification',
                ]);

                ApplicationPlayer::create([
                    'application_id' => $application->id,
                    'player_id' => $applicant->id,
                    'level_at_apply' => $this->regSkillLevel,
                    'id_document_type' => $this->ownDocumentType,
                    'id_document_path' => $this->ownDocument->store('id-documents', 'public'),
                ]);

                if ($partner) {
                    ApplicationPlayer::create([
                        'application_id' => $application->id,
                        'player_id' => $partner->id,
                        'level_at_apply' => $this->pairViaSearch ? ($partner->level ?? 'Unranked') : $this->pairSkillLevel,
                        'id_document_type' => $this->pairDocumentType,
                        'id_document_path' => $this->pairDocument->store('id-documents', 'public'),
                    ]);
                }
            });
        } catch (\RuntimeException $e) {
            $this->addError('registration', $e->getMessage());

            return;
        }

        $this->viewingTournamentId = null;
        $this->dispatch('modal-close', name: 'tournament-registration');
        $this->dispatch('registration-toast', message: 'Application submitted! Waiting for organizer verification.', variant: 'success');

        unset($this->tournaments, $this->myApplications);
    }

    /**
     * Tournaments currently open for registration, optionally filtered by name.
     *
     * @return \Illuminate\Support\Collection<int, Tournament>
     */
    #[Computed]
    public function tournaments()
    {
        return Tournament::query()
            ->acceptingRegistrations()
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('registration_end')
            ->get();
    }

    #[Computed]
    public function viewingTournament(): ?Tournament
    {
        if (! $this->viewingTournamentId) {
            return null;
        }

        return Tournament::query()
            ->with('tournamentCategories.category')
            ->find($this->viewingTournamentId);
    }

    /**
     * This player's tournament applications, optionally filtered by tournament name.
     *
     * @return \Illuminate\Support\Collection<int, PlayerApplication>
     */
    #[Computed]
    public function myApplications()
    {
        return auth()->user()->player
            ->playerApplications()
            ->with(['tournamentCategory.tournament', 'tournamentCategory.category'])
            ->when(
                $this->search !== '',
                fn ($query) => $query->whereHas(
                    'tournamentCategory.tournament',
                    fn ($q) => $q->where('name', 'like', '%'.$this->search.'%')
                )
            )
            ->latest()
            ->get();
    }

    /**
     * The currently viewed application, scoped to this player so a viewer
     * can never load another player's application by guessing an ID.
     */
    #[Computed]
    public function viewingApplication(): ?PlayerApplication
    {
        if (! $this->viewingApplicationId) {
            return null;
        }

        return auth()->user()->player
            ->playerApplications()
            ->with(['tournamentCategory.tournament', 'tournamentCategory.category', 'paymentDetails'])
            ->find($this->viewingApplicationId);
    }

    #[Computed]
    public function regAge(): ?int
    {
        return $this->regBirthDate !== '' ? Carbon::parse($this->regBirthDate)->age : null;
    }

    /**
     * Categories in the tournament being viewed that this applicant, based
     * on the gender/birthdate currently entered in the wizard, is eligible
     * to register for.
     *
     * @return \Illuminate\Support\Collection<int, TournamentCategory>
     */
    #[Computed]
    public function eligibleCategories()
    {
        if (! $this->viewingTournamentId) {
            return collect();
        }

        return TournamentCategory::query()
            ->where('tournament_id', $this->viewingTournamentId)
            ->with('category')
            ->eligibleFor($this->regGender !== '' ? $this->regGender : null, $this->regAge)
            ->get();
    }

    #[Computed]
    public function selectedTournamentCategory(): ?TournamentCategory
    {
        if (! $this->regTournamentCategoryId) {
            return null;
        }

        return TournamentCategory::with('category')->find($this->regTournamentCategoryId);
    }

    #[Computed]
    public function isDoublesRegistration(): bool
    {
        return (int) ($this->selectedTournamentCategory?->category?->team_size ?? 1) === 2;
    }

    #[Computed]
    public function totalRegistrationSteps(): int
    {
        return $this->isDoublesRegistration ? 3 : 2;
    }

    #[Computed]
    public function isPairStep(): bool
    {
        return $this->isDoublesRegistration && $this->regStep === 2;
    }

    #[Computed]
    public function isDocumentsStep(): bool
    {
        return $this->regStep === $this->totalRegistrationSteps;
    }

    /**
     * Whether the pair should be located via search rather than entered
     * manually: either they have an account, or they don't but have played
     * in a tournament here before (so a player record likely already exists).
     */
    #[Computed]
    public function pairViaSearch(): bool
    {
        return $this->pairHasAccount === 'yes' || $this->pairPlayedBefore === 'yes';
    }

    /**
     * Existing player records matching the pair search, scoped to account
     * holders only when the pair is said to have one. Excludes the applicant.
     *
     * @return \Illuminate\Support\Collection<int, Player>
     */
    #[Computed]
    public function pairSearchResults()
    {
        if (mb_strlen($this->pairSearch) < 2) {
            return collect();
        }

        return Player::query()
            ->when($this->pairHasAccount === 'yes', fn ($query) => $query->whereNotNull('user_id'))
            ->when(auth()->user()->player, fn ($query, $player) => $query->where('id', '!=', $player->id))
            ->where(function ($query) {
                $query->where('name', 'like', '%'.$this->pairSearch.'%')
                    ->orWhere('phone_number', 'like', '%'.$this->pairSearch.'%')
                    ->orWhere('email', 'like', '%'.$this->pairSearch.'%');
            })
            ->limit(10)
            ->get();
    }

    /**
     * @return array<string, string>
     */
    public function documentTypeOptions(): array
    {
        return $this->documentTypes;
    }

    public function formatDateRange(Tournament $tournament): string
    {
        if ($tournament->date_start->isSameMonth($tournament->date_end)) {
            return $tournament->date_start->format('F j').' - '.$tournament->date_end->format('j, Y');
        }

        return $tournament->date_start->format('F j, Y').' - '.$tournament->date_end->format('F j, Y');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function profileStepRules(): array
    {
        $rules = [
            'regTournamentCategoryId' => ['required', Rule::in($this->eligibleCategories->pluck('id'))],
            'regFirstName' => ['required', 'string', 'max:255'],
            'regLastName' => ['required', 'string', 'max:255'],
            'regMiddleInitial' => ['nullable', 'string', 'max:5'],
            'regBirthDate' => ['required', 'date', 'before:today'],
            'regGender' => ['required', 'in:male,female,prefer_not_to_say'],
            'regAddress' => ['required', 'string', 'max:255'],
            'regSkillLevel' => ['required', 'string', 'max:255'],
            'regClub' => ['nullable', 'string', 'max:255'],
            'regEmail' => ['nullable', 'email', 'max:255'],
            'regPhoneNumber' => ['nullable', 'string', 'max:255'],
        ];

        if ($this->isDoublesRegistration) {
            $rules['pairHasAccount'] = ['required', 'in:yes,no'];

            if ($this->pairHasAccount === 'no') {
                $rules['pairPlayedBefore'] = ['required', 'in:yes,no'];
            }
        }

        return $rules;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function pairStepRules(): array
    {
        if ($this->pairViaSearch) {
            return [
                'pairPlayerId' => ['required', 'integer', 'exists:players,id'],
            ];
        }

        return [
            'pairFirstName' => ['required', 'string', 'max:255'],
            'pairLastName' => ['required', 'string', 'max:255'],
            'pairMiddleInitial' => ['nullable', 'string', 'max:5'],
            'pairBirthDate' => ['required', 'date', 'before:today'],
            'pairGender' => ['required', 'in:male,female,prefer_not_to_say'],
            'pairAddress' => ['required', 'string', 'max:255'],
            'pairSkillLevel' => ['required', 'string', 'max:255'],
            'pairClub' => ['nullable', 'string', 'max:255'],
            'pairEmail' => ['nullable', 'email', 'max:255'],
            'pairPhoneNumber' => ['nullable', 'string', 'max:255'],
            'pairPhoto' => ['nullable', 'image', 'max:4096'],
        ];
    }

    /**
     * Find or create the applicant's own player record, keeping it in sync
     * with whatever was (re)entered in the wizard.
     */
    private function resolveApplicantPlayer(): Player
    {
        $attributes = [
            'name' => $this->combineName($this->regFirstName, $this->regMiddleInitial, $this->regLastName),
            'birth_date' => $this->regBirthDate,
            'gender' => $this->regGender,
            'address' => $this->regAddress,
            'club' => $this->regClub !== '' ? $this->regClub : null,
            'level' => $this->regSkillLevel,
            'email' => $this->regEmail !== '' ? $this->regEmail : null,
            'phone_number' => $this->regPhoneNumber !== '' ? $this->regPhoneNumber : null,
        ];

        $player = auth()->user()->player;

        if ($player) {
            $player->update($attributes);

            return $player;
        }

        return Player::create($attributes + ['user_id' => auth()->id()]);
    }

    private function resolvePartnerPlayer(): Player
    {
        if ($this->pairViaSearch) {
            return Player::findOrFail($this->pairPlayerId);
        }

        return Player::create([
            'name' => $this->combineName($this->pairFirstName, $this->pairMiddleInitial, $this->pairLastName),
            'birth_date' => $this->pairBirthDate,
            'gender' => $this->pairGender,
            'address' => $this->pairAddress,
            'club' => $this->pairClub !== '' ? $this->pairClub : null,
            'level' => $this->pairSkillLevel,
            'email' => $this->pairEmail !== '' ? $this->pairEmail : null,
            'phone_number' => $this->pairPhoneNumber !== '' ? $this->pairPhoneNumber : null,
            'photo_path' => $this->pairPhoto?->store('player-photos', 'public'),
        ]);
    }

    /**
     * A player can't have more than one non-rejected application in the same
     * draw — a previously rejected application doesn't block re-applying.
     */
    private function assertNotAlreadyEntered(Player $player, int $tournamentCategoryId): void
    {
        $alreadyEntered = ApplicationPlayer::query()
            ->where('player_id', $player->id)
            ->whereHas('application', fn ($query) => $query
                ->where('tournament_category_id', $tournamentCategoryId)
                ->where('status', '!=', 'rejected'))
            ->exists();

        if ($alreadyEntered) {
            throw new \RuntimeException("{$player->name} already has an active application for this category.");
        }
    }

    private function combineName(string $first, string $middleInitial, string $last): string
    {
        return collect([$first, $middleInitial !== '' ? rtrim($middleInitial, '.').'.' : null, $last])
            ->filter()
            ->implode(' ');
    }

    /**
     * Best-effort split of a combined "First M. Last" name (the format
     * produced by {@see combineName()}) back into its parts for pre-filling
     * the wizard from an existing player record.
     *
     * @return array{0: string, 1: string, 2: string} [first, last, middleInitial]
     */
    private function splitName(string $name): array
    {
        $parts = explode(' ', trim($name));

        if (count($parts) >= 3 && str_ends_with($parts[1], '.') && mb_strlen($parts[1]) <= 3) {
            return [$parts[0], implode(' ', array_slice($parts, 2)), rtrim($parts[1], '.')];
        }

        if (count($parts) >= 2) {
            return [$parts[0], implode(' ', array_slice($parts, 1)), ''];
        }

        return [$parts[0] ?? '', '', ''];
    }
}; ?>

<div class="flex flex-col gap-5">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex h-10 items-center gap-4 rounded-full bg-white px-2.5 py-1.5">
            <button type="button" wire:click="selectTab('accepting')"
                    @class([
                        'flex h-full items-center justify-center rounded-full px-4 text-xs',
                        'bg-[#0b4742] text-white' => $tab === 'accepting',
                        'cursor-pointer text-[#0b4742]' => $tab !== 'accepting',
                    ])>
                Accepting Registrations
            </button>
            <button type="button" wire:click="selectTab('applications')"
                    @class([
                        'flex h-full items-center justify-center rounded-full px-4 text-xs',
                        'bg-[#0b4742] text-white' => $tab === 'applications',
                        'cursor-pointer text-[#0b4742]' => $tab !== 'applications',
                    ])>
                My Applications
            </button>
        </div>

        <div class="flex w-full items-center gap-2.5 rounded-[10px] bg-white px-5 py-2.5 sm:w-[265px]">
            <flux:icon.magnifying-glass class="size-[17px] shrink-0 text-black/50" />
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search"
                   class="w-full bg-transparent text-xs text-black placeholder:text-black/50 focus:outline-none">
        </div>
    </div>

    @if ($tab === 'accepting')
        <div class="flex flex-col gap-[15px]">
            @forelse ($this->tournaments as $tournament)
                <flux:modal.trigger name="tournament-details" wire:key="tournament-{{ $tournament->id }}">
                    <x-player.event-card
                        wire:click="viewTournament({{ $tournament->id }})"
                        :name="$tournament->name"
                        :poster-url="$tournament->poster_url"
                        :dates="$this->formatDateRange($tournament)"
                        :days="$tournament->specific_days"
                        :time="$tournament->time"
                        :deadline="$tournament->registration_end->format('F j, Y')"
                    />
                </flux:modal.trigger>
            @empty
                <p class="py-10 text-center text-sm text-white">No tournaments are currently accepting registrations.</p>
            @endforelse
        </div>
    @else
        <div class="flex flex-col gap-[15px] rounded-[5px] bg-[#77b9b3]/60 p-5">
            @forelse ($this->myApplications as $application)
                <flux:modal.trigger name="application-details" wire:key="application-{{ $application->id }}">
                    <x-player.application-row
                        wire:click="viewApplication({{ $application->id }})"
                        class="cursor-pointer"
                        :name="$application->tournamentCategory->tournament->name.' — '.$application->tournamentCategory->category->name"
                        :status="$application->status_label"
                    />
                </flux:modal.trigger>
            @empty
                <p class="py-10 text-center text-sm text-white">You have no tournament applications yet.</p>
            @endforelse
        </div>
    @endif

    <flux:modal name="tournament-details" class="w-full max-w-md overflow-hidden !p-0 dark:bg-white! dark:ring-black/5!">
        @if ($this->viewingTournament)
            <div class="flex flex-col">
                <div class="bg-gradient-to-r from-[#00857a] to-[#77b9b3] px-[20px] py-[20px] text-white">
                    <h2 class="text-[24px] font-bold">{{ $this->viewingTournament->name }}</h2>
                </div>

                <div class="flex flex-col gap-[10px] px-[20px] pt-[15px]">
                    <div>
                        <p class="text-[8px] text-[#3f3f3f]">Event Date</p>
                        <p class="text-[12px] font-bold text-black">{{ $this->formatDateRange($this->viewingTournament) }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-[15px]">
                        <div>
                            <p class="text-[8px] text-[#3f3f3f]">Time</p>
                            <p class="text-[12px] font-bold text-black">{{ $this->viewingTournament->time ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-[8px] text-[#3f3f3f]">Registrations</p>
                            <p class="text-[12px] font-bold text-black">
                                {{ $this->viewingTournament->registration_start->format('F j') }} - {{ $this->viewingTournament->registration_end->format('j, Y') }}
                            </p>
                        </div>
                        <div>
                            <p class="text-[8px] text-[#3f3f3f]">Specific Days</p>
                            <p class="text-[12px] font-bold text-black">{{ $this->viewingTournament->specific_days ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-[8px] text-[#3f3f3f]">Pointing System</p>
                            <p class="text-[12px] font-bold text-black">{{ $this->viewingTournament->scoring_system ?? '—' }}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-[15px] h-px w-full bg-[#d3d3d3]"></div>

                <div class="flex flex-col gap-[10px] px-[20px] pt-[15px] pb-[15px] text-[12px] text-black">
                    <div class="flex justify-between font-bold">
                        <span>Categories</span>
                        <span>Registration Fee</span>
                    </div>

                    <div class="flex max-h-[150px] flex-col gap-[10px] overflow-y-auto">
                        @foreach ($this->viewingTournament->tournamentCategories as $tournamentCategory)
                            <div class="flex justify-between">
                                <span>{{ $tournamentCategory->category->name }}{{ $tournamentCategory->category->level ? ' - '.$tournamentCategory->category->level : '' }}</span>
                                <span>₱{{ number_format((float) $tournamentCategory->registration_fee, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="px-[20px] pb-[20px]">
                    <button type="button" wire:click="startRegistration"
                            class="w-full cursor-pointer rounded-[5px] bg-[#3e948d] px-[30px] py-[10px] text-center text-[12px] text-white transition hover:bg-[#0d5750]">
                        Register
                    </button>
                </div>
            </div>
        @endif
    </flux:modal>

    <flux:modal name="application-details" class="w-full max-w-2xl dark:bg-white! dark:ring-black/5!">
        @if ($this->viewingApplication)
            <div class="flex flex-col gap-6">
                <div class="flex flex-col gap-1">
                    <h2 class="text-xl font-bold text-black">{{ $this->viewingApplication->tournamentCategory->tournament->name }}</h2>
                    <p class="text-sm text-black/70">{{ $this->viewingApplication->tournamentCategory->category->name }}</p>
                </div>

                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-xs font-bold text-black">Status</p>
                        <p class="text-black/70">{{ $this->viewingApplication->status_label }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-black">Applied On</p>
                        <p class="text-black/70">{{ $this->viewingApplication->created_at->format('F j, Y') }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-black">Registration Fee</p>
                        <p class="text-black/70">₱{{ number_format((float) $this->viewingApplication->tournamentCategory->registration_fee, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-black">Payment</p>
                        <p class="text-black/70">{{ $this->viewingApplication->paymentDetails->last()?->payment_status ?? 'No payment submitted yet' }}</p>
                    </div>
                </div>
            </div>
        @endif
    </flux:modal>

    <flux:modal name="tournament-registration" class="w-full max-w-xl dark:bg-white! dark:ring-black/5!">
        <div class="flex flex-col gap-[20px] p-[5px]">
            <div>
                <p class="text-[15px] text-black">Tournament Registraion</p>
                <p class="text-[20px] font-bold text-black">{{ $this->viewingTournament?->name }}</p>
            </div>

            <div>
                <div class="flex items-center justify-between rounded-t-[5px] bg-[#138177] px-[15px] py-[10px] text-white">
                    <span class="text-[10px] font-bold">
                        @if ($this->isDocumentsStep)
                            Valid Government Documents
                        @elseif ($this->isPairStep)
                            Paired Player
                        @else
                            Player Profile
                        @endif
                    </span>
                    <span class="text-[10px] italic">Step {{ $regStep }} of {{ $this->totalRegistrationSteps }}</span>
                </div>

                <div class="flex max-h-[420px] flex-col gap-[15px] overflow-y-auto rounded-b-[5px] border border-[#138177] bg-white p-[15px]">
                    @if ($regStep === 1)
                        <div class="flex gap-[9px]">
                            <x-player.registration-field label="First Name" model="regFirstName" placeholder="e.g. Juan" class="flex-1" />
                            <x-player.registration-field label="Last Name" model="regLastName" placeholder="e.g. Cruz" class="flex-1" />
                            <x-player.registration-field label="M.I" model="regMiddleInitial" placeholder="e.g. D" class="flex-1" />
                        </div>
                        <div class="flex gap-[9px]">
                            <x-player.registration-field label="Birthdate" model="regBirthDate" type="date" :live="true" class="flex-1" />
                            <x-player.registration-select label="Gender" model="regGender" :live="true" :options="['male' => 'Male', 'female' => 'Female', 'prefer_not_to_say' => 'Prefer not to say']" class="flex-1" />
                            <x-player.registration-field label="Address" model="regAddress" placeholder="e.g. Purok 5, Ozamiz City" class="flex-1" />
                        </div>
                        <div class="flex gap-[5px]">
                            <x-player.registration-field label="Skill Level" model="regSkillLevel" placeholder="e.g. Beginner" class="flex-1" />
                            <x-player.registration-field label="Club" model="regClub" placeholder="e.g. OBC Badminton Club" class="flex-1" />
                        </div>
                        <div class="flex gap-[5px]">
                            <x-player.registration-field label="Email" model="regEmail" type="email" placeholder="e.g. juancruz@gmail.com" class="flex-1" />
                            <x-player.registration-field label="Phone Number" model="regPhoneNumber" placeholder="e.g. 09xxxxxxxxx" class="flex-1" />
                        </div>

                        <x-player.registration-select
                            label="Category"
                            model="regTournamentCategoryId"
                            placeholder="Select Category"
                            :live="true"
                            :options="$this->eligibleCategories->mapWithKeys(fn ($tc) => [$tc->id => $tc->category->name.($tc->category->level ? ' - '.$tc->category->level : '')])"
                        />

                        @if ($this->isDoublesRegistration)
                            <x-player.registration-select
                                label="Does player pair have an account?"
                                model="pairHasAccount"
                                placeholder="Yes/ No"
                                :live="true"
                                :options="['yes' => 'Yes', 'no' => 'No']"
                            />

                            @if ($pairHasAccount === 'no')
                                <x-player.registration-select
                                    label="Has your pair played a tournament hosted by this organization before?"
                                    model="pairPlayedBefore"
                                    placeholder="Yes/ No"
                                    :live="true"
                                    :options="['yes' => 'Yes', 'no' => 'No']"
                                />
                            @endif
                        @endif
                    @elseif ($this->isPairStep)
                        @if ($this->pairViaSearch)
                            <div class="flex h-[33px] items-center rounded-[8px] border border-[#bcbcbc] pl-[15px]">
                                <input type="text" wire:model.live.debounce.300ms="pairSearch" placeholder="Search by name or email"
                                       class="flex-1 bg-transparent text-[12px] text-black placeholder:text-black/40 focus:outline-none">
                                <div class="flex h-[30px] w-[36px] items-center justify-center">
                                    <flux:icon.magnifying-glass class="size-[17px] text-black/50" />
                                </div>
                            </div>

                            <div class="flex max-h-[300px] flex-col gap-[10px] overflow-y-auto">
                                @forelse ($this->pairSearchResults as $player)
                                    <button type="button" wire:click="selectPairPlayer({{ $player->id }})"
                                            @class([
                                                'flex items-center gap-[20px] rounded-[10px] border px-[15px] py-[10px] text-left cursor-pointer',
                                                'border-[#138177] bg-[#eaf4f3]' => $pairPlayerId === $player->id,
                                                'border-[#d3d3d3] bg-white' => $pairPlayerId !== $player->id,
                                            ])>
                                        <span class="flex size-[24px] shrink-0 items-center justify-center rounded-full bg-[#525591] text-[12px] font-bold text-white">
                                            {{ $player->initials() }}
                                        </span>
                                        <span class="flex-1">
                                            <span class="block text-[11px] font-bold text-black">{{ $player->name }}</span>
                                            <span class="flex gap-[10px] text-[8px] text-black">
                                                <span>{{ $player->age ? $player->age.' yrs. old' : '—' }}</span>
                                                <span>{{ $player->gender ? Str::headline($player->gender) : '—' }}</span>
                                            </span>
                                        </span>
                                        <span @class([
                                            'size-[12px] shrink-0 rounded-full',
                                            'bg-[#138177]' => $pairPlayerId === $player->id,
                                            'bg-[#eaf4f3]' => $pairPlayerId !== $player->id,
                                        ])></span>
                                    </button>
                                @empty
                                    <p class="py-6 text-center text-[11px] text-black/50">
                                        {{ mb_strlen($pairSearch) < 2 ? 'Type at least 2 characters to search.' : 'No matching players found.' }}
                                    </p>
                                @endforelse
                            </div>

                            @error('pairPlayerId')
                                <p class="text-[11px] text-red-600">{{ $message }}</p>
                            @enderror
                        @else
                            <div class="flex gap-[9px]">
                                <x-player.registration-field label="First Name" model="pairFirstName" placeholder="e.g. Juan" class="flex-1" />
                                <x-player.registration-field label="Last Name" model="pairLastName" placeholder="e.g. Cruz" class="flex-1" />
                                <x-player.registration-field label="M.I" model="pairMiddleInitial" placeholder="e.g. D" class="flex-1" />
                            </div>
                            <div class="flex gap-[9px]">
                                <x-player.registration-field label="Birthdate" model="pairBirthDate" type="date" class="flex-1" />
                                <x-player.registration-select label="Gender" model="pairGender" :options="['male' => 'Male', 'female' => 'Female', 'prefer_not_to_say' => 'Prefer not to say']" class="flex-1" />
                                <x-player.registration-field label="Address" model="pairAddress" placeholder="e.g. Purok 5, Ozamiz City" class="flex-1" />
                            </div>
                            <div class="flex gap-[5px]">
                                <x-player.registration-field label="Skill Level" model="pairSkillLevel" placeholder="e.g. Beginner" class="flex-1" />
                                <x-player.registration-field label="Club" model="pairClub" placeholder="e.g. OBC Badminton Club" class="flex-1" />
                            </div>
                            <div class="flex gap-[5px]">
                                <x-player.registration-field label="Email" model="pairEmail" type="email" placeholder="e.g. juancruz@gmail.com" class="flex-1" />
                                <x-player.registration-field label="Phone Number" model="pairPhoneNumber" placeholder="e.g. 09xxxxxxxxx" class="flex-1" />
                            </div>

                            <div x-data="{ fileName: '' }" class="flex flex-col gap-[2px]">
                                <label for="pairPhoto"
                                       class="flex cursor-pointer items-center justify-center rounded-[5px] border border-dashed border-[#777] px-[10px] py-[20px] text-center text-[12px] text-black">
                                    <span x-text="fileName || 'Upload photo of the player'"></span>
                                </label>
                                <input id="pairPhoto" type="file" wire:model="pairPhoto" accept="image/*" class="hidden"
                                       x-on:change="fileName = $event.target.files[0]?.name ?? ''">
                                @error('pairPhoto')
                                    <p class="text-[11px] text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif
                    @else
                        <div class="flex flex-col gap-[10px]">
                            <p class="text-[12px] font-bold text-black">Your valid document</p>
                            <x-player.registration-select label="Type of Document" model="ownDocumentType" placeholder="Philippine National ID/ Passport/ Birth Certificate" :options="$this->documentTypeOptions()" />

                            <div x-data="{ fileName: '' }" class="flex flex-col gap-[2px]">
                                <label class="text-[12px] text-black">Upload attach</label>
                                <label for="ownDocument"
                                       class="flex h-[80px] cursor-pointer items-center justify-center rounded-[5px] border border-dashed border-[#777] px-[10px] text-center text-[12px] text-[#838988]">
                                    <span x-text="fileName || 'Upload photo of the document'"></span>
                                </label>
                                <input id="ownDocument" type="file" wire:model="ownDocument" accept="image/*" class="hidden"
                                       x-on:change="fileName = $event.target.files[0]?.name ?? ''">
                                @error('ownDocument')
                                    <p class="text-[11px] text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        @if ($this->isDoublesRegistration)
                            <div class="flex flex-col gap-[10px] border-t border-[#d3d3d3] pt-[15px]">
                                <p class="text-[12px] font-bold text-black">Pair's valid document</p>
                                <x-player.registration-select label="Type of Document" model="pairDocumentType" placeholder="Philippine National ID/ Passport/ Birth Certificate" :options="$this->documentTypeOptions()" />

                                <div x-data="{ fileName: '' }" class="flex flex-col gap-[2px]">
                                    <label class="text-[12px] text-black">Upload attach</label>
                                    <label for="pairDocument"
                                           class="flex h-[80px] cursor-pointer items-center justify-center rounded-[5px] border border-dashed border-[#777] px-[10px] text-center text-[12px] text-[#838988]">
                                        <span x-text="fileName || 'Upload photo of the document'"></span>
                                    </label>
                                    <input id="pairDocument" type="file" wire:model="pairDocument" accept="image/*" class="hidden"
                                           x-on:change="fileName = $event.target.files[0]?.name ?? ''">
                                    @error('pairDocument')
                                        <p class="text-[11px] text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            </div>

            @error('registration')
                <p class="text-[11px] text-red-600">{{ $message }}</p>
            @enderror

            <div class="grid grid-cols-2 gap-[20px]">
                @if ($regStep === 1)
                    <button type="button" wire:click="cancelRegistration"
                            class="cursor-pointer rounded-[5px] border border-[#a3a3a3] px-[30px] py-[10px] text-center text-[12px] text-[#a3a3a3]">
                        Cancel
                    </button>
                @else
                    <button type="button" wire:click="registrationPreviousStep"
                            class="cursor-pointer rounded-[5px] border border-[#a3a3a3] px-[30px] py-[10px] text-center text-[12px] text-[#a3a3a3]">
                        Back
                    </button>
                @endif

                @if ($this->isDocumentsStep)
                    <button type="button" wire:click="submitRegistration" wire:loading.attr="disabled" wire:target="submitRegistration"
                            class="cursor-pointer rounded-[5px] bg-[#00a89a] px-[30px] py-[10px] text-center text-[12px] text-white disabled:cursor-not-allowed disabled:opacity-60">
                        <span wire:loading.remove wire:target="submitRegistration">Submit Application</span>
                        <span wire:loading wire:target="submitRegistration">Submitting…</span>
                    </button>
                @else
                    <button type="button" wire:click="registrationNextStep"
                            class="cursor-pointer rounded-[5px] bg-[#00a89a] px-[30px] py-[10px] text-center text-[12px] text-white">
                        Next
                    </button>
                @endif
            </div>
        </div>
    </flux:modal>

    <flux:toast />

    @script
    <script>
        this.$on('registration-toast', (event) => {
            window.Flux.toast({ text: event.detail.message, variant: event.detail.variant });
        });
    </script>
    @endscript
</div>
