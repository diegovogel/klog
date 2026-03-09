<?php

use App\Models\Media;
use App\Models\Memory;
use App\Models\User;
use App\Models\WebClipping;

describe('DELETE /memories/{memory}', function () {
    it('soft-deletes a memory and its relations', function () {
        $user = User::factory()->create();
        $memory = Memory::factory()->create();
        Media::factory()->image()->create(['mediable_id' => $memory->id, 'mediable_type' => $memory->getMorphClass()]);
        WebClipping::factory()->create(['memory_id' => $memory->id]);

        $this->actingAs($user)
            ->delete(route('memories.destroy', $memory))
            ->assertRedirect('/');

        expect(Memory::count())->toBe(0)
            ->and(Memory::withTrashed()->count())->toBe(1)
            ->and(Media::count())->toBe(0)
            ->and(WebClipping::count())->toBe(0);
    });

    it('redirects with success message', function () {
        $user = User::factory()->create();
        $memory = Memory::factory()->create();

        $this->actingAs($user)
            ->delete(route('memories.destroy', $memory))
            ->assertRedirect('/')
            ->assertSessionHas('success', 'Memory deleted.');
    });

    it('returns 404 for non-existent memory', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete(route('memories.destroy', 999))
            ->assertNotFound();
    });

    it('requires authentication', function () {
        $memory = Memory::factory()->create();

        $this->delete(route('memories.destroy', $memory))
            ->assertRedirect(route('login'));
    });
});
