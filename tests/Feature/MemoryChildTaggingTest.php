<?php

use App\Models\Child;
use App\Models\Memory;
use App\Models\User;

describe('child tagging', function () {
    it('shows the child selector on the create form', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('memories.create'))
            ->assertSuccessful()
            ->assertSee('Children')
            ->assertSee('data-child-selector', false);
    });

    it('shows existing children as options on the create form', function () {
        $user = User::factory()->create();
        Child::factory()->create(['name' => 'Emma']);
        Child::factory()->create(['name' => 'Liam']);

        $this->actingAs($user)
            ->get(route('memories.create'))
            ->assertSee('Emma')
            ->assertSee('Liam');
    });

    it('stores a memory with existing children', function () {
        $user = User::factory()->create();
        $emma = Child::factory()->create(['name' => 'Emma']);
        $liam = Child::factory()->create(['name' => 'Liam']);

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'Park day',
                'memory_date' => '2026-02-15',
                'children' => [$emma->id, $liam->id],
            ])
            ->assertRedirect('/');

        $memory = Memory::first();
        expect($memory->children)->toHaveCount(2);
        expect($memory->children->pluck('name')->all())->toContain('Emma', 'Liam');
    });

    it('stores a memory with new children', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'First day',
                'memory_date' => '2026-02-15',
                'new_children' => ['Sophie', 'Noah'],
            ])
            ->assertRedirect('/');

        $memory = Memory::first();
        expect($memory->children)->toHaveCount(2);
        expect(Child::count())->toBe(2);
        expect($memory->children->pluck('name')->all())->toContain('Sophie', 'Noah');
    });

    it('stores a memory with both existing and new children', function () {
        $user = User::factory()->create();
        $emma = Child::factory()->create(['name' => 'Emma']);

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'Family outing',
                'memory_date' => '2026-02-15',
                'children' => [$emma->id],
                'new_children' => ['Liam'],
            ])
            ->assertRedirect('/');

        $memory = Memory::first();
        expect($memory->children)->toHaveCount(2);
        expect($memory->children->pluck('name')->all())->toContain('Emma', 'Liam');
    });

    it('stores a memory without any children', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'Solo adventure',
                'memory_date' => '2026-02-15',
            ])
            ->assertRedirect('/');

        expect(Memory::first()->children)->toHaveCount(0);
    });

    it('rejects invalid child IDs', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'Bad ID',
                'memory_date' => '2026-02-15',
                'children' => [999],
            ])
            ->assertSessionHasErrors('children.0');
    });

    it('rejects new child names exceeding 100 characters', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'Long name',
                'memory_date' => '2026-02-15',
                'new_children' => [str_repeat('a', 101)],
            ])
            ->assertSessionHasErrors('new_children.0');
    });

    it('does not duplicate existing children when creating via new_children', function () {
        $user = User::factory()->create();
        Child::factory()->create(['name' => 'Emma']);

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'Duplicate test',
                'memory_date' => '2026-02-15',
                'new_children' => ['Emma'],
            ])
            ->assertRedirect('/');

        expect(Child::count())->toBe(1);
        expect(Memory::first()->children)->toHaveCount(1);
    });

    it('displays children on the memory card', function () {
        $user = User::factory()->create();
        $memory = Memory::factory()->create();
        $emma = Child::factory()->create(['name' => 'Emma']);
        $liam = Child::factory()->create(['name' => 'Liam']);
        $memory->children()->attach([$emma->id, $liam->id]);

        $this->actingAs($user)
            ->get('/')
            ->assertSee('Emma')
            ->assertSee('Liam');
    });

    it('does not show child labels when no children are tagged', function () {
        $user = User::factory()->create();
        Memory::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertDontSee('memory-card__child-label', false);
    });

    it('restores a soft-deleted child when submitted as new_children', function () {
        $user = User::factory()->create();
        $child = Child::factory()->create(['name' => 'Emma']);
        $child->delete();

        $this->actingAs($user)
            ->post(route('memories.store'), [
                'title' => 'Restore test',
                'memory_date' => '2026-02-15',
                'new_children' => ['Emma'],
            ])
            ->assertRedirect('/');

        expect(Child::count())->toBe(1);
        expect(Child::first()->name)->toBe('Emma');
        expect(Memory::first()->children)->toHaveCount(1);
    });

    it('preserves new_children after validation failure', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('memories.create'))
            ->post(route('memories.store'), [
                'title' => str_repeat('a', 256), // triggers validation error
                'memory_date' => '2026-02-15',
                'new_children' => ['Sophie', 'Noah'],
            ])
            ->assertRedirect(route('memories.create'))
            ->assertSessionHasErrors('title');

        $this->actingAs($user)
            ->get(route('memories.create'))
            ->assertSee('Sophie')
            ->assertSee('Noah');
    });
});
