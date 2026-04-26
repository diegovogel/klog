<?php

use App\Enums\MediaType;
use App\Enums\MimeType;

describe('MimeType enum', function () {
    it('maps image MIME types to image media type', function (string $mime) {
        expect(MimeType::mediaTypeFromMime($mime))->toBe(MediaType::IMAGE->value);
    })->with([
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'heic' => 'image/heic',
        'heif' => 'image/heif',
        'avif' => 'image/avif',
    ]);

    it('maps video MIME types to video media type', function (string $mime) {
        expect(MimeType::mediaTypeFromMime($mime))->toBe(MediaType::VIDEO->value);
    })->with([
        'quicktime' => 'video/quicktime',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
    ]);

    it('maps audio MIME types to audio media type', function (string $mime) {
        expect(MimeType::mediaTypeFromMime($mime))->toBe(MediaType::AUDIO->value);
    })->with([
        'mpeg' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'mp4' => 'audio/mp4',
        'webm' => 'audio/webm',
    ]);

    it('throws on unknown MIME type', function () {
        MimeType::mediaTypeFromMime('application/pdf');
    })->throws(Exception::class, 'Unexpected mime type: application/pdf');

    it('includes all enum cases in values()', function () {
        $values = MimeType::values();

        expect($values)
            ->toContain('video/webm')
            ->toContain('audio/webm')
            ->toContain('audio/mp4')
            ->and(count($values))->toBe(count(MimeType::cases()));
    });

    it('exposes a media type for every case', function (MimeType $case) {
        expect($case->mediaType())->toBeInstanceOf(MediaType::class);
    })->with(MimeType::cases());

    it('returns human-friendly labels for each case', function () {
        expect(MimeType::JPEG->label())->toBe('JPG')
            ->and(MimeType::MPEG->label())->toBe('MP3')
            ->and(MimeType::MP4_AUDIO->label())->toBe('M4A')
            ->and(MimeType::WEBM_VIDEO->label())->toBe('WEBM')
            ->and(MimeType::WEBM_AUDIO->label())->toBe('WEBM')
            ->and(MimeType::PNG->label())->toBe('PNG');
    });

    it('groups labels by media type', function () {
        $grouped = MimeType::labelsByMediaType();

        expect(array_keys($grouped))->toEqualCanonicalizing(MediaType::values())
            ->and($grouped[MediaType::IMAGE->value])->toContain('JPG', 'PNG', 'HEIC', 'AVIF')
            ->and($grouped[MediaType::VIDEO->value])->toContain('MP4', 'MOV', 'WEBM')
            ->and($grouped[MediaType::AUDIO->value])->toContain('MP3', 'WAV', 'M4A', 'WEBM');
    });
});
