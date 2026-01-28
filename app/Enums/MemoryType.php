<?php

namespace App\Enums;

enum MemoryType: string
{
    case PHOTO = 'photo';
    case VIDEO = 'video';
    case AUDIO = 'audio';
    case WEBCLIP = 'webclip';
    case TEXT = 'text';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function randomValue(): string
    {
        $values = self::values();

        return $values[array_rand($values)];
    }
}
