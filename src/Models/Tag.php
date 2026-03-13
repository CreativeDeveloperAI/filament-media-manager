<?php

namespace Slimani\MediaManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    protected $table = 'media_tags';

    protected $fillable = [
        'name',
        'slug',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tag) {
            if (empty($tag->slug)) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    public function files(): MorphToMany
    {
        return $this->morphedByMany(File::class, 'taggable', 'media_taggables');
    }

    public function folders(): MorphToMany
    {
        return $this->morphedByMany(Folder::class, 'taggable', 'media_taggables');
    }
}
