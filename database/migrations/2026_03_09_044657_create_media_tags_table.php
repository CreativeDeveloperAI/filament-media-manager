<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_tags', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string('name');
            $blueprint->string('slug')->unique();
            $blueprint->timestamps();
        });

        Schema::create('media_taggables', function (Blueprint $blueprint) {
            $blueprint->foreignId('tag_id')->constrained('media_tags')->cascadeOnDelete();
            $blueprint->morphs('taggable');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_taggables');
        Schema::dropIfExists('media_tags');
    }
};
