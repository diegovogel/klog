<?php

namespace Database\Factories;

use App\Enums\MemoryType;
use App\Models\Memory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class MemoryFactory extends Factory
{
    protected $model = Memory::class;

    public function definition(): array
    {
        return [
            'title' => Str::title($this->faker->words(rand(1, 5), true)),
            'content' => $this->faker->paragraph(rand(0, 4)),
            'type' => MemoryType::randomValue(),
            'captured_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
