<?php

use App\Models\Memory;
use App\Models\Tag;
use App\Models\User;

describe('memory tagging', function () {
    it('shows the tag input on the create form', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('memories.create'))
            ->assertSuccessful()
            ->assertSee('Tags')
            ->assertSee('data-tag-input', false);
    });

    it('shows existing tags in the datalist', function () {
        $user = User::factory()->create();
        Tag::factory()->create(['name' => 'coffee']);
        Tag::factory()->create(['name' => 'park']);

        $this->actingAs($user)
            ->get(route('memories.create'))
            ->assertSee('coffee')
            ->assertSee('park');
    });

    it('stores a memory with existing tags', function () {
        $user = User::factory()->create();
        $coffee = Tag::factory()->create(['name' => 'coffee']);
        $park = Tag::factory()->create(['name' => 'park']);

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'Morning walk',
                'memory_date' => '2026-02-15',
                'tags' => [$coffee->id, $park->id],
            ])
            ->assertRedirect('/');

        $memory = Memory::first();
        expect($memory->tags)->toHaveCount(2);
        expect($memory->tags->pluck('name')->all())->toContain('coffee', 'park');
    });

    it('stores a memory with new tags', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'Beach day',
                'memory_date' => '2026-02-15',
                'new_tags' => ['sunset', 'ocean'],
            ])
            ->assertRedirect('/');

        $memory = Memory::first();
        expect($memory->tags)->toHaveCount(2);
        expect(Tag::count())->toBe(2);
        expect($memory->tags->pluck('name')->all())->toContain('sunset', 'ocean');
    });

    it('stores a memory with both existing and new tags', function () {
        $user = User::factory()->create();
        $coffee = Tag::factory()->create(['name' => 'coffee']);

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'Cafe visit',
                'memory_date' => '2026-02-15',
                'tags' => [$coffee->id],
                'new_tags' => ['latte'],
            ])
            ->assertRedirect('/');

        $memory = Memory::first();
        expect($memory->tags)->toHaveCount(2);
        expect($memory->tags->pluck('name')->all())->toContain('coffee', 'latte');
    });

    it('stores a memory without any tags', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'No tags',
                'memory_date' => '2026-02-15',
            ])
            ->assertRedirect('/');

        expect(Memory::first()->tags)->toHaveCount(0);
    });

    it('rejects invalid tag IDs', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'Bad ID',
                'memory_date' => '2026-02-15',
                'tags' => [999],
            ])
            ->assertSessionHasErrors('tags.0');
    });

    it('rejects soft-deleted tag IDs', function () {
        $user = User::factory()->create();
        $tag = Tag::factory()->create(['name' => 'coffee', 'slug' => 'coffee']);
        $tag->delete();

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'Deleted tag',
                'memory_date' => '2026-02-15',
                'tags' => [$tag->id],
            ])
            ->assertSessionHasErrors('tags.0');
    });

    it('rejects new tag names exceeding 100 characters', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'Long tag',
                'memory_date' => '2026-02-15',
                'new_tags' => [str_repeat('a', 101)],
            ])
            ->assertSessionHasErrors('new_tags.0');
    });

    it('does not duplicate existing tags when creating via new_tags', function () {
        $user = User::factory()->create();
        Tag::factory()->create(['name' => 'coffee', 'slug' => 'coffee']);

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'Duplicate test',
                'memory_date' => '2026-02-15',
                'new_tags' => ['coffee'],
            ])
            ->assertRedirect('/');

        expect(Tag::count())->toBe(1);
        expect(Memory::first()->tags)->toHaveCount(1);
    });

    it('treats tags with different casing as the same tag', function () {
        $user = User::factory()->create();
        Tag::factory()->create(['name' => 'Coffee', 'slug' => 'coffee']);

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'Case test',
                'memory_date' => '2026-02-15',
                'new_tags' => ['coffee'],
            ])
            ->assertRedirect('/');

        expect(Tag::count())->toBe(1);
        expect(Memory::first()->tags)->toHaveCount(1);
    });

    it('displays tags on the memory card', function () {
        $user = User::factory()->create();
        $memory = Memory::factory()->create();
        $coffee = Tag::factory()->create(['name' => 'coffee']);
        $park = Tag::factory()->create(['name' => 'park']);
        $memory->tags()->attach([$coffee->id, $park->id]);

        $this->actingAs($user)
            ->get('/')
            ->assertSee('Tags:')
            ->assertSee('coffee')
            ->assertSee('park');
    });

    it('does not show tags label when no tags exist', function () {
        $user = User::factory()->create();
        Memory::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertDontSee('Tags:');
    });

    it('preserves new_tags after validation failure', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('memories.create'))
            ->post(route('memories.store'), [
                'title' => str_repeat('a', 256),
                'memory_date' => '2026-02-15',
                'new_tags' => ['sunset', 'beach'],
            ])
            ->assertRedirect(route('memories.create'))
            ->assertSessionHasErrors('title');

        $this->actingAs($user)
            ->get(route('memories.create'))
            ->assertSee('sunset')
            ->assertSee('beach');
    });

    it('restores a soft-deleted tag when submitted as new_tags', function () {
        $user = User::factory()->create();
        $tag = Tag::factory()->create(['name' => 'coffee', 'slug' => 'coffee']);
        $tag->delete();

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'Restore test',
                'memory_date' => '2026-02-15',
                'new_tags' => ['coffee'],
            ])
            ->assertRedirect('/');

        expect(Tag::count())->toBe(1);
        expect(Tag::first()->name)->toBe('coffee');
        expect(Memory::first()->tags)->toHaveCount(1);
    });
});
