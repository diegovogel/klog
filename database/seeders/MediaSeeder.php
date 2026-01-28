<?php

namespace Database\Seeders;

use App\Models\Media;
use App\Models\Memory;
use App\Models\WebClipping;
use Illuminate\Database\Seeder;

class MediaSeeder extends Seeder
{
    public function run(): void
    {
        $memories = Memory::all();
        $webClippings = WebClipping::all();

        if ($memories->count() > 0) {
            foreach ($memories as $memory) {
                $hasText = $memory->content !== null;

                if ($hasText) {
                    $hasMedia = rand(1, 10) <= 8; // 80% of memories with text have media.
                } else {
                    $hasMedia = true;
                }

                if (! $hasMedia) {
                    continue;
                }

                switch (rand(1, 4)) {
                    case 1: // Photos
                        Media::factory()
                            ->for($memory, 'mediable')
                            ->count(rand(1, 10))
                            ->image()
                            ->create();
                        break;
                    case 2: // Video
                        Media::factory()
                            ->for($memory, 'mediable')
                            ->count(rand(1, 3))
                            ->video()
                            ->create();
                        break;
                    case 3: // Audio
                        Media::factory()
                            ->for($memory, 'mediable')
                            ->count(rand(1, 2))
                            ->audio()
                            ->create();
                        break;
                    default: // All media types
                        Media::factory()
                            ->for($memory, 'mediable')
                            ->count(rand(1, 5))
                            ->create();
                }
            }
        }

        if ($webClippings->count() > 0) {
            foreach ($webClippings as $clipping) {
                Media::factory()
                    ->for($clipping, 'mediable')
                    ->image()
                    ->create();
            }
        }

        if (Media::count() === 0) {
            Media::factory()->count(100)->create();
        }
    }
}
