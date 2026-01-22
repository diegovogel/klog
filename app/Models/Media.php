<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Media extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'memory_id',
        'filename',
        'original_filename',
        'mime_type',
        'size',
        'disk',
        'path',
        'type',
        'metadata',
        'order',
    ];

    public function memory(): BelongsTo
    {
        return $this->belongsTo(Memory::class);
    }
}
