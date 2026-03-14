<?php

namespace Database\Factories;

use App\Enums\MediaType;
use App\Enums\MimeType;
use App\Enums\ProcessingStatus;
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

        $dateTime = $this->faker->dateTimeBetween('-5 year', 'now');

        return [
            'filename' => $this->faker->uuid(),
            'original_filename' => $fileName,
            'mime_type' => $mimeType,
            'captured_at' => $dateTime,
            'size' => $this->faker->randomNumber(7, true),
            'disk' => 'local',
            'path' => $fileName,
            'type' => $type,
            'metadata' => null,
            'order' => 0,
            'mediable_id' => Memory::factory(),
            'mediable_type' => (new Memory)->getMorphClass(),
            'created_at' => $dateTime->getTimestamp(),
            'updated_at' => Carbon::now(),
        ];
    }

    public function image(): static
    {
        $fileName = $this->pickFilename(MediaType::IMAGE->value);

        return $this->state(fn (array $attributes) => [
            'type' => MediaType::IMAGE->value,
            'mime_type' => MimeType::JPEG->value,
            'original_filename' => $fileName,
            'path' => $fileName,
        ]);
    }

    public function video(): static
    {
        $fileName = $this->pickFilename(MediaType::VIDEO->value);

        return $this->state(fn (array $attributes) => [
            'type' => MediaType::VIDEO->value,
            'mime_type' => MimeType::MOV->value,
            'original_filename' => $fileName,
            'path' => $fileName,
        ]);
    }

    public function audio(): static
    {
        $fileName = $this->pickFilename(MediaType::AUDIO->value);

        return $this->state(fn (array $attributes) => [
            'type' => MediaType::AUDIO->value,
            'mime_type' => MimeType::MP4_AUDIO->value,
            'original_filename' => $fileName,
            'path' => $fileName,
        ]);
    }

    public function heic(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MediaType::IMAGE->value,
            'mime_type' => MimeType::HEIC->value,
            'original_filename' => 'photo.heic',
            'path' => 'uploads/2026/03/test.heic',
            'processing_status' => ProcessingStatus::Pending,
        ]);
    }

    public function mov(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MediaType::VIDEO->value,
            'mime_type' => MimeType::MOV->value,
            'original_filename' => 'video.MOV',
            'path' => 'uploads/2026/03/test.mov',
            'processing_status' => ProcessingStatus::Pending,
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'processing_status' => ProcessingStatus::Processing,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'processing_status' => ProcessingStatus::Failed,
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
