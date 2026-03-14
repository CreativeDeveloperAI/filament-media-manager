<?php

namespace Slimani\MediaManager\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Slimani\MediaManager\Models\File;
use Slimani\MediaManager\Models\MediaAttachment;

trait InteractsWithMediaFiles
{
    /**
     * Get all of the model's media attachments.
     */
    public function mediaAttachments(): MorphMany
    {
        return $this->morphMany(MediaAttachment::class, 'attachable');
    }

    /**
     * Get all of the model's media files.
     */
    public function mediaFiles(?string $collection = null): MorphToMany
    {
        $relation = $this->morphToMany(File::class, 'attachable', 'media_attachments', 'attachable_id', 'media_file_id')
            ->withPivot('collection', 'sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');

        if ($collection) {
            $relation->wherePivot('collection', $collection);
        }

        return $relation;
    }

    /**
     * Define a single media relation via a foreign key.
     */
    public function mediaFile(string $column): BelongsTo
    {
        return $this->belongsTo(File::class, $column);
    }
}
