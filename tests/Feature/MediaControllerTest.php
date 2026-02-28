<?php

use App\Models\Media;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

describe('media serving', function () {
    it('serves a media file to authenticated users', function () {
        $user = User::factory()->create();
        $media = Media::factory()->image()->create(['disk' => 'local']);

        Storage::disk('local')->put($media->path, 'fake-image-content');

        $this->actingAs($user)
            ->get(route('media.show', $media->filename))
            ->assertSuccessful()
            ->assertHeader('Content-Type', 'image/jpeg');
    });

    it('redirects unauthenticated users to login', function () {
        $media = Media::factory()->image()->create(['disk' => 'local']);

        Storage::disk('local')->put($media->path, 'fake-image-content');

        $this->get(route('media.show', $media->filename))
            ->assertRedirect(route('login'));
    });

    it('returns 404 for nonexistent filename', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('media.show', 'nonexistent-uuid'))
            ->assertNotFound();
    });

    it('returns 404 for soft-deleted media', function () {
        $user = User::factory()->create();
        $media = Media::factory()->image()->create(['disk' => 'local']);

        Storage::disk('local')->put($media->path, 'fake-image-content');
        $media->delete();

        $this->actingAs($user)
            ->get(route('media.show', $media->filename))
            ->assertNotFound();
    });

    it('returns 404 when file is missing from disk', function () {
        $user = User::factory()->create();
        $media = Media::factory()->image()->create(['disk' => 'local']);

        $this->actingAs($user)
            ->get(route('media.show', $media->filename))
            ->assertNotFound();
    });

    it('serves video files with correct content type', function () {
        $user = User::factory()->create();
        $media = Media::factory()->video()->create(['disk' => 'local']);

        Storage::disk('local')->put($media->path, 'fake-video-content');

        $this->actingAs($user)
            ->get(route('media.show', $media->filename))
            ->assertSuccessful()
            ->assertHeader('Content-Type', 'video/quicktime');
    });

    it('serves audio files with correct content type', function () {
        $user = User::factory()->create();
        $media = Media::factory()->audio()->create(['disk' => 'local']);

        Storage::disk('local')->put($media->path, 'fake-audio-content');

        $this->actingAs($user)
            ->get(route('media.show', $media->filename))
            ->assertSuccessful()
            ->assertHeader('Content-Type', 'audio/m4a');
    });

    it('includes Accept-Ranges header in normal responses', function () {
        $user = User::factory()->create();
        $media = Media::factory()->image()->create(['disk' => 'local']);

        Storage::disk('local')->put($media->path, 'fake-image-content');

        $this->actingAs($user)
            ->get(route('media.show', $media->filename))
            ->assertSuccessful()
            ->assertHeader('Accept-Ranges', 'bytes');
    });
});

describe('range requests', function () {
    it('returns partial content for a valid range', function () {
        $user = User::factory()->create();
        $media = Media::factory()->video()->create(['disk' => 'local']);
        $content = str_repeat('x', 1000);

        Storage::disk('local')->put($media->path, $content);

        $this->actingAs($user)
            ->get(route('media.show', $media->filename), ['Range' => 'bytes=0-99'])
            ->assertStatus(206)
            ->assertHeader('Content-Length', '100')
            ->assertHeader('Content-Range', 'bytes 0-99/1000')
            ->assertHeader('Accept-Ranges', 'bytes');
    });

    it('returns the correct byte range from the middle of a file', function () {
        $user = User::factory()->create();
        $media = Media::factory()->video()->create(['disk' => 'local']);
        $content = 'abcdefghijklmnopqrstuvwxyz';

        Storage::disk('local')->put($media->path, $content);

        $response = $this->actingAs($user)
            ->get(route('media.show', $media->filename), ['Range' => 'bytes=10-14']);

        $response->assertStatus(206);
        expect($response->streamedContent())->toBe('klmno');
    });

    it('handles open-ended range requests', function () {
        $user = User::factory()->create();
        $media = Media::factory()->video()->create(['disk' => 'local']);
        $content = str_repeat('x', 500);

        Storage::disk('local')->put($media->path, $content);

        $this->actingAs($user)
            ->get(route('media.show', $media->filename), ['Range' => 'bytes=200-'])
            ->assertStatus(206)
            ->assertHeader('Content-Length', '300')
            ->assertHeader('Content-Range', 'bytes 200-499/500');
    });

    it('handles suffix range requests', function () {
        $user = User::factory()->create();
        $media = Media::factory()->video()->create(['disk' => 'local']);
        $content = 'abcdefghij';

        Storage::disk('local')->put($media->path, $content);

        $response = $this->actingAs($user)
            ->get(route('media.show', $media->filename), ['Range' => 'bytes=-3']);

        $response->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 7-9/10');
        expect($response->streamedContent())->toBe('hij');
    });

    it('returns 416 for an out-of-range request', function () {
        $user = User::factory()->create();
        $media = Media::factory()->video()->create(['disk' => 'local']);

        Storage::disk('local')->put($media->path, 'short');

        $this->actingAs($user)
            ->get(route('media.show', $media->filename), ['Range' => 'bytes=999-1999'])
            ->assertStatus(416)
            ->assertHeader('Content-Range', 'bytes */5');
    });
});
