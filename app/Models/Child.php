<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Child extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
    ];

    public function memories(): BelongsToMany
    {
        return $this->belongsToMany(Memory::class)->withTimestamps();
    }

    public static function findOrCreateByName(string $name): self
    {
        return static::firstOrCreate(['name' => trim($name)]);
    }
}
