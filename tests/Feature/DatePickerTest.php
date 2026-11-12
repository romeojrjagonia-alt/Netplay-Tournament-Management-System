<?php

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Livewire\Volt\Volt;

/**
 * The picker's options are emitted as JSON with escaped quotes (a backslash,
 * "u0022"), e.g. model:birthDate.
 */
function pickerOption(string $key, string $value): string
{
    $quote = chr(92).'u0022';

    return $quote.$key.$quote.':'.$quote.$value.$quote;
}

it('renders the year, month and day picker bound to a model', function () {
    $html = Blade::render('<x-date-picker model="birthDate" start="years" max="2026-11-12" />');

    expect($html)
        ->toContain('datePicker(')
        ->toContain(pickerOption('model', 'birthDate'))
        ->toContain(pickerOption('start', 'years'))
        ->toContain(pickerOption('max', '2026-11-12'))
        ->toContain('Select date')
        ->not->toContain('type="date"');
});

it('replaces the native date input in the field components', function () {
    view()->share('errors', new ViewErrorBag);

    $player = Blade::render('<x-player.registration-field label="Birthdate" model="regBirthDate" type="date" start="years" />');
    $auth = Blade::render('<x-auth.field label="Birth Date" model="birthDate" type="date" />');
    $text = Blade::render('<x-player.registration-field label="Name" model="name" />');

    expect($player)->toContain('datePicker(')->not->toContain('type="date"')
        ->and($auth)->toContain('datePicker(')->not->toContain('type="date"')
        ->and($text)->toContain('type="text"')->not->toContain('datePicker(');
});

it('uses the picker for birthdates on the public registration page', function () {
    $tournament = Tournament::create([
        'name' => 'Picker Open',
        'format' => 'single_elimination',
        'sport' => 'Badminton',
        'scoring_system' => 'Best of 3 to 21',
        'date_start' => now()->addDays(10)->toDateString(),
        'date_end' => now()->addDays(11)->toDateString(),
        'registration_start' => now()->subDay()->toDateString(),
        'registration_end' => now()->addDays(5)->toDateString(),
        'status' => 'published',
    ]);

    Volt::test('tournaments.register', ['tournament' => $tournament])
        ->assertSeeHtml('datePicker(')
        ->assertSeeHtml(pickerOption('model', 'regBirthDate'))
        ->assertSeeHtml(pickerOption('max', now()->toDateString()))
        ->assertDontSeeHtml('type="date"');
});

it('uses the picker for the tournament date range and registration window', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));

    $component = Volt::test('admin.events.wizard.details');

    foreach (['dateStart', 'dateEnd', 'registrationStart', 'registrationEnd'] as $field) {
        $component->assertSeeHtml(pickerOption('model', $field));
    }

    $component->assertDontSeeHtml('type="date"');
});
