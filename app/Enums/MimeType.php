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

    /**
     * Non-canonical MIME types that browsers or PHP's finfo report for supported formats.
     * macOS reports .m4a files as audio/x-m4a, and finfo reports WAV as audio/x-wav.
     */
    private const ALIASES = [
        'audio/x-m4a' => 'audio/mp4',
        'audio/m4a' => 'audio/mp4',
        'audio/x-wav' => 'audio/wav',
        'audio/wave' => 'audio/wav',
        'audio/vnd.wave' => 'audio/wav',
        'audio/mp3' => 'audio/mpeg',
        'image/jpg' => 'image/jpeg',
    ];

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Canonical values plus known aliases, for validating MIME types detected from file contents.
     *
     * @return list<string>
     */
    public static function acceptedValues(): array
    {
        return array_merge(self::values(), array_keys(self::ALIASES));
    }

    /**
     * Strip codec parameters and map known aliases to their canonical MIME type.
     */
    public static function normalize(string $mimeType): string
    {
        $base = strtolower(trim(explode(';', $mimeType)[0]));

        return self::ALIASES[$base] ?? $base;
    }

    public static function fromExtension(string $extension): ?self
    {
        $extension = strtolower(ltrim($extension, '.'));

        foreach (self::cases() as $case) {
            if (in_array($extension, $case->extensions(), true)) {
                return $case;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function extensions(): array
    {
        return match ($this) {
            self::JPEG => ['jpg', 'jpeg'],
            self::PNG => ['png'],
            self::GIF => ['gif'],
            self::WEBP => ['webp'],
            self::HEIC => ['heic'],
            self::HEIF => ['heif'],
            self::AVIF => ['avif'],
            self::MOV => ['mov'],
            self::MP4 => ['mp4'],
            self::WEBM_VIDEO => ['webm'],
            self::MPEG => ['mp3'],
            self::WAV => ['wav'],
            self::MP4_AUDIO => ['m4a'],
            self::WEBM_AUDIO => [],
        };
    }

    /**
     * Value for a file input's accept attribute. iOS Safari doesn't honor audio/* in the
     * file picker, so explicit extensions are listed to keep audio files selectable.
     */
    public static function acceptAttribute(): string
    {
        $extensions = array_unique(array_merge(...array_map(
            fn (self $case): array => $case->extensions(),
            self::cases(),
        )));

        return implode(',', array_merge(
            ['image/*', 'video/*', 'audio/*'],
            array_map(fn (string $extension): string => '.'.$extension, $extensions),
        ));
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
