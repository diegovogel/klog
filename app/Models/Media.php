<?php

namespace App\Models;

use App\Enums\ProcessingStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Media extends Model
{
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'processing_status' => 'complete',
    ];

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
        'processing_status',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'processing_status' => ProcessingStatus::class,
        ];
    }

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isProcessing(): bool
    {
        return in_array($this->processing_status, [ProcessingStatus::Pending, ProcessingStatus::Processing]);
    }

    public function isOptimizationFailed(): bool
    {
        return $this->processing_status === ProcessingStatus::Failed;
    }

    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn () => route('media.show', $this->filename),
        );
    }
}
