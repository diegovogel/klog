<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Memory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'content',
        'type',
        'captured_at',
    ];

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function getMedia(): Collection
    {
        return $this->media()->get();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    // Sync tags from array of names
    public function syncTagNames(array $tagNames): void
    {
        $tags = collect($tagNames)->map(function ($name) {
            return Tag::findOrCreateByName(trim($name));
        });

        $this->tags()->sync($tags->pluck('id'));
    }
}
