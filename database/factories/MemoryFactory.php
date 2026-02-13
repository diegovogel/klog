<?php

namespace Database\Factories;

use App\Models\Memory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class MemoryFactory extends Factory
{
    protected $model = Memory::class;

    public function definition(): array
    {
        $date = $this->faker->dateTimeBetween('-5 years', 'now');

        return [
            'title' => Str::title($this->faker->words(rand(1, 5), true)),
            'content' => $this->faker->paragraph(rand(0, 4)),
            'memory_date' => $date,
            'created_at' => $date->getTimestamp(),
            'updated_at' => Carbon::now(),
        ];
    }
}
