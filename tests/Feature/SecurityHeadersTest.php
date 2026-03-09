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

    it('sets X-Frame-Options DENY', function () {
        $response = $this->get('/login');

        $response->assertHeader('X-Frame-Options', 'DENY');
    });

    it('sets Referrer-Policy', function () {
        $response = $this->get('/login');

        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    });

    it('sets Permissions-Policy', function () {
        $response = $this->get('/login');

        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    });
});
