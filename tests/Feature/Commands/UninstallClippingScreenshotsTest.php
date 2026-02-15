<?php

use App\Models\Media;
use App\Models\WebClipping;
use Illuminate\Support\Facades\Process;

describe('clippings:uninstall-screenshots', function () {
    it('removes packages successfully', function () {
        Process::fake([
            'composer remove spatie/browsershot' => Process::result(exitCode: 0),
            'npm uninstall puppeteer' => Process::result(exitCode: 0),
        ]);

        $this->artisan('clippings:uninstall-screenshots')
            ->expectsOutput('Screenshot packages removed.')
            ->expectsOutput('Existing screenshot images have been preserved as regular media.')
            ->assertSuccessful();
    });

    it('reports failure when composer remove fails', function () {
        Process::fake([
            'composer remove spatie/browsershot' => Process::result(
                output: '',
                errorOutput: 'Package not found',
                exitCode: 1,
            ),
        ]);

        $this->artisan('clippings:uninstall-screenshots')
            ->expectsOutput('Failed to remove spatie/browsershot.')
            ->assertFailed();
    });

    it('reports failure when npm uninstall fails', function () {
        Process::fake([
            'composer remove spatie/browsershot' => Process::result(exitCode: 0),
            'npm uninstall puppeteer' => Process::result(
                output: '',
                errorOutput: 'npm ERR!',
                exitCode: 1,
            ),
        ]);

        $this->artisan('clippings:uninstall-screenshots')
            ->expectsOutput('Failed to remove puppeteer.')
            ->assertFailed();
    });

    it('preserves existing screenshot media records', function () {
        Process::fake([
            'composer remove spatie/browsershot' => Process::result(exitCode: 0),
            'npm uninstall puppeteer' => Process::result(exitCode: 0),
        ]);

        $clipping = WebClipping::factory()->create();
        $screenshot = Media::factory()->image()->create([
            'mediable_type' => WebClipping::class,
            'mediable_id' => $clipping->id,
        ]);

        $this->artisan('clippings:uninstall-screenshots')
            ->assertSuccessful();

        expect(Media::find($screenshot->id))->not->toBeNull();
    });
});
