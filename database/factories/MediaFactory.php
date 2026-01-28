<?php

namespace Database\Factories;

use App\Enums\MediaType;
use App\Enums\MimeType;
use App\Models\Media;
use App\Models\Memory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        return [
            'filename' => $this->faker->uuid(),
            'original_filename' => Str::slug($this->faker->words(3, true)),
            'mime_type' => $this->faker->randomElement(MimeType::values()),
            'size' => $this->faker->randomNumber(7, true),
            'disk' => 'local',
            'path' => $this->faker->word(),
            'type' => $this->faker->randomElement(MediaType::values()),
            'metadata' => null,
            'order' => 0,
            'mediable_id' => Memory::factory(),
            'mediable_type' => (new Memory)->getMorphClass(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }

    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MediaType::IMAGE->value,
            'mime_type' => MimeType::JPEG->value,
        ]);
    }

    public function video(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MediaType::VIDEO->value,
            'mime_type' => MimeType::MP4->value,
        ]);
    }

    public function audio(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MediaType::AUDIO->value,
            'mime_type' => MimeType::MPEG->value,
        ]);
    }
}
