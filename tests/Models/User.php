<?php

namespace Slimani\MediaManager\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Slimani\MediaManager\Models\File;

class User extends Authenticatable
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'users';

    public function avatar()
    {
        return $this->belongsTo(File::class, 'avatar_id');
    }

    public function documents()
    {
        return $this->belongsToMany(File::class, 'user_documents', 'user_id', 'file_id');
    }
}
