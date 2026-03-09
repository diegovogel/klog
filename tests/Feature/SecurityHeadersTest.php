<?php

use App\Models\User;

describe('SecurityHeaders middleware', function () {
    it('sets X-Content-Type-Options nosniff on all responses', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    });

    it('sets X-Content-Type-Options nosniff on guest responses', function () {
        $response = $this->get('/login');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    });
});
