<?php

namespace Database\Factories;

use App\Enums\MediaType;
use App\Enums\MimeType;
use App\Models\Media;
use App\Models\Memory;
use Exception;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        $mimeType = $this->faker->randomElement(MimeType::values());

        $type = MimeType::mediaTypeFromMime($mimeType);

        $fileName = $this->pickFilename($type);

        return [
            'filename' => $this->faker->uuid(),
            'original_filename' => $fileName,
            'mime_type' => $mimeType,
            'size' => $this->faker->randomNumber(7, true),
            'disk' => 'public',
            'path' => $fileName,
            'type' => $type,
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
        $type = MediaType::IMAGE->value;
        $fileName = $this->pickFilename($type);

        return $this->state(fn (array $attributes) => [
            'type' => $type,
            'mime_type' => MimeType::JPEG->value,
            'filename' => $fileName,
            'path' => $fileName,
        ]);
    }

    public function video(): static
    {
        $type = MediaType::VIDEO->value;
        $fileName = $this->pickFilename($type);

        return $this->state(fn (array $attributes) => [
            'type' => $type,
            'mime_type' => MimeType::MOV->value,
            'filename' => $fileName,
            'path' => $fileName,
        ]);
    }

    public function audio(): static
    {
        $type = MediaType::AUDIO->value;
        $fileName = $this->pickFilename($type);

        return $this->state(fn (array $attributes) => [
            'type' => $type,
            'mime_type' => MimeType::M4A->value,
            'filename' => $fileName,
            'path' => $fileName,
        ]);
    }

    protected function pickFilename(string $type): string
    {
        return match ($type) {
            MediaType::IMAGE->value => $this->faker->randomElement([
                'sample-image-portrait.jpg',
                'sample-image-landscape.jpg',
            ]),
            MediaType::VIDEO->value => $this->faker->randomElement([
                'sample-video-portrait.MOV',
                'sample-video-landscape.MOV',
            ]),
            MediaType::AUDIO->value => 'sample-audio.m4a',
            default => throw new Exception('Unexpected media type: '.$type),
        };
    }
}
