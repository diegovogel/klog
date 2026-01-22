<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WebClipping extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'memory_id',
        'url',
    ];

    public function memory(): BelongsTo
    {
        return $this->belongsTo(Memory::class);
    }

    public function screenshot(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }
}
