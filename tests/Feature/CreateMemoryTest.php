<?php

use App\Models\Memory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
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
                'memory_date' => '2026-02-15',
            ])
            ->assertRedirect('/');

        expect(Memory::where('title', 'Beach day')->exists())->toBeTrue();
    });

    it('stores a memory without a title', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => null,
                'memory_date' => '2026-02-15',
            ])
            ->assertRedirect('/');

        expect(Memory::count())->toBe(1);
    });

    it('rejects a title longer than 255 characters', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => str_repeat('a', 256),
                'memory_date' => '2026-02-15',
            ])
            ->assertSessionHasErrors('title');

        expect(Memory::count())->toBe(0);
    });

    it('stores the submitted memory_date', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'Sunset',
                'memory_date' => '2025-06-15',
            ]);

        expect(Memory::first()->memory_date->format('Y-m-d'))->toBe('2025-06-15');
    });

    it('defaults memory_date to today on the create form', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('memories.create'))
            ->assertSuccessful()
            ->assertSee('name="memory_date"', false)
            ->assertSee('value="'.now()->format('Y-m-d').'"', false);
    });

    it('requires memory_date', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'No date',
            ])
            ->assertSessionHasErrors('memory_date');

        expect(Memory::count())->toBe(0);
    });

    it('rejects a future memory_date', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'Future',
                'memory_date' => now()->addDay()->format('Y-m-d'),
            ])
            ->assertSessionHasErrors('memory_date');

        expect(Memory::count())->toBe(0);
    });

    it('stores a memory with content', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'Formatted note',
                'content' => '<strong>Bold</strong> and <em>italic</em>',
                'memory_date' => '2026-02-15',
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
                'memory_date' => '2026-02-15',
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
                'memory_date' => '2026-02-15',
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
                'memory_date' => '2026-02-15',
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

    it('renders accessible rich editor attributes', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('memories.create'))
            ->assertSuccessful();

        // Contenteditable has proper ARIA attributes
        $response->assertSee('role="textbox"', false);
        $response->assertSee('aria-multiline="true"', false);
        $response->assertSee('aria-label="Content"', false);

        // Toolbar has proper role
        $response->assertSee('role="toolbar"', false);

        // Toolbar buttons have aria-pressed and aria-label
        $response->assertSee('aria-pressed="false"', false);
        $response->assertSee('aria-label="Bold"', false);
        $response->assertSee('aria-label="Italic"', false);
        $response->assertSee('aria-label="Link"', false);
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
                'memory_date' => '2026-02-15',
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
                'memory_date' => '2026-02-15',
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
            ->post(route('memories.store'), [
                'memory_date' => '2026-02-15',
                'media' => [$file],
            ]);

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
                'memory_date' => '2026-02-15',
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
                'memory_date' => '2026-02-15',
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
                'memory_date' => '2026-02-15',
                'media' => $files,
            ])
            ->assertSessionHasErrors('media');
    });

    it('stores a memory without media files', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'Text only',
                'memory_date' => '2026-02-15',
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
            ->post(route('memories.store'), [
                'memory_date' => '2026-02-15',
                'media' => [$video],
            ]);

        $media = Memory::first()->media->first();
        expect($media->type)->toBe('video');
    });

    it('stores a captured WebM video', function () {
        Queue::fake();
        Storage::fake('local');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->create('video-20260209-143000.webm', 2000, 'video/webm');

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'Recorded moment',
                'memory_date' => '2026-02-15',
                'media' => [$file],
            ])
            ->assertRedirect('/');

        $media = Memory::first()->media->first();
        expect($media->mime_type)->toBe('video/webm')
            ->and($media->type)->toBe('video')
            ->and($media->disk)->toBe('local');

        Storage::disk('local')->assertExists($media->path);
    });

    it('stores a captured WebM audio recording', function () {
        Storage::fake('local');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->create('audio-20260209-143000.webm', 500, 'audio/webm');

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'Voice note',
                'memory_date' => '2026-02-15',
                'media' => [$file],
            ])
            ->assertRedirect('/');

        $media = Memory::first()->media->first();
        expect($media->mime_type)->toBe('audio/webm')
            ->and($media->type)->toBe('audio')
            ->and($media->disk)->toBe('local');

        Storage::disk('local')->assertExists($media->path);
    });

    it('shows the web clippings repeater on the create form', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('memories.create'))
            ->assertSuccessful()
            ->assertSee('Web Clippings')
            ->assertSee('data-web-clippings', false);
    });

    it('stores a memory with web clippings', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'Bookmarks',
                'memory_date' => '2026-02-15',
                'clippings' => [
                    'https://example.com/article',
                    'https://laravel.com/docs',
                ],
            ])
            ->assertRedirect('/');

        $memory = Memory::first();
        expect($memory->webClippings)->toHaveCount(2);

        $urls = $memory->webClippings->pluck('url')->all();
        expect($urls)->toContain('https://example.com/article')
            ->toContain('https://laravel.com/docs');
    });

    it('stores a memory without clippings', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'No links',
                'memory_date' => '2026-02-15',
            ])
            ->assertRedirect('/');

        expect(Memory::first()->webClippings)->toHaveCount(0);
    });

    it('rejects invalid clipping URLs', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'Bad URL',
                'memory_date' => '2026-02-15',
                'clippings' => ['not-a-url'],
            ])
            ->assertSessionHasErrors('clippings.0');

        expect(Memory::count())->toBe(0);
    });

    it('rejects clipping URLs exceeding 2048 characters', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'Long URL',
                'memory_date' => '2026-02-15',
                'clippings' => ['https://example.com/'.str_repeat('a', 2040)],
            ])
            ->assertSessionHasErrors('clippings.0');

        expect(Memory::count())->toBe(0);
    });

    it('strips empty clipping rows before validation', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'With blanks',
                'memory_date' => '2026-02-15',
                'clippings' => ['', 'https://example.com', '', null],
            ])
            ->assertRedirect('/');

        $memory = Memory::first();
        expect($memory->webClippings)->toHaveCount(1)
            ->and($memory->webClippings->first()->url)->toBe('https://example.com');
    });

    it('succeeds when all clipping rows are empty', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'All blank clippings',
                'memory_date' => '2026-02-15',
                'clippings' => ['', '', null],
            ])
            ->assertRedirect('/');

        expect(Memory::first()->webClippings)->toHaveCount(0);
    });

    it('shows a global error banner when validation fails', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('memories.create'))
            ->post(route('memories.store'), [
                'title' => str_repeat('a', 256),
                'memory_date' => '2026-02-15',
            ])
            ->assertRedirect(route('memories.create'))
            ->assertSessionHasErrors('title');

        $this->actingAs($user)
            ->get(route('memories.create'))
            ->assertSee('There were problems with some of the memory info');
    });

    it('does not show the error banner on a fresh create form', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('memories.create'))
            ->assertDontSee('There were problems');
    });

    it('stores a memory with clippings and media together', function () {
        Storage::fake('local');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('photo.jpg');

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'Full memory',
                'content' => '<p>Great day</p>',
                'memory_date' => '2026-02-15',
                'media' => [$file],
                'clippings' => ['https://example.com'],
            ])
            ->assertRedirect('/');

        $memory = Memory::first();
        expect($memory->media)->toHaveCount(1)
            ->and($memory->webClippings)->toHaveCount(1)
            ->and($memory->content)->not->toBeEmpty();
    });
});
