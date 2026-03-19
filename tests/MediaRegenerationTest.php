<?php

namespace Slimani\MediaManager\Tests;

use Livewire\Livewire;
use Slimani\MediaManager\Livewire\MediaBrowser;
use Slimani\MediaManager\Models\File;
use Slimani\MediaManager\Pages\MediaManager;

uses(TestCase::class);

it('renders media manager page', function () {
    Livewire::test(MediaManager::class)
        ->assertStatus(200);
});

it('media browser dispatches selection sync event', function () {
    $file = File::create(['name' => 'Test File']);

    Livewire::test(MediaBrowser::class)
        ->set('selectedItems', ["file-{$file->id}"])
        ->assertDispatched('media-selection-synced', ids: [$file->id]);
});

it('media manager updates selection when event is received', function () {
    Livewire::test(MediaManager::class)
        ->dispatch('media-selection-synced', ids: [1, 2, 3])
        ->assertSet('selectedFileIds', [1, 2, 3]);
});

it('can call regenerate conversions action on media manager', function () {
    Livewire::test(MediaManager::class)
        ->set('selectedFileIds', [1])
        ->callAction('regenerate_conversions', [
            'conversions' => [],
            'only_missing' => true,
            'force' => false,
            'with_responsive_images' => false,
        ])
        ->assertNotified();
});
