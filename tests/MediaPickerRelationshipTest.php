<?php

namespace Slimani\MediaManager\Tests;

use App\Models\User;
use Livewire\Livewire;
use Slimani\MediaManager\Models\File;
use Slimani\MediaManager\Tests\Components\TestMediaPickerRelationshipForm;

class MediaPickerRelationshipTest extends TestCase
{
    public function test_it_hydrates_id_from_integer_id()
    {
        $file = File::factory()->create();

        $user = User::factory()->create(['avatar_id' => $file->id]);

        Livewire::actingAs($user)
            ->test(TestMediaPickerRelationshipForm::class, ['user' => $user])
            ->assertSet('data.avatar_id', fn ($state) => (int) $state === (int) $file->id);
    }

    public function test_it_dehydrates_integer_id()
    {
        $file = File::factory()->create();

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TestMediaPickerRelationshipForm::class, ['user' => $user])
            ->fillForm(['avatar_id' => $file->id])
            ->call('submit');

        $this->assertEquals($file->id, $user->fresh()->avatar_id);
    }

    public function test_it_handles_multiple_relationship_mapping()
    {
        $file1 = File::factory()->create();
        $file2 = File::factory()->create();

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TestMediaPickerRelationshipForm::class, ['user' => $user])
            ->fillForm(['documents' => [$file1->id, $file2->id]])
            ->call('submit');

        $this->assertCount(2, $user->fresh()->documents);
        $this->assertContains($file1->id, $user->fresh()->documents->pluck('id'));
        $this->assertContains($file2->id, $user->fresh()->documents->pluck('id'));
    }

    protected function getTestJpg()
    {
        $path = tempnam(sys_get_temp_dir(), 'test').'.jpg';
        $image = imagecreatetruecolor(10, 10);
        imagejpeg($image, $path);
        imagedestroy($image);

        return $path;
    }
}
