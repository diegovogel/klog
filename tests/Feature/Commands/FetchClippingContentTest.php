<?php

use App\Models\WebClipping;
use App\Services\WebClippingContentService;

describe('clippings:fetch-content', function () {
    it('reports when all clippings already have content', function () {
        WebClipping::factory()->withContent()->create();

        $this->artisan('clippings:fetch-content')
            ->expectsOutput('All web clippings already have content.')
            ->assertSuccessful();
    });

    it('fetches content for clippings without it', function () {
        $mock = Mockery::mock(WebClippingContentService::class);
        $mock->shouldReceive('extractText')->twice()->andReturn([
            'title' => 'Test Page',
            'content' => '<p>Extracted text</p>',
        ]);
        $this->app->instance(WebClippingContentService::class, $mock);

        WebClipping::factory()->count(2)->create();

        $this->artisan('clippings:fetch-content --limit=0')
            ->expectsOutput('Found 2 web clipping(s) to fetch.')
            ->expectsOutputToContain('Fetched 2/2 clippings. 0 failed.')
            ->assertSuccessful();

        expect(WebClipping::whereNotNull('content')->count())->toBe(2);
    });

    it('skips clippings that already have content', function () {
        $mock = Mockery::mock(WebClippingContentService::class);
        $mock->shouldReceive('extractText')->once()->andReturn([
            'title' => 'Test',
            'content' => '<p>Text</p>',
        ]);
        $this->app->instance(WebClippingContentService::class, $mock);

        WebClipping::factory()->withContent()->create();
        WebClipping::factory()->create();

        $this->artisan('clippings:fetch-content --limit=0')
            ->expectsOutput('Found 1 web clipping(s) to fetch.')
            ->assertSuccessful();
    });

    it('handles fetch failures gracefully and continues', function () {
        $callCount = 0;
        $mock = Mockery::mock(WebClippingContentService::class);
        $mock->shouldReceive('extractText')->twice()->andReturnUsing(function () use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                throw new \RuntimeException('Connection timed out');
            }

            return ['title' => 'Page', 'content' => '<p>Text</p>'];
        });
        $this->app->instance(WebClippingContentService::class, $mock);

        WebClipping::factory()->count(2)->create();

        $this->artisan('clippings:fetch-content --limit=0')
            ->expectsOutput('Found 2 web clipping(s) to fetch.')
            ->expectsOutputToContain('Fetched 1/2 clippings. 1 failed.')
            ->assertSuccessful();
    });

    it('respects the --limit option', function () {
        $mock = Mockery::mock(WebClippingContentService::class);
        $mock->shouldReceive('extractText')->once()->andReturn([
            'title' => 'Page',
            'content' => '<p>Text</p>',
        ]);
        $this->app->instance(WebClippingContentService::class, $mock);

        WebClipping::factory()->count(3)->create();

        $this->artisan('clippings:fetch-content --limit=1')
            ->expectsOutput('Found 1 web clipping(s) to fetch.')
            ->expectsOutputToContain('2 clipping(s) still need content.')
            ->assertSuccessful();
    });

    it('increments fetch_attempts on each try', function () {
        $mock = Mockery::mock(WebClippingContentService::class);
        $mock->shouldReceive('extractText')->once()->andReturn([
            'title' => null,
            'content' => null,
        ]);
        $this->app->instance(WebClippingContentService::class, $mock);

        $clipping = WebClipping::factory()->create();

        $this->artisan('clippings:fetch-content')
            ->assertSuccessful();

        expect($clipping->fresh()->fetch_attempts)->toBe(1);
    });

    it('skips clippings that have reached max attempts', function () {
        WebClipping::factory()->create([
            'content' => null,
            'fetch_attempts' => 14,
        ]);

        $this->artisan('clippings:fetch-content')
            ->expectsOutput('All web clippings already have content.')
            ->assertSuccessful();
    });

    it('warns when content extraction returns empty', function () {
        $mock = Mockery::mock(WebClippingContentService::class);
        $mock->shouldReceive('extractText')->once()->andReturn([
            'title' => null,
            'content' => null,
        ]);
        $this->app->instance(WebClippingContentService::class, $mock);

        WebClipping::factory()->create();

        $this->artisan('clippings:fetch-content')
            ->expectsOutputToContain('No content:')
            ->expectsOutputToContain('Fetched 0/1 clippings. 1 failed.')
            ->assertSuccessful();
    });
});
