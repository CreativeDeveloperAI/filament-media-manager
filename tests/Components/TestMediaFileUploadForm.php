<?php

namespace Slimani\MediaManager\Tests\Components;

use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Livewire\Component;

class TestMediaFileUploadForm extends Component implements HasForms
{
    use InteractsWithForms;

    public $data;

    public function mount()
    {
        $this->form->fill();
    }

    public function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema
            ->schema([
                \Slimani\MediaManager\Form\MediaPicker::make('avatar_id'),
            ])
            ->statePath('data');
    }

    public function render()
    {
        return '<div>{{ $this->form }}</div>';
    }
}
