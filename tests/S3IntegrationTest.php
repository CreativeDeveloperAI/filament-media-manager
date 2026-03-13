<?php

namespace Slimani\MediaManager\Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Slimani\MediaManager\Livewire\MediaBrowser;
use Slimani\MediaManager\Models\File;

uses(TestCase::class);

it('can upload file to real s3', function () {
    $diskName = filament('media-manager')->getDisk();

    // Force Livewire to use the same disk for temporary uploads
    config()->set('livewire.temporary_file_upload.disk', $diskName);

    $disk = Storage::disk($diskName);

    $filename = 's3-test-image-'.uniqid().'.jpg';
    $file = UploadedFile::fake()->image($filename);

    Livewire::test(MediaBrowser::class)
        ->callAction('upload', [
            'files' => [$file],
            'caption' => 'S3 Integration Test',
        ])
        ->assertDispatched('media-uploaded');

    $this->assertDatabaseHas('media_files', [
        'caption' => 'S3 Integration Test',
    ]);

    $fileModel = File::where('caption', 'S3 Integration Test')->latest()->first();
    expect($fileModel)->not->toBeNull();

    $media = $fileModel->getFirstMedia('default');
    expect($media)->not->toBeNull();
    expect($media->disk)->toBe($diskName);

    // Verify existence on the actual disk
    expect($disk->exists($media->getPathRelativeToRoot()))->toBeTrue("File missing on $diskName at: ".$media->getPathRelativeToRoot());

    // Cleanup
    $fileModel->delete(); // This should trigger media deletion if configured
    expect($disk->exists($media->getPathRelativeToRoot()))->toBeFalse("File NOT deleted from $diskName after model delete");
});
