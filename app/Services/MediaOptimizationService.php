<?php

namespace App\Services;

use App\Enums\MimeType;
use App\Enums\ProcessingStatus;
use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Imagick;
use Symfony\Component\Process\Process;

class MediaOptimizationService
{
    /** @var list<string> */
    private const IMAGE_CONVERSION_TYPES = [
        'image/heic',
        'image/heif',
        'image/avif',
    ];

    /** @var list<string> */
    private const VIDEO_OPTIMIZATION_TYPES = [
        'video/quicktime',
        'video/webm',
    ];

    public function needsOptimization(Media $media): bool
    {
        return $this->needsImageConversion($media) || $this->needsVideoOptimization($media);
    }

    public function needsImageConversion(Media $media): bool
    {
        return in_array($media->mime_type, self::IMAGE_CONVERSION_TYPES);
    }

    public function needsVideoOptimization(Media $media): bool
    {
        return in_array($media->mime_type, self::VIDEO_OPTIMIZATION_TYPES);
    }

    public function convertImage(Media $media): void
    {
        $disk = Storage::disk($media->disk);
        $absolutePath = $disk->path($media->path);

        $maxDimension = config('klog.media_optimization.image_max_dimension', 2048);
        $quality = config('klog.media_optimization.image_quality', 85);

        $imagick = new Imagick($absolutePath);
        $imagick->autoOrient();

        $width = $imagick->getImageWidth();
        $height = $imagick->getImageHeight();

        if ($width > $maxDimension || $height > $maxDimension) {
            $imagick->scaleImage($maxDimension, $maxDimension, true);
        }

        $imagick->setImageFormat('jpeg');
        $imagick->setImageCompressionQuality($quality);
        $imagick->stripImage();

        $directory = dirname($media->path);
        $newPath = $directory.'/'.$media->filename.'.jpg';
        $newAbsolutePath = $disk->path($newPath);

        $imagick->writeImage($newAbsolutePath);
        $imagick->destroy();

        if ($media->path !== $newPath) {
            $disk->delete($media->path);
        }

        $media->update([
            'path' => $newPath,
            'mime_type' => MimeType::JPEG->value,
            'size' => filesize($newAbsolutePath),
            'processing_status' => ProcessingStatus::Complete,
        ]);
    }

    public function optimizeVideo(Media $media): void
    {
        $disk = Storage::disk($media->disk);
        $inputPath = $disk->path($media->path);

        $probeData = $this->probeVideo($inputPath);
        $width = $probeData['width'];
        $height = $probeData['height'];
        $duration = $probeData['duration'];

        $maxDimension = config('klog.media_optimization.video_max_dimension', 2048);
        $scaleFilter = $this->buildScaleFilter($width, $height, $maxDimension);

        $directory = dirname($media->path);
        $newPath = $directory.'/'.$media->filename.'.mp4';
        $outputPath = $disk->path($newPath);

        $crf = config('klog.media_optimization.video_crf', 23);
        $ffmpeg = config('klog.media_optimization.ffmpeg_path', 'ffmpeg');

        $command = [
            $ffmpeg, '-i', $inputPath,
            '-c:v', 'libx264', '-crf', (string) $crf, '-preset', 'medium',
            '-c:a', 'aac', '-b:a', '128k',
            '-movflags', '+faststart',
        ];

        if ($scaleFilter) {
            $command = array_merge($command, ['-vf', $scaleFilter]);
        }

        $command[] = '-y';
        $command[] = $outputPath;

        $process = new Process($command);
        $process->setTimeout(600);
        $process->mustRun();

        $outputProbe = $this->probeVideo($outputPath);

        if ($media->path !== $newPath) {
            $disk->delete($media->path);
        }

        $media->update([
            'path' => $newPath,
            'mime_type' => MimeType::MP4->value,
            'size' => filesize($outputPath),
            'processing_status' => ProcessingStatus::Complete,
            'metadata' => [
                'width' => $outputProbe['width'],
                'height' => $outputProbe['height'],
                'duration' => $outputProbe['duration'] ?? $duration,
            ],
        ]);
    }

    /**
     * @return array{width: int, height: int, duration: float|null}
     */
    private function probeVideo(string $path): array
    {
        $ffprobe = config('klog.media_optimization.ffprobe_path', 'ffprobe');

        $process = new Process([
            $ffprobe, '-v', 'quiet', '-print_format', 'json',
            '-show_streams', '-select_streams', 'v:0', $path,
        ]);
        $process->mustRun();

        $data = json_decode($process->getOutput(), true);
        $stream = $data['streams'][0] ?? [];

        return [
            'width' => (int) ($stream['width'] ?? 0),
            'height' => (int) ($stream['height'] ?? 0),
            'duration' => isset($stream['duration']) ? (float) $stream['duration'] : null,
        ];
    }

    private function buildScaleFilter(int $width, int $height, int $maxDimension): ?string
    {
        if ($width <= $maxDimension && $height <= $maxDimension) {
            return null;
        }

        if ($width >= $height) {
            return "scale={$maxDimension}:-2";
        }

        return "scale=-2:{$maxDimension}";
    }
}
