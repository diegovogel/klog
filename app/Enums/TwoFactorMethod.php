<?php

namespace App\Enums;

enum TwoFactorMethod: string
{
    case EMAIL = 'email';
    case AUTHENTICATOR = 'authenticator';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
