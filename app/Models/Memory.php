<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function media(): Memory|HasMany
    {
        return $this->hasMany(Media::class);
    }

    public function getMedia(): Collection
    {
        return $this->media()->get();
    }
}
