<?php

namespace App\Enums;

enum MemoryType: string
{
    case PHOTO = 'photo';
    case VIDEO = 'video';
    case AUDIO = 'audio';
    case WEBCLIP = 'webclip';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
