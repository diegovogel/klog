<?php

namespace App\Enums;

enum MimeType: string
{
    case JPEG = 'image/jpeg';
    case MP4 = 'video/mp4';
    case MPEG = 'audio/mpeg';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
