<?php

use App\Models\Memory;
use App\Models\User;

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
});
