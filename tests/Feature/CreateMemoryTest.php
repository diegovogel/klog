<?php

use App\Models\Memory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

describe('create memory', function () {
    it('shows the create form to authenticated users', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('memories.create'))
            ->assertSuccessful()
            ->assertSee('New Memory')
            ->assertSee('Title');
    });

    it('redirects guests to login', function () {
        $this->get(route('memories.create'))
            ->assertRedirect(route('login'));
    });

    it('stores a memory with a title', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'Beach day',
            ])
            ->assertRedirect('/');

        expect(Memory::where('title', 'Beach day')->exists())->toBeTrue();
    });

    it('stores a memory without a title', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => null,
            ])
            ->assertRedirect('/');

        expect(Memory::count())->toBe(1);
    });

    it('rejects a title longer than 255 characters', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => str_repeat('a', 256),
            ])
            ->assertSessionHasErrors('title');

        expect(Memory::count())->toBe(0);
    });

    it('sets captured_at when creating a memory', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'Sunset',
            ]);

        expect(Memory::first()->captured_at)->not->toBeNull();
    });

    it('stores a memory with content', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'Formatted note',
                'content' => '<strong>Bold</strong> and <em>italic</em>',
            ])
            ->assertRedirect('/');

        $memory = Memory::first();
        expect($memory->content)->toBe('<strong>Bold</strong> and <em>italic</em>');
    });

    it('stores a memory with content but no title', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'content' => 'Just a thought.',
            ])
            ->assertRedirect('/');

        $memory = Memory::first();
        expect($memory->title)->toBeNull()
            ->and($memory->content)->toBe('Just a thought.');
    });

    it('rejects content longer than 65535 characters', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'content' => str_repeat('a', 65536),
            ])
            ->assertSessionHasErrors('content');

        expect(Memory::count())->toBe(0);
    });

    it('sanitizes HTML content before storing', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'XSS test',
                'content' => '<strong>safe</strong><script>alert(1)</script>',
            ])
            ->assertRedirect('/');

        $memory = Memory::first();
        expect($memory->content)->toBe('<strong>safe</strong>alert(1)');
    });

    it('renders content as HTML on the feed', function () {
        $user = User::factory()->create();

        Memory::factory()->create([
            'content' => '<strong>Bold</strong> and <em>italic</em>',
        ]);

        $response = $this->actingAs($user)->get('/');

        $response->assertSuccessful()
            ->assertSee('<strong>Bold</strong> and <em>italic</em>', false);
    });

    it('shows the content editor on the create form', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('memories.create'))
            ->assertSuccessful()
            ->assertSee('Content')
            ->assertSee('data-rich-editor', false);
    });

    it('shows the media upload on the create form', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('memories.create'))
            ->assertSuccessful()
            ->assertSee('Media')
            ->assertSee('data-media-upload', false);
    });

    it('stores a memory with an uploaded image', function () {
        Storage::fake('local');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('beach.jpg', 640, 480)->size(500);

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'Beach day',
                'media' => [$file],
            ])
            ->assertRedirect('/');

        $memory = Memory::first();
        expect($memory->media)->toHaveCount(1);

        $media = $memory->media->first();
        expect($media->original_filename)->toBe('beach.jpg')
            ->and($media->mime_type)->toBe('image/jpeg')
            ->and($media->type)->toBe('image')
            ->and($media->disk)->toBe('local')
            ->and($media->order)->toBe(0);

        Storage::disk('local')->assertExists($media->path);
    });

    it('stores multiple media files with correct ordering', function () {
        Storage::fake('local');
        $user = User::factory()->create();

        $image = UploadedFile::fake()->image('photo.jpg');
        $audio = UploadedFile::fake()->create('song.mp3', 1024, 'audio/mpeg');

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'Mixed media',
                'media' => [$image, $audio],
            ])
            ->assertRedirect('/');

        $memory = Memory::first();
        expect($memory->media)->toHaveCount(2);

        $sorted = $memory->media->sortBy('order');
        expect($sorted->first()->order)->toBe(0)
            ->and($sorted->last()->order)->toBe(1);
    });

    it('stores files in the uploads path', function () {
        Storage::fake('local');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('test.jpg');

        $this->actingAs($user)
            ->post(route('memories.store'), ['media' => [$file]]);

        $media = Memory::first()->media->first();
        $now = now();
        $expectedPrefix = sprintf('uploads/%s/%s/', $now->format('Y'), $now->format('m'));

        expect($media->path)->toStartWith($expectedPrefix);
    });

    it('rejects files with unsupported MIME types', function () {
        Storage::fake('local');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'Bad file',
                'media' => [$file],
            ])
            ->assertSessionHasErrors('media.0');

        expect(Memory::count())->toBe(0);
    });

    it('rejects files exceeding the maximum size', function () {
        Storage::fake('local');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('huge.jpg')->size(103424);

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'Too big',
                'media' => [$file],
            ])
            ->assertSessionHasErrors('media.0');

        expect(Memory::count())->toBe(0);
    });

    it('rejects more than 20 files', function () {
        Storage::fake('local');
        $user = User::factory()->create();

        $files = array_map(
            fn () => UploadedFile::fake()->image('photo.jpg')->size(10),
            range(1, 21),
        );

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'Too many',
                'media' => $files,
            ])
            ->assertSessionHasErrors('media');
    });

    it('stores a memory without media files', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'Text only',
            ])
            ->assertRedirect('/');

        $memory = Memory::first();
        expect($memory->media)->toHaveCount(0);
    });

    it('derives correct media type from video MIME', function () {
        Storage::fake('local');
        $user = User::factory()->create();

        $video = UploadedFile::fake()->create('clip.mp4', 5000, 'video/mp4');

        $this->actingAs($user)
            ->post(route('memories.store'), ['media' => [$video]]);

        $media = Memory::first()->media->first();
        expect($media->type)->toBe('video');
    });
});
