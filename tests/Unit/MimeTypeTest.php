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
});
