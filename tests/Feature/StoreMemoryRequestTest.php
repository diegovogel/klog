<?php

use App\Models\User;

describe('StoreMemoryRequest validation', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
    });

    it('accepts https clipping URLs', function () {
        $response = $this->actingAs($this->user)->post(route('memories.store'), [
            'memory_date' => now()->toDateString(),
            'clippings' => ['https://example.com/article'],
        ]);

        $response->assertSessionDoesntHaveErrors('clippings.0');
    });

    it('accepts http clipping URLs', function () {
        $response = $this->actingAs($this->user)->post(route('memories.store'), [
            'memory_date' => now()->toDateString(),
            'clippings' => ['http://example.com/article'],
        ]);

        $response->assertSessionDoesntHaveErrors('clippings.0');
    });

    it('rejects javascript: clipping URLs', function () {
        $response = $this->actingAs($this->user)->post(route('memories.store'), [
            'memory_date' => now()->toDateString(),
            'clippings' => ['javascript:alert(1)'],
        ]);

        $response->assertSessionHasErrors('clippings.0');
    });

    it('rejects data: clipping URLs', function () {
        $response = $this->actingAs($this->user)->post(route('memories.store'), [
            'memory_date' => now()->toDateString(),
            'clippings' => ['data:text/html,<script>alert(1)</script>'],
        ]);

        $response->assertSessionHasErrors('clippings.0');
    });
});
