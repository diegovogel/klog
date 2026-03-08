<?php

namespace Database\Seeders;

use App\Models\Media;
use App\Models\Memory;
use App\Models\WebClipping;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class MediaSeeder extends Seeder
{
    public function run(): void
    {
        $this->ensureSampleImagesExist();
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

    /**
     * Generate placeholder sample images if they don't exist on disk.
     *
     * The MediaFactory references these filenames, but only video/audio
     * sample files are committed to the repo. This creates simple
     * placeholder JPEGs so seeded image records resolve correctly.
     */
    private function ensureSampleImagesExist(): void
    {
        $disk = Storage::disk('local');

        $samples = [
            'sample-image-landscape.jpg' => [800, 600],
            'sample-image-portrait.jpg' => [600, 800],
        ];

        foreach ($samples as $filename => [$width, $height]) {
            if ($disk->exists($filename)) {
                continue;
            }

            $image = imagecreatetruecolor($width, $height);
            $bg = imagecolorallocate($image, 220, 200, 180);
            imagefill($image, 0, 0, $bg);

            $terracotta = imagecolorallocate($image, 180, 90, 53);
            $tan = imagecolorallocate($image, 200, 160, 130);
            $sage = imagecolorallocate($image, 160, 180, 160);

            $pad = 50;
            $midX = intdiv($width, 2) + intdiv($pad, 2);
            $midY = intdiv($height, 2);

            imagefilledrectangle($image, $pad, $pad, $midX - 10, $midY - 10, $terracotta);
            imagefilledrectangle($image, $midX + 10, $pad, $width - $pad, $midY - 10, $tan);
            imagefilledrectangle($image, $pad, $midY + 10, $width - $pad, $height - $pad, $sage);

            $tmpPath = tempnam(sys_get_temp_dir(), 'klog_sample_');
            imagejpeg($image, $tmpPath, 85);
            imagedestroy($image);

            $disk->put($filename, file_get_contents($tmpPath));
            unlink($tmpPath);
        }
    }
}
