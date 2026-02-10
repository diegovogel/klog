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
});
