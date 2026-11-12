<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Livewire\Volt\Volt;

it('says the file is too large instead of quoting kilobytes', function () {
    $validator = Validator::make(
        ['photo' => UploadedFile::fake()->create('big.jpg', 3000, 'image/jpeg')],
        ['photo' => ['image', 'max:2048']],
    );

    expect($validator->errors()->first('photo'))->toBe('File size too large. Maximum is 2 MB.');
});

it('still uses the normal wording for text that is too long', function () {
    $validator = Validator::make(['name' => str_repeat('a', 300)], ['name' => ['max:255']]);

    expect($validator->errors()->first('name'))->toBe('The name field must not be greater than 255 characters.');
});

it('shows the short message when a profile picture is too big', function () {
    Storage::fake('public');

    Volt::actingAs(User::factory()->create(['role' => 'umpire']))->test('staff.profile-modal')
        ->call('startEditing')
        ->set('photo', UploadedFile::fake()->create('big.jpg', 3000, 'image/jpeg'))
        ->call('save')
        ->assertHasErrors('photo')
        ->assertSee('File size too large. Maximum is 2 MB.')
        ->assertDontSee('kilobytes');
});

it('states the limit of each field', function () {
    $limits = ['max:2048' => '2 MB', 'max:4096' => '4 MB', 'max:5120' => '5 MB', 'max:1536' => '1.5 MB', 'max:500' => '500 KB'];

    foreach ($limits as $rule => $shown) {
        $validator = Validator::make(
            ['file' => UploadedFile::fake()->create('big.jpg', 6000, 'image/jpeg')],
            ['file' => ['image', $rule]],
        );

        expect($validator->errors()->first('file'))->toBe("File size too large. Maximum is {$shown}.");
    }
});
