<?php

use App\Models\User;

describe('PWA manifest', function () {
    it('returns valid JSON with correct content type', function () {
        $response = $this->get('/manifest.webmanifest');

        $response->assertSuccessful()
            ->assertHeader('Content-Type', 'application/manifest+json');
    });

    it('uses app name from config', function () {
        config(['app.name' => 'Ari Log']);

        $response = $this->get('/manifest.webmanifest');
        $data = $response->json();

        expect($data['name'])->toBe('Ari Log')
            ->and($data['short_name'])->toBe('Ari Log');
    });

    it('includes required manifest fields', function () {
        $response = $this->get('/manifest.webmanifest');
        $data = $response->json();

        expect($data)
            ->toHaveKeys(['name', 'short_name', 'start_url', 'display', 'background_color', 'theme_color', 'icons'])
            ->and($data['display'])->toBe('standalone')
            ->and($data['start_url'])->toBe('/')
            ->and($data['background_color'])->toBe('#faf9f7')
            ->and($data['theme_color'])->toBe('#b45a35');
    });

    it('includes all four icon entries', function () {
        $response = $this->get('/manifest.webmanifest');
        $icons = $response->json('icons');

        expect($icons)->toHaveCount(4);

        $sizes = collect($icons)->pluck('sizes')->all();
        expect($sizes)->toContain('192x192', '512x512');

        $maskable = collect($icons)->where('purpose', 'maskable');
        expect($maskable)->toHaveCount(2);
    });
});

describe('PWA offline page', function () {
    it('is accessible without authentication', function () {
        $this->get('/offline')
            ->assertSuccessful()
            ->assertSee('You appear to be offline');
    });

    it('includes a reload button', function () {
        $this->get('/offline')
            ->assertSuccessful()
            ->assertSee('Try Again');
    });
});

describe('PWA meta tags', function () {
    it('includes PWA meta tags in the authenticated layout', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertSuccessful()
            ->assertSee('<meta name="theme-color" content="#b45a35">', false)
            ->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false)
            ->assertSee('apple-mobile-web-app-capable', false)
            ->assertSee('apple-touch-icon', false)
            ->assertSee('viewport-fit=cover', false);
    });

    it('includes PWA meta tags in the public layout', function () {
        $this->get('/login')
            ->assertSee('<meta name="theme-color" content="#b45a35">', false)
            ->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false)
            ->assertSee('apple-mobile-web-app-capable', false)
            ->assertSee('apple-touch-icon', false)
            ->assertSee('viewport-fit=cover', false);
    });
});

describe('PWA static assets', function () {
    it('serves the service worker file', function () {
        expect(file_exists(public_path('sw.js')))->toBeTrue();
    });

    it('serves favicon.svg', function () {
        expect(file_exists(public_path('favicon.svg')))->toBeTrue();
    });

    it('serves apple-touch-icon.png', function () {
        expect(file_exists(public_path('apple-touch-icon.png')))->toBeTrue();
    });

    it('serves all manifest icons', function () {
        expect(file_exists(public_path('icons/icon-192.png')))->toBeTrue()
            ->and(file_exists(public_path('icons/icon-512.png')))->toBeTrue()
            ->and(file_exists(public_path('icons/icon-maskable-192.png')))->toBeTrue()
            ->and(file_exists(public_path('icons/icon-maskable-512.png')))->toBeTrue();
    });
});
