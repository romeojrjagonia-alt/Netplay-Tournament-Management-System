<?php

use App\Models\Category;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use Livewire\Volt\Volt;

function categoriesAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function categoriesTournament(string $status = 'draft'): Tournament
{
    return Tournament::create([
        'name' => 'Inato Tournament',
        'format' => 'round_robin',
        'sport' => 'Badminton',
        'scoring_system' => 'Best of 3 to 21',
        'date_start' => '2026-09-01',
        'date_end' => '2026-09-30',
        'registration_start' => '2026-06-01',
        'registration_end' => '2026-12-31',
        'status' => $status,
    ]);
}

function categoriesLibraryEntry(array $overrides = []): Category
{
    return Category::create(array_merge([
        'name' => "Men's Singles",
        'gender' => 'male',
        'team_size' => 1,
        'level' => 'Beginner',
        'age_min' => null,
        'age_max' => null,
    ], $overrides));
}

function categoriesUse(Tournament $tournament, Category $category): TournamentCategory
{
    return TournamentCategory::create([
        'tournament_id' => $tournament->id,
        'category_id' => $category->id,
        'registration_fee' => 200,
        'max_slots' => 16,
        'status' => 'open',
    ]);
}

test('a non-admin cannot open the categories page', function () {
    $this->actingAs(User::factory()->create(['role' => 'player']));

    Volt::test('admin.categories.index')->assertForbidden();
});

test('the page is reachable by an admin', function () {
    $this->actingAs(categoriesAdmin())
        ->get(route('admin.categories.index'))
        ->assertOk()
        ->assertSee('Create Categories');
});

test('an admin creates a category from gender, level and age', function () {
    $this->actingAs(categoriesAdmin());

    Volt::test('admin.categories.index')
        ->set('categoryType', 'mixed_doubles')
        ->set('level', 'Level B')
        ->set('ageMin', '18')
        ->set('ageMax', '35')
        ->call('save')
        ->assertHasNoErrors();

    $category = Category::first();

    expect($category->name)->toBe('Mixed Doubles');
    expect($category->gender)->toBe('mixed');
    expect($category->team_size)->toBe(2);
    expect($category->level)->toBe('Level B');
    expect([$category->age_min, $category->age_max])->toBe([18, 35]);
});

test('leaving age empty creates an open all-ages category', function () {
    $this->actingAs(categoriesAdmin());

    Volt::test('admin.categories.index')
        ->set('categoryType', 'mens_singles')
        ->set('level', 'Beginner')
        ->call('save')
        ->assertSee('Open (all ages)');

    $category = Category::first();

    expect($category->age_min)->toBeNull();
    expect($category->age_max)->toBeNull();
});

test('level must come from the preset list', function () {
    $this->actingAs(categoriesAdmin());

    Volt::test('admin.categories.index')
        ->set('categoryType', 'mens_singles')
        ->set('level', 'Grandmaster')
        ->call('save')
        ->assertHasErrors('level');

    expect(Category::count())->toBe(0);
});

test('a duplicate category shows a warning modal and is not created', function () {
    categoriesLibraryEntry(['name' => "Men's Doubles", 'team_size' => 2, 'level' => 'Level C', 'age_min' => 18, 'age_max' => 35]);

    $this->actingAs(categoriesAdmin());

    Volt::test('admin.categories.index')
        ->set('categoryType', 'mens_doubles')
        ->set('level', 'Level C')
        ->set('ageMin', '18')
        ->set('ageMax', '35')
        ->call('save')
        ->assertSet('showDuplicateWarning', true)
        ->assertSee('Duplicate category');

    expect(Category::count())->toBe(1);
});

test('an open-age category is only a duplicate of another open-age category', function () {
    categoriesLibraryEntry(['age_min' => 18, 'age_max' => 35]);

    $this->actingAs(categoriesAdmin());

    Volt::test('admin.categories.index')
        ->set('categoryType', 'mens_singles')
        ->set('level', 'Beginner')
        ->call('save')
        ->assertSet('showDuplicateWarning', false);

    expect(Category::count())->toBe(2);
});

test('an admin can edit a category', function () {
    $category = categoriesLibraryEntry();

    $this->actingAs(categoriesAdmin());

    Volt::test('admin.categories.index')
        ->call('edit', $category->id)
        ->assertSet('categoryType', 'mens_singles')
        ->assertSet('level', 'Beginner')
        ->set('level', 'Level A')
        ->set('ageMin', '40')
        ->call('save')
        ->assertSet('editingId', null);

    $category->refresh();

    expect($category->level)->toBe('Level A');
    expect($category->age_min)->toBe(40);
    expect(Category::count())->toBe(1);
});

test('saving an edit that matches another category triggers the duplicate warning', function () {
    categoriesLibraryEntry(['level' => 'Level A']);
    $category = categoriesLibraryEntry(['level' => 'Level B']);

    $this->actingAs(categoriesAdmin());

    Volt::test('admin.categories.index')
        ->call('edit', $category->id)
        ->set('level', 'Level A')
        ->call('save')
        ->assertSet('showDuplicateWarning', true);

    expect($category->fresh()->level)->toBe('Level B');
});

test('a category used by a published event is locked from editing and deleting', function () {
    $category = categoriesLibraryEntry();
    categoriesUse(categoriesTournament('published'), $category);

    $this->actingAs(categoriesAdmin());

    Volt::test('admin.categories.index')
        ->assertSee('Locked')
        ->call('edit', $category->id)
        ->assertSet('editingId', null)
        ->call('delete', $category->id);

    expect(Category::count())->toBe(1);
});

test('a category used only by a draft can be edited but not deleted', function () {
    $category = categoriesLibraryEntry();
    categoriesUse(categoriesTournament('draft'), $category);

    $this->actingAs(categoriesAdmin());

    Volt::test('admin.categories.index')
        ->call('edit', $category->id)
        ->assertSet('editingId', $category->id)
        ->call('delete', $category->id);

    expect(Category::count())->toBe(1);
});

test('an unused category can be deleted', function () {
    $category = categoriesLibraryEntry();

    $this->actingAs(categoriesAdmin());

    Volt::test('admin.categories.index')->call('delete', $category->id);

    expect(Category::count())->toBe(0);
});

test('the event modal adds a category with its fee and max players', function () {
    $tournament = categoriesTournament();
    $category = categoriesLibraryEntry();

    $this->actingAs(categoriesAdmin());

    Volt::test('admin.events.show', ['tournament' => $tournament])
        ->call('openCategoryModal')
        ->assertSet('showCategoryModal', true)
        ->set("newFee.{$category->id}", '300')
        ->set("newSlots.{$category->id}", '16')
        ->call('addCategory', $category->id)
        ->assertHasNoErrors();

    $added = $tournament->tournamentCategories()->first();

    expect($added->category_id)->toBe($category->id);
    expect((float) $added->registration_fee)->toBe(300.0);
    expect($added->max_slots)->toBe(16);
});

test('adding a category to an event requires a fee and max players', function () {
    $tournament = categoriesTournament();
    $category = categoriesLibraryEntry();

    $this->actingAs(categoriesAdmin());

    Volt::test('admin.events.show', ['tournament' => $tournament])
        ->call('addCategory', $category->id)
        ->assertHasErrors(["newFee.{$category->id}", "newSlots.{$category->id}"]);

    expect($tournament->tournamentCategories()->count())->toBe(0);
});

test('the event modal edits the fee and max players of an added category', function () {
    $tournament = categoriesTournament();
    $added = categoriesUse($tournament, categoriesLibraryEntry());

    $this->actingAs(categoriesAdmin());

    Volt::test('admin.events.show', ['tournament' => $tournament])
        ->call('openCategoryModal')
        ->assertSet("editFee.{$added->id}", '200.00')
        ->set("editFee.{$added->id}", '450')
        ->set("editSlots.{$added->id}", '32')
        ->call('saveCategoryTerms', $added->id)
        ->assertHasNoErrors();

    $added->refresh();

    expect((float) $added->registration_fee)->toBe(450.0);
    expect($added->max_slots)->toBe(32);
});

test('the event modal removes an added category', function () {
    $tournament = categoriesTournament();
    $added = categoriesUse($tournament, categoriesLibraryEntry());

    $this->actingAs(categoriesAdmin());

    Volt::test('admin.events.show', ['tournament' => $tournament])
        ->call('removeCategory', $added->id);

    expect($tournament->tournamentCategories()->count())->toBe(0);
});

test('categories cannot be changed on a published event', function () {
    $tournament = categoriesTournament('published');
    $category = categoriesLibraryEntry();

    $this->actingAs(categoriesAdmin());

    Volt::test('admin.events.show', ['tournament' => $tournament])
        ->call('openCategoryModal')
        ->assertForbidden();

    Volt::test('admin.events.show', ['tournament' => $tournament])
        ->set("newFee.{$category->id}", '300')
        ->set("newSlots.{$category->id}", '16')
        ->call('addCategory', $category->id)
        ->assertForbidden();

    expect($tournament->tournamentCategories()->count())->toBe(0);
});

test('an event cannot be published until it has a category, then can be', function () {
    $tournament = categoriesTournament();

    $this->actingAs(categoriesAdmin());

    Volt::test('admin.events.show', ['tournament' => $tournament])
        ->call('togglePublish')
        ->assertSee('Add at least one category before publishing this event.');

    expect($tournament->fresh()->status)->toBe('draft');

    categoriesUse($tournament, categoriesLibraryEntry());

    Volt::test('admin.events.show', ['tournament' => $tournament])->call('togglePublish');

    expect($tournament->fresh()->status)->toBe('published');
});
