<?php

namespace Slimani\MediaManager\Tables\Columns;

use Filament\Tables\Columns\Column;
use Slimani\MediaManager\Models\File;

class MediaColumn extends Column
{
    protected string $view = 'media-manager::filament.tables.columns.media-column';

    public function getMediaUrl(): ?string
    {
        $state = $this->getState();
        if (! $state) {
            return null;
        }

        // Assuming state is ID or ID array. simpler for single ID now.
        $file = File::find($state);

        return $file?->getFirstMediaUrl('default', 'thumb');
    }
}
