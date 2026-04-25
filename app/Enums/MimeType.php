<?php

namespace App\Enums;

use Exception;

enum MimeType: string
{
    // Image
    case JPEG = 'image/jpeg';
    case PNG = 'image/png';
    case GIF = 'image/gif';
    case WEBP = 'image/webp';
    case HEIC = 'image/heic';
    case HEIF = 'image/heif';
    case AVIF = 'image/avif';

    // Video
    case MOV = 'video/quicktime';
    case MP4 = 'video/mp4';
    case WEBM_VIDEO = 'video/webm';

    // Audio
    case MPEG = 'audio/mpeg';
    case WAV = 'audio/wav';
    case MP4_AUDIO = 'audio/mp4';
    case WEBM_AUDIO = 'audio/webm';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function mediaType(): MediaType
    {
        return match ($this) {
            self::JPEG, self::PNG, self::GIF, self::WEBP, self::HEIC, self::HEIF, self::AVIF => MediaType::IMAGE,
            self::MOV, self::MP4, self::WEBM_VIDEO => MediaType::VIDEO,
            self::MPEG, self::WAV, self::MP4_AUDIO, self::WEBM_AUDIO => MediaType::AUDIO,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::JPEG => 'JPG',
            self::MPEG => 'MP3',
            self::MP4_AUDIO => 'M4A',
            self::WEBM_VIDEO, self::WEBM_AUDIO => 'WEBM',
            default => $this->name,
        };
    }

    /**
     * @return array<string, list<string>>
     */
    public static function labelsByMediaType(): array
    {
        $grouped = array_fill_keys(MediaType::values(), []);

        foreach (self::cases() as $case) {
            $grouped[$case->mediaType()->value][] = $case->label();
        }

        return $grouped;
    }

    /**
     * @throws Exception
     */
    public static function mediaTypeFromMime(string $mimeType): string
    {
        $case = self::tryFrom($mimeType);

        if ($case === null) {
            throw new Exception('Unexpected mime type: '.$mimeType);
        }

        return $case->mediaType()->value;
    }
}
