<?php

namespace App\Enums;

enum MimeType: string
{
    case IMAGE_JPEG = 'image/jpeg';
    case VIDEO_MP4 = 'video/mp4';
    case AUDIO_MPEG = 'audio/mpeg';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
