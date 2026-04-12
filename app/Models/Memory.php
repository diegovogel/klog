<?php

namespace App\Models;

use App\Enums\MediaType;
use App\Enums\MemoryType;
use App\Observers\MemoryObserver;
use App\Services\SearchIndexer;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

#[ObservedBy([MemoryObserver::class])]
class Memory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'content',
        'memory_date',
    ];

    protected $casts = [
        'memory_date' => 'datetime',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
        return $this->belongsToMany(Tag::class)->withTimestamps();
    }

    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Child::class)->withTimestamps();
    }

    // Sync tags from array of names
    public function syncTagNames(array $tagNames): void
    {
        $tags = collect($tagNames)->map(function ($name) {
            return Tag::findOrCreateByName(trim($name));
        });

        $this->tags()->sync($tags->pluck('id'));
        $this->reindexSearch();
    }

    public function attachTagNames(array $tagNames): void
    {
        $tagIds = collect($tagNames)
            ->map(fn ($name) => trim($name))
            ->filter()
            ->unique()
            ->map(fn ($name) => Tag::findOrCreateByName($name)->id);

        $this->tags()->syncWithoutDetaching($tagIds);
        $this->reindexSearch();
    }

    /**
     * Refresh this memory's entry in the FTS5 search index.
     *
     * Call after any mutation that changes searchable fields but doesn't
     * fire a model event on Memory — e.g. attaching tags via the pivot,
     * or adding a web clipping through the relationship.
     */
    public function reindexSearch(): void
    {
        app(SearchIndexer::class)->index($this->fresh(['tags', 'webClippings']) ?? $this);
    }

    /**
     * Filter memories whose derived type overlaps any of the given types.
     *
     * OR logic — selecting "photo" and "video" returns memories with photos,
     * videos, or both. An empty array is a no-op.
     *
     * @param  array<int, string>  $types
     */
    public function scopeFilterByTypes(Builder $query, array $types): Builder
    {
        if ($types === []) {
            return $query;
        }

        return $query->where(function (Builder $outer) use ($types): void {
            foreach ($types as $type) {
                $outer->orWhere(function (Builder $inner) use ($type): void {
                    match ($type) {
                        MemoryType::TEXT->value => $inner
                            ->whereNotNull('memories.content')
                            ->where('memories.content', '!=', ''),
                        MemoryType::WEBCLIP->value => $inner->whereHas('webClippings'),
                        MemoryType::PHOTO->value => $inner->whereHas('media', fn (Builder $m) => $m->where('type', MediaType::IMAGE->value)),
                        MemoryType::VIDEO->value => $inner->whereHas('media', fn (Builder $m) => $m->where('type', MediaType::VIDEO->value)),
                        MemoryType::AUDIO->value => $inner->whereHas('media', fn (Builder $m) => $m->where('type', MediaType::AUDIO->value)),
                        default => null,
                    };
                });
            }
        });
    }

    public function scopeFilterByDateRange(Builder $query, ?CarbonInterface $from, ?CarbonInterface $to): Builder
    {
        if ($from !== null) {
            $query->where('memories.memory_date', '>=', $from->copy()->startOfDay());
        }

        if ($to !== null) {
            $query->where('memories.memory_date', '<=', $to->copy()->endOfDay());
        }

        return $query;
    }

    /**
     * @param  array<int, int>  $childIds
     */
    public function scopeFilterByChildren(Builder $query, array $childIds): Builder
    {
        if ($childIds === []) {
            return $query;
        }

        return $query->whereHas(
            'children',
            fn (Builder $q) => $q->whereIn('children.id', $childIds),
        );
    }

    public function scopeFilterByUser(Builder $query, ?int $userId): Builder
    {
        if ($userId === null) {
            return $query;
        }

        return $query->where('memories.user_id', $userId);
    }

    /**
     * Soft-delete this memory and all related media and web clippings.
     */
    public function deleteWithRelations(): void
    {
        $this->media()->delete();

        $this->webClippings->each(function (WebClipping $clipping) {
            $clipping->screenshot()?->delete();
            $clipping->delete();
        });

        $this->delete();
    }

    /**
     * Permanently delete this memory, all related records, and media files from disk.
     */
    public function forceDeleteWithRelations(): void
    {
        $this->media->each(function (Media $media) {
            Storage::disk('local')->delete($media->path);
            $media->forceDelete();
        });

        $this->webClippings->each(function (WebClipping $clipping) {
            if ($clipping->screenshot) {
                Storage::disk('local')->delete($clipping->screenshot->path);
                $clipping->screenshot->forceDelete();
            }
            $clipping->forceDelete();
        });

        $this->forceDelete();
    }
}
