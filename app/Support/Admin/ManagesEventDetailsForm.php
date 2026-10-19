<?php

namespace App\Support\Admin;

use App\Models\Tournament;
use App\Support\Badminton\GameRules;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;

/**
 * Shared event-details form state, population, and persistence — used by
 * both the create wizard's step 1 and the standalone edit page, which are
 * separate Volt components (each mapped to its own route) so each stays
 * directly testable via Volt::test() rather than by inferring which route
 * was hit.
 */
trait ManagesEventDetailsForm
{
    use WithFileUploads;

    public ?Tournament $tournament = null;

    public string $name = '';

    public string $format = '';

    public string $location = '';

    public string $scoringSystem = '';

    public string $dateStart = '';

    public string $dateEnd = '';

    public string $timeStart = '';

    public string $timeEnd = '';

    public string $registrationStart = '';

    public string $registrationEnd = '';

    public string $tiebreakerFirst = '';

    public string $tiebreakerSecond = '';

    /** @var array<int, string> */
    public array $selectedDays = [];

    public $poster = null;

    /**
     * The pointing systems GameRules implements — a dropdown rather than
     * free text so every event scores one of the supported ways. Keys are
     * the stored `scoring_system` values.
     *
     * @var array<string, string>
     */
    public array $scoringSystems = [
        GameRules::SYSTEM_BEST_OF_THREE => '21-Point: Best of 3',
        GameRules::SYSTEM_SINGLE_SET => '31-Point: Single Set (first to 31)',
    ];

    /** @var array<int, string> */
    public array $weekOrder = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    /**
     * Populate every field from an existing tournament, e.g. when resuming
     * a draft or opening the edit page.
     */
    protected function fillFromTournament(Tournament $tournament): void
    {
        $this->tournament = $tournament;
        $this->name = $tournament->name;
        $this->format = $tournament->format;
        $this->location = $tournament->location ?? '';
        $this->scoringSystem = $tournament->scoring_system;
        $this->dateStart = $tournament->date_start->format('Y-m-d');
        $this->dateEnd = $tournament->date_end->format('Y-m-d');
        $this->registrationStart = $tournament->registration_start->format('Y-m-d');
        $this->registrationEnd = $tournament->registration_end->format('Y-m-d');
        $this->tiebreakerFirst = $tournament->tiebreaker_rules[0] ?? '';
        $this->tiebreakerSecond = $tournament->tiebreaker_rules[1] ?? '';
        $this->selectedDays = $tournament->specific_days
            ? array_map('trim', explode(',', $tournament->specific_days))
            : [];

        if ($tournament->time) {
            [$startLabel, $endLabel] = array_pad(explode(' - ', $tournament->time, 2), 2, null);
            $this->timeStart = $startLabel ? Carbon::parse($startLabel)->format('H:i') : '';
            $this->timeEnd = $endLabel ? Carbon::parse($endLabel)->format('H:i') : '';
        }
    }

    /**
     * The image shown in the cover upload box: the freshly picked file, the
     * tournament's existing poster when editing, or null to show the
     * "Click to Upload" placeholder.
     */
    #[Computed]
    public function posterPreviewUrl(): ?string
    {
        if ($this->poster) {
            // A non-image upload (rejected by validation on submit, but
            // still rendered until then) has no previewable temporary URL.
            return $this->poster->isPreviewable() ? $this->poster->temporaryUrl() : null;
        }

        return $this->tournament?->poster_url;
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateDetails(): array
    {
        return $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'format' => ['required', 'in:single_elimination,round_robin'],
            'location' => ['nullable', 'string', 'max:255'],
            'scoringSystem' => ['required', Rule::in(array_keys($this->scoringSystems))],
            'dateStart' => ['required', 'date'],
            'dateEnd' => ['required', 'date', 'after_or_equal:dateStart'],
            'timeStart' => ['nullable', 'date_format:H:i', 'required_with:timeEnd'],
            'timeEnd' => ['nullable', 'date_format:H:i', 'required_with:timeStart', 'after:timeStart'],
            'registrationStart' => ['required', 'date'],
            'registrationEnd' => ['required', 'date', 'after_or_equal:registrationStart'],
            'selectedDays' => ['required', 'array', 'min:1'],
            'tiebreakerFirst' => ['nullable', Rule::in(array_keys(Tournament::TIEBREAKER_RULES))],
            'tiebreakerSecond' => ['nullable', Rule::in(array_keys(Tournament::TIEBREAKER_RULES)), 'different:tiebreakerFirst'],
            'poster' => ['nullable', 'image', 'max:5120'],
        ]);
    }

    /**
     * Persist validated data, creating the tournament as a draft the first
     * time through so the admin can resume later — see NETPLAY.md's admin
     * workflow for tournament setup.
     *
     * @param  array<string, mixed>  $data
     */
    protected function saveDetails(array $data): void
    {
        $attributes = $this->detailAttributes($data);

        if ($this->poster) {
            // Replacing an existing cover — remove the old file so it
            // doesn't linger orphaned on disk (NETPLAY.md 4.9).
            if ($this->tournament?->poster_path) {
                Storage::disk('public')->delete($this->tournament->poster_path);
            }

            $attributes['poster_path'] = $this->poster->store('tournament-posters', 'public');
        }

        if ($this->tournament) {
            $this->tournament->update($attributes);
        } else {
            $this->tournament = Tournament::create([...$attributes, 'status' => 'draft']);
        }
    }

    /**
     * Whether saving `$data` would change anything a generated schedule
     * depends on: the date range, daily time window, days of the week or
     * format.
     *
     * @param  array<string, mixed>  $data
     */
    protected function changesGeneratedSchedule(array $data): bool
    {
        if (! $this->tournament) {
            return false;
        }

        $new = $this->detailAttributes($data);
        $current = $this->tournament;

        return $current->date_start->format('Y-m-d') !== Carbon::parse($new['date_start'])->format('Y-m-d')
            || $current->date_end->format('Y-m-d') !== Carbon::parse($new['date_end'])->format('Y-m-d')
            || $current->time !== $new['time']
            || $current->specific_days !== $new['specific_days']
            || $current->format !== $new['format'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function detailAttributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'format' => $data['format'],
            'sport' => 'Badminton',
            'location' => $data['location'],
            'scoring_system' => $data['scoringSystem'],
            'date_start' => $data['dateStart'],
            'date_end' => $data['dateEnd'],
            'time' => $data['timeStart'] && $data['timeEnd']
                ? Carbon::createFromFormat('H:i', $data['timeStart'])->format('g:i A').' - '.Carbon::createFromFormat('H:i', $data['timeEnd'])->format('g:i A')
                : null,
            'specific_days' => collect($this->weekOrder)->filter(fn ($day) => in_array($day, $data['selectedDays'], true))->implode(', '),
            'registration_start' => $data['registrationStart'],
            'registration_end' => $data['registrationEnd'],
            // Only round robin uses tiebreakers; the second is meaningless without the first.
            'tiebreaker_rules' => $data['format'] === 'round_robin'
                ? (array_values(array_filter([$data['tiebreakerFirst'] ?? null, $data['tiebreakerSecond'] ?? null])) ?: null)
                : null,
        ];
    }
}
