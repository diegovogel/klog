<?php

use App\Models\Child;
use App\Models\Memory;

it('creates a child with a name', function () {
    $child = Child::factory()->create(['name' => 'Emma']);

    expect($child->name)->toBe('Emma');
});

it('enforces unique names', function () {
    Child::factory()->create(['name' => 'Liam']);

    expect(fn () => Child::factory()->create(['name' => 'Liam']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('finds or creates a child by name', function () {
    $first = Child::findOrCreateByName('Sophie');
    $second = Child::findOrCreateByName('Sophie');

    expect($first->id)->toBe($second->id)
        ->and(Child::count())->toBe(1);
});

it('trims whitespace when finding or creating', function () {
    $child = Child::findOrCreateByName('  Noah  ');

    expect($child->name)->toBe('Noah');
});

it('belongs to many memories', function () {
    $child = Child::factory()->create();
    $memories = Memory::factory()->count(3)->create();

    $child->memories()->attach($memories->pluck('id'));

    expect($child->memories()->count())->toBe(3);
});

it('supports soft deletes', function () {
    $child = Child::factory()->create();
    $child->delete();

    expect(Child::count())->toBe(0)
        ->and(Child::withTrashed()->count())->toBe(1);
});
