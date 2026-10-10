<?php

use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Livewire\Volt\Volt;

test('no view falls back to native browser confirms or toasts', function () {
    $offenders = collect(File::allFiles(resource_path('views')))
        ->filter(fn ($file) => str_ends_with($file->getFilename(), '.blade.php'))
        ->filter(fn ($file) => preg_match('/wire:confirm|Flux\.toast|<flux:toast|admin-toast|registration-toast/', $file->getContents()))
        ->map(fn ($file) => $file->getRelativePathname())
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});

test('deleting a category asks for confirmation in the system modal', function () {
    Category::create(['name' => "Men's Singles", 'gender' => 'male', 'team_size' => 1, 'level' => 'Level C']);

    $this->actingAs(User::factory()->create(['role' => 'admin']));

    Volt::test('admin.categories.index')
        ->assertSeeHtml('system-confirm')
        ->assertSee('Delete category?', false);
});

test('portal pages render the shared system modal', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('admin.categories.index'))
        ->assertOk()
        ->assertSee('system-message.window', false);
});
