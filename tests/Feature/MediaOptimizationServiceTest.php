<?php

use App\Enums\MimeType;
use App\Models\Media;
use App\Services\MediaOptimizationService;

describe('MediaOptimizationService', function () {
    describe('needsOptimization', function () {
        it('returns true for HEIC images', function () {
            $media = Media::factory()->heic()->make();
            $service = new MediaOptimizationService;

            expect($service->needsOptimization($media))->toBeTrue();
        });

        it('returns true for HEIF images', function () {
            $media = Media::factory()->make(['mime_type' => MimeType::HEIF->value]);
            $service = new MediaOptimizationService;

            expect($service->needsOptimization($media))->toBeTrue();
        });

        it('returns true for AVIF images', function () {
            $media = Media::factory()->make(['mime_type' => MimeType::AVIF->value]);
            $service = new MediaOptimizationService;

            expect($service->needsOptimization($media))->toBeTrue();
        });

        it('returns true for MOV videos', function () {
            $media = Media::factory()->mov()->make();
            $service = new MediaOptimizationService;

            expect($service->needsOptimization($media))->toBeTrue();
        });

        it('returns true for WebM videos', function () {
            $media = Media::factory()->make(['mime_type' => MimeType::WEBM_VIDEO->value]);
            $service = new MediaOptimizationService;

            expect($service->needsOptimization($media))->toBeTrue();
        });

        it('returns false for JPEG images', function () {
            $media = Media::factory()->image()->make();
            $service = new MediaOptimizationService;

            expect($service->needsOptimization($media))->toBeFalse();
        });

        it('returns false for PNG images', function () {
            $media = Media::factory()->make(['mime_type' => MimeType::PNG->value]);
            $service = new MediaOptimizationService;

            expect($service->needsOptimization($media))->toBeFalse();
        });

        it('returns false for MP4 videos', function () {
            $media = Media::factory()->make(['mime_type' => MimeType::MP4->value]);
            $service = new MediaOptimizationService;

            expect($service->needsOptimization($media))->toBeFalse();
        });

        it('returns false for audio files', function () {
            $media = Media::factory()->audio()->make();
            $service = new MediaOptimizationService;

            expect($service->needsOptimization($media))->toBeFalse();
        });

        it('returns false for GIF images', function () {
            $media = Media::factory()->make(['mime_type' => MimeType::GIF->value]);
            $service = new MediaOptimizationService;

            expect($service->needsOptimization($media))->toBeFalse();
        });

        it('returns false for WebP images', function () {
            $media = Media::factory()->make(['mime_type' => MimeType::WEBP->value]);
            $service = new MediaOptimizationService;

            expect($service->needsOptimization($media))->toBeFalse();
        });
    });

    describe('needsImageConversion', function () {
        it('returns true only for HEIC, HEIF, and AVIF', function () {
            $service = new MediaOptimizationService;

            $convertible = [MimeType::HEIC, MimeType::HEIF, MimeType::AVIF];

            foreach ($convertible as $mime) {
                $media = Media::factory()->make(['mime_type' => $mime->value]);
                expect($service->needsImageConversion($media))->toBeTrue(
                    "Expected {$mime->value} to need image conversion"
                );
            }
        });
    });

    describe('needsVideoOptimization', function () {
        it('returns true only for MOV and WebM video', function () {
            $service = new MediaOptimizationService;

            $optimizable = [MimeType::MOV, MimeType::WEBM_VIDEO];

            foreach ($optimizable as $mime) {
                $media = Media::factory()->make(['mime_type' => $mime->value]);
                expect($service->needsVideoOptimization($media))->toBeTrue(
                    "Expected {$mime->value} to need video optimization"
                );
            }
        });

        it('returns false for MP4', function () {
            $service = new MediaOptimizationService;
            $media = Media::factory()->make(['mime_type' => MimeType::MP4->value]);

            expect($service->needsVideoOptimization($media))->toBeFalse();
        });
    });
});
