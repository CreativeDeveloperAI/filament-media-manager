<?php

namespace Slimani\MediaManager\Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Slimani\MediaManager\Livewire\MediaBrowser;
use Slimani\MediaManager\Models\File;

class S3IntegrationTest extends TestCase
{
    public function test_it_can_upload_file_to_real_s3()
    {
        $diskName = filament('media-manager')->getDisk();

        // Force Livewire to use the same disk for temporary uploads
        config()->set('livewire.temporary_file_upload.disk', $diskName);

        $disk = Storage::disk($diskName);

        $filename = 's3-test-image-'.uniqid().'.jpg';
        $file = UploadedFile::fake()->image($filename);

        // Manually put a file to simulate Livewire's upload if needed
        // but let's see if Livewire test helper handles it with the config change

        echo "Testing upload to disk: $diskName (Livewire temp disk: ".config('livewire.temporary_file_upload.disk').")\n";

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
        $this->assertNotNull($fileModel);

        $media = $fileModel->getFirstMedia('default');
        $this->assertNotNull($media);
        $this->assertEquals($diskName, $media->disk);

        // Verify existence on the actual disk
        $this->assertTrue($disk->exists($media->getPathRelativeToRoot()), "File missing on $diskName at: ".$media->getPathRelativeToRoot());

        echo 'Successfully verified file on S3: '.$media->getPathRelativeToRoot()."\n";

        // Cleanup
        $fileModel->delete(); // This should trigger media deletion if configured
        $this->assertFalse($disk->exists($media->getPathRelativeToRoot()), "File NOT deleted from $diskName after model delete");
    }
}
