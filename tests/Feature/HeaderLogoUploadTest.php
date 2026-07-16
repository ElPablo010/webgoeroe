<?php

use App\Filament\Pages\HeaderSettings;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function () {
    $user = User::factory()->create();
    $this->actingAs($user);
});

it('accepts a PNG upload through the logo upload action', function () {
    $file = UploadedFile::fake()->image('logo.png', 200, 200);

    Livewire::test(HeaderSettings::class)
        ->mountAction('upload_logo')
        ->setActionData(['upload' => [$file]])
        ->callMountedAction()
        ->assertHasNoActionErrors();
});
