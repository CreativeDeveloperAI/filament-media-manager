<?php

namespace Slimani\MediaManager\Tests\Components;

use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Slimani\MediaManager\Infolists\Components\MediaImageEntry;
use Slimani\MediaManager\Models\File;
use Slimani\MediaManager\Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Storage::fake('public');
});

it('can render an image file', function () {
    $file = File::create([
        'name' => 'test-image',
        'mime_type' => 'image/jpeg',
        'size' => 1024,
        'extension' => 'jpg',
    ]);
    $file->addMediaFromString('fake')->toMediaCollection('default');

    $dummyLivewire = new class extends Component implements HasSchemas
    {
        use InteractsWithSchemas;
    };
    $component = MediaImageEntry::make('file')
        ->container(Schema::make($dummyLivewire)->record($file))
        ->state($file->id)
        ->circular();

    $html = Blade::render($component->toHtml());

    expect($html)
        ->toContain('fi-in-image')
        ->toContain('fi-circular')
        ->toContain('<img');
});

it('can use custom conversion', function () {
    $file = File::create([
        'name' => 'test-image',
        'mime_type' => 'image/jpeg',
        'size' => 1024,
        'extension' => 'jpg',
    ]);
    $file->addMediaFromString('fake')->toMediaCollection('default');

    $dummyLivewire = new class extends Component implements HasSchemas
    {
        use InteractsWithSchemas;
    };
    $component = MediaImageEntry::make('file')
        ->container(Schema::make($dummyLivewire)->record($file))
        ->state($file->id)
        ->conversion('preview');

    expect($component->getConversion())->toBe('preview');
});

it('can handle File model in state', function () {
    $file = File::create([
        'name' => 'model-file.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 1024,
        'extension' => 'jpg',
    ]);
    $file->addMediaFromString('fake')->toMediaCollection('default');

    $dummyLivewire = new class extends Component implements HasSchemas
    {
        use InteractsWithSchemas;
    };
    $component = MediaImageEntry::make('file')
        ->container(Schema::make($dummyLivewire)->record($file))
        ->state($file);

    $html = Blade::render($component->toHtml());

    expect($html)->toContain('fi-in-image');
});

it('can handle Collection in state by taking the first item', function () {
    $file1 = File::create([
        'name' => 'file1.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 1024,
        'extension' => 'jpg',
    ]);
    $file1->addMediaFromString('fake')->toMediaCollection('default');

    $file2 = File::create([
        'name' => 'file2.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 1024,
        'extension' => 'jpg',
    ]);
    $file2->addMediaFromString('fake')->toMediaCollection('default');

    $dummyLivewire = new class extends Component implements HasSchemas
    {
        use InteractsWithSchemas;
    };
    $component = MediaImageEntry::make('files')
        ->container(Schema::make($dummyLivewire)->record($file1))
        ->state(collect([$file1, $file2]));

    $html = Blade::render($component->toHtml());

    expect($html)->toContain('fi-in-image');
});
