<?php

namespace Database\Factories;

use App\Models\Memory;
use App\Models\WebClipping;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class WebClippingFactory extends Factory
{
    protected $model = WebClipping::class;

    public function definition(): array
    {
        return [
            'url' => $this->faker->url(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'memory_id' => Memory::factory(),
        ];
    }

    public function withContent(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => $this->faker->sentence(),
            'content' => '<p>'.$this->faker->paragraph().'</p>',
        ]);
    }
}
