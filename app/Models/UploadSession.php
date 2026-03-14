<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UploadSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'original_filename',
        'mime_type',
        'total_size',
        'total_chunks',
        'received_chunks',
        'received_chunk_indices',
        'disk',
        'path',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'total_size' => 'integer',
            'total_chunks' => 'integer',
            'received_chunks' => 'integer',
            'received_chunk_indices' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isComplete(): bool
    {
        return $this->received_chunks === $this->total_chunks;
    }

    public function chunksDirectory(): string
    {
        return 'uploads/chunks/'.$this->id.'/';
    }
}
