<?php

namespace App\Models;

use App\Enums\MediaType;
use App\Enums\MemoryType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Memory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'content',
        'captured_at',
    ];

    /**
     * Derive memory types from content and relationships.
     *
     * @return array<string>
     */
    protected function types(): Attribute
    {
        return Attribute::make(
            get: function (): array {
                $types = [];

                // Check for text content
                if (! empty($this->content)) {
                    $types[] = MemoryType::TEXT->value;
                }

                // Check for web clippings
                if ($this->webClippings()->exists()) {
                    $types[] = MemoryType::WEBCLIP->value;
                }

                // Check for media types
                $mediaTypes = $this->media()->pluck('type')->unique();
                foreach ($mediaTypes as $mediaType) {
                    $memoryType = match ($mediaType) {
                        MediaType::IMAGE->value, MediaType::IMAGE => MemoryType::PHOTO->value,
                        MediaType::VIDEO->value, MediaType::VIDEO => MemoryType::VIDEO->value,
                        MediaType::AUDIO->value, MediaType::AUDIO => MemoryType::AUDIO->value,
                        default => null,
                    };

                    if ($memoryType !== null) {
                        $types[] = $memoryType;
                    }
                }

                return $types;
            }
        );
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function getMedia(): Collection
    {
        return $this->media()->get();
    }

    public function webClippings(): HasMany
    {
        return $this->hasMany(WebClipping::class);
    }

    public function getWebClippings(): Collection
    {
        return $this->webClippings()->get();
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

    public function attachTagNames(array $tagNames): void
    {
        $tagIds = collect($tagNames)
            ->map(fn ($name) => trim($name))
            ->filter()
            ->unique()
            ->map(fn ($name) => Tag::findOrCreateByName($name)->id);

        $this->tags()->syncWithoutDetaching($tagIds);
    }
}
