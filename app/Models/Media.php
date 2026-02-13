<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Media extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'mediable_id',
        'mediable_type',
        'filename',
        'original_filename',
        'mime_type',
        'captured_at',
        'size',
        'disk',
        'path',
        'type',
        'metadata',
        'order',
    ];

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn () => route('media.show', $this->filename),
        );
    }
}
