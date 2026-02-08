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
});
