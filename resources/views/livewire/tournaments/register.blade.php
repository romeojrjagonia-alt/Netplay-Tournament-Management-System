<?php

use App\Mail\ApplicationReceived;
use App\Models\ApplicationPlayer;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.public', ['title' => 'Tournament Registration'])] class extends Component {
    public Tournament $tournament;

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

    /** 'search' picks an existing player by name; 'manual' enters a new partner by hand. */
    public string $pairMode = 'search';

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

    public ?string $submittedReferenceCode = null;

    public function mount(Tournament $tournament): void
    {
        abort_unless(
            Tournament::acceptingRegistrations()->whereKey($tournament->id)->exists(),
            404,
        );

        $this->tournament = $tournament;

        $user = $this->applicantUser();
        $player = $user?->player;

        if ($user) {
            $this->regEmail = $player?->email ?? $user->email;
        }

        if ($player) {
            [$this->regFirstName, $this->regLastName, $this->regMiddleInitial] = $this->splitName($player->name);
            $this->regBirthDate = $player->birth_date?->format('Y-m-d') ?? '';
            $this->regGender = $player->gender ?? '';
            $this->regAddress = $player->address ?? '';
            $this->regSkillLevel = $player->level ?? '';
            $this->regClub = $player->club ?? '';
            $this->regPhoneNumber = $player->phone_number ?? '';
        }
    }

    /**
     * Validate the current step and advance the wizard.
     */
    public function nextStep(): void
    {
        if ($this->regStep === 1) {
            $this->validate($this->profileStepRules());
        } elseif ($this->isPairStep) {
            $this->validate($this->pairStepRules());
        }

        $this->regStep = min($this->regStep + 1, $this->totalSteps);
    }

    public function selectPairPlayer(int $playerId): void
    {
        $this->pairPlayerId = Player::query()->whereKey($playerId)->value('id');
    }

    public function enterPairManually(): void
    {
        $this->pairMode = 'manual';
        $this->pairPlayerId = null;
        $this->resetValidation();
    }

    public function searchPairAgain(): void
    {
        $this->pairMode = 'search';
        $this->resetValidation();
    }

    public function previousStep(): void
    {
        $this->regStep = max($this->regStep - 1, 1);
    }

    /**
     * Create the application. Walk-ins have no account, so their player
     * records are matched by phone/email instead of being created blindly
     * (see NETPLAY.md 4.3) and are never overwritten by a stranger's input.
     */
    public function submit(): void
    {
        $this->validate($this->profileStepRules());

        if ($this->isDoublesRegistration) {
            $this->validate($this->pairStepRules());
        }

        // Staff entering a field of walk-ins from one desk aren't throttled.
        if (! auth()->user()?->isAdmin()) {
            $throttleKey = 'tournament-register:'.request()->ip();

            if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
                $this->addError('registration', 'Too many registrations from this device. Please try again later.');
                $this->dispatch('system-message', title: 'Too many registrations', message: 'Too many registrations from this device. Please try again later.', variant: 'danger');

                return;
            }

            RateLimiter::hit($throttleKey, 3600);
        }

        $tournamentCategoryId = $this->regTournamentCategoryId;

        try {
            $application = DB::transaction(function () use ($tournamentCategoryId): PlayerApplication {
                TournamentCategory::lockAndAssertOpenSlot($tournamentCategoryId);

                $applicant = $this->resolveApplicantPlayer();
                $this->assertNotAlreadyEntered($applicant, $tournamentCategoryId);

                $partner = null;

                if ($this->isDoublesRegistration) {
                    $partner = $this->resolvePartnerPlayer();

                    if ($partner->is($applicant)) {
                        throw new \RuntimeException('Your partner must be a different player.');
                    }

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
                ]);

                if ($partner) {
                    ApplicationPlayer::create([
                        'application_id' => $application->id,
                        'player_id' => $partner->id,
                        'level_at_apply' => $this->pairMode === 'search' ? ($partner->level ?? 'Unranked') : $this->pairSkillLevel,
                    ]);
                }

                return $application;
            });
        } catch (\RuntimeException $e) {
            $this->addError('registration', $e->getMessage());
            $this->dispatch('system-message', title: 'Could not submit application', message: $e->getMessage(), variant: 'danger');

            return;
        }

        $this->sendReferenceCodeEmail($application);

        $this->submittedReferenceCode = $application->reference_code;
    }

    /**
     * Existing players matching the partner search. Only ids and names are
     * loaded, so nothing else about a player is exposed on the public form.
     *
     * @return \Illuminate\Support\Collection<int, Player>
     */
    #[Computed]
    public function pairSearchResults()
    {
        if (mb_strlen(trim($this->pairSearch)) < 2) {
            return collect();
        }

        return Player::query()
            ->select(['id', 'name'])
            ->where('name', 'like', '%'.trim($this->pairSearch).'%')
            ->when($this->applicantUser()?->player, fn ($query, $player) => $query->where('id', '!=', $player->id))
            ->orderBy('name')
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function selectedPairPlayer(): ?Player
    {
        return $this->pairPlayerId
            ? Player::query()->select(['id', 'name'])->find($this->pairPlayerId)
            : null;
    }

    #[Computed]
    public function regAge(): ?int
    {
        return $this->regBirthDate !== '' ? Carbon::parse($this->regBirthDate)->age : null;
    }

    /**
     * Categories in this tournament the applicant, based on the gender and
     * birth date entered, is eligible to register for.
     *
     * @return \Illuminate\Support\Collection<int, TournamentCategory>
     */
    #[Computed]
    public function eligibleCategories()
    {
        return TournamentCategory::query()
            ->where('tournament_id', $this->tournament->id)
            ->with('category')
            ->eligibleFor($this->regGender !== '' ? $this->regGender : null, $this->regAge)
            ->get();
    }

    /**
     * Ids of the eligible categories that have no entry slot left.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    #[Computed]
    public function fullCategoryIds()
    {
        return $this->eligibleCategories->filter->isFull()->pluck('id');
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
    public function totalSteps(): int
    {
        return $this->isDoublesRegistration ? 3 : 2;
    }

    #[Computed]
    public function isPairStep(): bool
    {
        return $this->isDoublesRegistration && $this->regStep === 2;
    }

    #[Computed]
    public function isReviewStep(): bool
    {
        return $this->regStep === $this->totalSteps;
    }

    public function formatDateRange(): string
    {
        if ($this->tournament->date_start->isSameMonth($this->tournament->date_end)) {
            return $this->tournament->date_start->format('F j').' - '.$this->tournament->date_end->format('j, Y');
        }

        return $this->tournament->date_start->format('F j, Y').' - '.$this->tournament->date_end->format('F j, Y');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function profileStepRules(): array
    {
        return [
            'regTournamentCategoryId' => ['required', Rule::in($this->eligibleCategories->pluck('id'))],
            'regFirstName' => ['required', 'string', 'max:255'],
            'regLastName' => ['required', 'string', 'max:255'],
            'regMiddleInitial' => ['nullable', 'string', 'max:5'],
            'regBirthDate' => ['required', 'date', 'before:today'],
            'regGender' => ['required', 'in:male,female,prefer_not_to_say'],
            'regAddress' => ['required', 'string', 'max:255'],
            'regSkillLevel' => ['required', 'string', 'max:255'],
            'regClub' => ['nullable', 'string', 'max:255'],
            'regEmail' => ['nullable', 'email', 'max:255', 'required_without:regPhoneNumber'],
            'regPhoneNumber' => ['nullable', 'string', 'max:255', 'required_without:regEmail'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function pairStepRules(): array
    {
        if ($this->pairMode === 'search') {
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
            'pairEmail' => ['nullable', 'email', 'max:255', 'required_without:pairPhoneNumber'],
            'pairPhoneNumber' => ['nullable', 'string', 'max:255', 'required_without:pairEmail'],
        ];
    }

    /**
     * The signed-in player this registration is for. An admin registering a
     * walk-in is staff acting for someone else, so their own account is
     * never prefilled into, or linked to, the walk-in's player record.
     */
    private function applicantUser(): ?\App\Models\User
    {
        $user = auth()->user();

        return $user && ! $user->isAdmin() ? $user : null;
    }

    /**
     * A signed-in player's own record is kept in sync with what they enter;
     * a guest is matched to (or added as) a player record without touching
     * anyone else's details.
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
            'phone_number' => $this->normalizePhone($this->regPhoneNumber),
        ];

        $ownPlayer = $this->applicantUser()?->player;

        if ($ownPlayer) {
            $ownPlayer->update($attributes);

            return $ownPlayer;
        }

        $player = $this->findOrCreatePlayer($attributes);

        if ($this->applicantUser() && $player->user_id === null) {
            $player->update(['user_id' => $this->applicantUser()->id]);
        }

        return $player;
    }

    /**
     * The partner is either an existing player picked from the name search,
     * or a new player entered by hand (still matched by phone/email).
     */
    private function resolvePartnerPlayer(): Player
    {
        if ($this->pairMode === 'search') {
            return Player::query()->findOrFail($this->pairPlayerId);
        }

        return $this->findOrCreatePlayer([
            'name' => $this->combineName($this->pairFirstName, $this->pairMiddleInitial, $this->pairLastName),
            'birth_date' => $this->pairBirthDate,
            'gender' => $this->pairGender,
            'address' => $this->pairAddress,
            'club' => $this->pairClub !== '' ? $this->pairClub : null,
            'level' => $this->pairSkillLevel,
            'email' => $this->pairEmail !== '' ? $this->pairEmail : null,
            'phone_number' => $this->normalizePhone($this->pairPhoneNumber),
        ]);
    }

    /**
     * Reuse the player who already has this phone number (or email) so match
     * history and skill level stay in one place; only blank fields on the
     * existing record are filled in.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function findOrCreatePlayer(array $attributes): Player
    {
        $existing = null;

        if ($attributes['phone_number']) {
            $existing = Player::query()->where('phone_number', $attributes['phone_number'])->first();
        }

        if (! $existing && $attributes['email']) {
            $email = mb_strtolower($attributes['email']);

            // Account holders keep their email on the user, so check it too.
            $existing = Player::query()->whereRaw('lower(email) = ?', [$email])->first()
                ?? Player::query()->whereHas('user', fn ($query) => $query->whereRaw('lower(email) = ?', [$email]))->first();
        }

        if (! $existing) {
            return Player::create($attributes);
        }

        $existing->fill(collect($attributes)
            ->filter(fn ($value, $key) => $value !== null && $existing->{$key} === null)
            ->all())->save();

        return $existing;
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

    private function sendReferenceCodeEmail(PlayerApplication $application): void
    {
        if ($this->regEmail === '') {
            return;
        }

        try {
            Mail::to($this->regEmail)->send(new ApplicationReceived($application));
        } catch (\Throwable $e) {
            // The on-screen code is the source of truth; a mail outage must not undo the registration.
            Log::warning('Could not email application reference code.', ['application' => $application->id, 'error' => $e->getMessage()]);
        }
    }

    private function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/[^\d+]/', '', $phone);

        if (str_starts_with($digits, '+63')) {
            $digits = '0'.substr($digits, 3);
        }

        return $digits !== '' ? $digits : null;
    }

    private function combineName(string $first, string $middleInitial, string $last): string
    {
        return collect([$first, $middleInitial !== '' ? rtrim($middleInitial, '.').'.' : null, $last])
            ->filter()
            ->implode(' ');
    }

    /**
     * Best-effort split of a combined "First M. Last" name back into its
     * parts, for pre-filling the form from a signed-in player's record.
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
    <div>
        <p class="text-sm text-teal-800">Tournament Registration</p>
        <h1 class="text-2xl font-bold text-black">{{ $tournament->name }}</h1>
        <p class="text-xs text-black/70">
            {{ $this->formatDateRange() }}@if ($tournament->location) &middot; {{ $tournament->location }}@endif
        </p>
    </div>

    @if ($submittedReferenceCode)
        <div class="flex flex-col items-center gap-4 rounded-[10px] border border-[#138177] bg-white p-6 text-center">
            <p class="text-lg font-bold text-black">Application submitted!</p>
            <p class="max-w-md text-sm text-black/70">
                The organizers will review your application. Keep this reference code — you need it to check your
                application progress and to pay once you're approved.
            </p>

            <div x-data="{ copied: false }" class="flex flex-col items-center gap-2">
                <span class="rounded-[10px] bg-[#eaf4f3] px-6 py-3 text-3xl font-bold tracking-widest text-[#0b4742]">{{ $submittedReferenceCode }}</span>
                <button type="button"
                        x-on:click="navigator.clipboard?.writeText(@js($submittedReferenceCode)); copied = true"
                        class="cursor-pointer text-xs text-[#138177] underline">
                    <span x-show="!copied">Copy code</span>
                    <span x-show="copied" style="display: none;">Copied!</span>
                </button>
            </div>

            @if ($regEmail !== '')
                <p class="text-xs text-black/60">We also sent the code to {{ $regEmail }}.</p>
            @endif

            <a href="{{ route('home') }}#check-application" wire:navigate
               class="rounded-[5px] bg-[#00a89a] px-[30px] py-[10px] text-center text-[12px] text-white">
                Check application progress
            </a>
        </div>
    @else
        <div>
            <div class="flex items-center justify-between rounded-t-[5px] bg-[#138177] px-[15px] py-[10px] text-white">
                <span class="text-[11px] font-bold">
                    @if ($this->isReviewStep)
                        Review &amp; Submit
                    @elseif ($this->isPairStep)
                        Paired Player
                    @else
                        Player Profile
                    @endif
                </span>
                <span class="text-[11px] italic">Step {{ $regStep }} of {{ $this->totalSteps }}</span>
            </div>

            <div class="flex flex-col gap-[15px] rounded-b-[5px] border border-[#138177] bg-white p-[15px]">
                @if ($regStep === 1)
                    <div class="grid grid-cols-1 gap-[9px] sm:grid-cols-3">
                        <x-player.registration-field label="First Name" model="regFirstName" placeholder="e.g. Juan" />
                        <x-player.registration-field label="Last Name" model="regLastName" placeholder="e.g. Cruz" />
                        <x-player.registration-field label="M.I" model="regMiddleInitial" placeholder="e.g. D" />
                    </div>
                    <div class="grid grid-cols-1 gap-[9px] sm:grid-cols-3">
                        <x-player.registration-field label="Birthdate" model="regBirthDate" type="date" :live="true" />
                        <x-player.registration-select label="Gender" model="regGender" :live="true" :options="['male' => 'Male', 'female' => 'Female', 'prefer_not_to_say' => 'Prefer not to say']" />
                        <x-player.registration-field label="Address" model="regAddress" placeholder="e.g. Purok 5, Ozamiz City" />
                    </div>
                    <div class="grid grid-cols-1 gap-[9px] sm:grid-cols-2">
                        <x-player.registration-field label="Skill Level" model="regSkillLevel" placeholder="e.g. Beginner" />
                        <x-player.registration-field label="Club (optional)" model="regClub" placeholder="e.g. OBC Badminton Club" />
                    </div>
                    <div class="grid grid-cols-1 gap-[9px] sm:grid-cols-2">
                        <x-player.registration-field label="Phone Number (phone or email required)" model="regPhoneNumber" placeholder="e.g. 09xxxxxxxxx" />
                        <x-player.registration-field label="Email (phone or email required — we'll send your reference code)" model="regEmail" type="email" placeholder="e.g. juancruz@gmail.com" />
                    </div>

                    <x-player.registration-select
                        label="Category"
                        model="regTournamentCategoryId"
                        placeholder="Select category"
                        :live="true"
                        :options="$this->eligibleCategories->mapWithKeys(fn ($tc) => [$tc->id => $tc->category_label.' — ₱'.number_format((float) $tc->registration_fee, 2).($this->fullCategoryIds->contains($tc->id) ? ' (Full)' : '')])"
                        :disabled-options="$this->fullCategoryIds->all()"
                    />
                @elseif ($this->isPairStep)
                    @if ($pairMode === 'search')
                        <p class="text-xs text-black/60">Search for your partner by name. Players who have joined before are already in our records.</p>

                        <div class="flex h-[33px] items-center rounded-[8px] border border-[#bcbcbc] pl-[15px]">
                            <input type="text" wire:model.live.debounce.300ms="pairSearch" placeholder="Search by name"
                                   class="flex-1 bg-transparent text-[12px] text-black placeholder:text-black/40 focus:outline-none">
                            <div class="flex h-[30px] w-[36px] items-center justify-center">
                                <flux:icon.magnifying-glass class="size-[17px] text-black/50" />
                            </div>
                        </div>

                        <div class="flex max-h-[260px] flex-col gap-[8px] overflow-y-auto">
                            @forelse ($this->pairSearchResults as $player)
                                <button type="button" wire:click="selectPairPlayer({{ $player->id }})" wire:key="pair-{{ $player->id }}"
                                        @class([
                                            'flex items-center justify-between gap-3 rounded-[10px] border px-[15px] py-[10px] text-left text-[12px] font-bold text-black cursor-pointer',
                                            'border-[#138177] bg-[#eaf4f3]' => $pairPlayerId === $player->id,
                                            'border-[#d3d3d3] bg-white' => $pairPlayerId !== $player->id,
                                        ])>
                                    <span>{{ $player->name }}</span>
                                    <span @class([
                                        'size-[12px] shrink-0 rounded-full',
                                        'bg-[#138177]' => $pairPlayerId === $player->id,
                                        'bg-[#eaf4f3]' => $pairPlayerId !== $player->id,
                                    ])></span>
                                </button>
                            @empty
                                <p class="py-4 text-center text-[11px] text-black/50">
                                    {{ mb_strlen(trim($pairSearch)) < 2 ? 'Type at least 2 characters to search.' : 'No matching players found.' }}
                                </p>
                            @endforelse
                        </div>

                        @error('pairPlayerId')
                            <p class="text-[11px] text-red-600">Pick your partner from the list, or enter their details.</p>
                        @enderror

                        <button type="button" wire:click="enterPairManually"
                                class="cursor-pointer self-start text-[12px] text-[#138177] underline">
                            My partner isn't listed — enter their details
                        </button>
                    @else
                        <p class="text-xs text-black/60">
                            Enter your partner's details. If they've played with us before, we'll match their phone number
                            or email to their existing record.
                        </p>

                        <div class="grid grid-cols-1 gap-[9px] sm:grid-cols-3">
                            <x-player.registration-field label="First Name" model="pairFirstName" placeholder="e.g. Juan" />
                            <x-player.registration-field label="Last Name" model="pairLastName" placeholder="e.g. Cruz" />
                            <x-player.registration-field label="M.I" model="pairMiddleInitial" placeholder="e.g. D" />
                        </div>
                        <div class="grid grid-cols-1 gap-[9px] sm:grid-cols-3">
                            <x-player.registration-field label="Birthdate" model="pairBirthDate" type="date" />
                            <x-player.registration-select label="Gender" model="pairGender" :options="['male' => 'Male', 'female' => 'Female', 'prefer_not_to_say' => 'Prefer not to say']" />
                            <x-player.registration-field label="Address" model="pairAddress" placeholder="e.g. Purok 5, Ozamiz City" />
                        </div>
                        <div class="grid grid-cols-1 gap-[9px] sm:grid-cols-2">
                            <x-player.registration-field label="Skill Level" model="pairSkillLevel" placeholder="e.g. Beginner" />
                            <x-player.registration-field label="Club (optional)" model="pairClub" placeholder="e.g. OBC Badminton Club" />
                        </div>
                        <div class="grid grid-cols-1 gap-[9px] sm:grid-cols-2">
                            <x-player.registration-field label="Phone Number (phone or email required)" model="pairPhoneNumber" placeholder="e.g. 09xxxxxxxxx" />
                            <x-player.registration-field label="Email (phone or email required)" model="pairEmail" type="email" placeholder="e.g. juancruz@gmail.com" />
                        </div>

                        <button type="button" wire:click="searchPairAgain"
                                class="cursor-pointer self-start text-[12px] text-[#138177] underline">
                            Search existing players instead
                        </button>
                    @endif
                @else
                    <dl class="flex flex-col gap-2 text-xs text-black">
                        <div class="flex justify-between gap-4">
                            <dt class="text-black/60">Category</dt>
                            <dd class="text-right font-bold">{{ $this->selectedTournamentCategory?->category_label }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-black/60">Registration fee</dt>
                            <dd class="font-bold">₱{{ number_format((float) $this->selectedTournamentCategory?->registration_fee, 2) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-black/60">Player</dt>
                            <dd class="text-right font-bold">{{ $regFirstName }} {{ $regLastName }} · {{ $regPhoneNumber !== '' ? $regPhoneNumber : $regEmail }}</dd>
                        </div>
                        @if ($this->isDoublesRegistration)
                            <div class="flex justify-between gap-4">
                                <dt class="text-black/60">Partner</dt>
                                <dd class="text-right font-bold">{{ $pairMode === 'search' ? $this->selectedPairPlayer?->name : trim($pairFirstName.' '.$pairLastName) }}</dd>
                            </div>
                        @endif
                    </dl>

                    <p class="text-xs text-black/60">
                        After you submit, you'll get a reference code. Once the organizers approve your application, use that
                        code on the home page to pay the registration fee.
                    </p>
                @endif
            </div>
        </div>


        <div class="grid grid-cols-2 gap-5">
            @if ($regStep === 1)
                <a href="{{ route('home') }}" wire:navigate
                   class="rounded-[5px] border border-[#a3a3a3] px-[30px] py-[10px] text-center text-[12px] text-[#a3a3a3]">
                    Cancel
                </a>
            @else
                <button type="button" wire:click="previousStep"
                        class="cursor-pointer rounded-[5px] border border-[#a3a3a3] px-[30px] py-[10px] text-center text-[12px] text-[#a3a3a3]">
                    Back
                </button>
            @endif

            @if ($this->isReviewStep)
                <button type="button" wire:click="submit" wire:loading.attr="disabled" wire:target="submit"
                        class="cursor-pointer rounded-[5px] bg-[#00a89a] px-[30px] py-[10px] text-center text-[12px] text-white disabled:cursor-not-allowed disabled:opacity-60">
                    <span wire:loading.remove wire:target="submit">Submit Application</span>
                    <span wire:loading wire:target="submit">Submitting…</span>
                </button>
            @else
                <button type="button" wire:click="nextStep"
                        class="cursor-pointer rounded-[5px] bg-[#00a89a] px-[30px] py-[10px] text-center text-[12px] text-white">
                    Next
                </button>
            @endif
        </div>
    @endif
</div>
