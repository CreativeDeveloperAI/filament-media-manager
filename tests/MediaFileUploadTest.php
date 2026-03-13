<?php

namespace Slimani\MediaManager\Tests;

use Slimani\MediaManager\Tests\Models\User;
use Livewire\Livewire;
use Slimani\MediaManager\Tests\Components\TestMediaFileUploadForm;

uses(TestCase::class);

it('can render media file upload field', function () {
    $user = User::create(['name' => 'Test User']);
    Livewire::actingAs($user)
        ->test(TestMediaFileUploadForm::class)
        ->assertOk();
});

it('has browse action', function () {
    $user = User::create(['name' => 'Test User']);
    Livewire::actingAs($user)
        ->test(TestMediaFileUploadForm::class)
        ->assertSee('Browse Media');
});
