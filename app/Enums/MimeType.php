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
    case M4A = 'audio/m4a';
    case MP4_AUDIO = 'audio/mp4';
    case WEBM_AUDIO = 'audio/webm';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @throws Exception
     */
    public static function mediaTypeFromMime(string $mimeType): string
    {
        return match ($mimeType) {
            MimeType::JPEG->value, MimeType::PNG->value, MimeType::GIF->value, MimeType::WEBP->value, MimeType::HEIC->value, MimeType::HEIF->value, MimeType::AVIF->value => MediaType::IMAGE->value,
            MimeType::MOV->value, MimeType::MP4->value, MimeType::WEBM_VIDEO->value => MediaType::VIDEO->value,
            MimeType::MPEG->value, MimeType::WAV->value, MimeType::M4A->value, MimeType::MP4_AUDIO->value, MimeType::WEBM_AUDIO->value => MediaType::AUDIO->value,
            default => throw new Exception('Unexpected mime type: '.$mimeType),
        };
    }
}
