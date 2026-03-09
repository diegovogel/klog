<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\TwoFactorSettingsController;
use App\Http\Requests\StoreMemoryRequest;
use App\Models\Memory;
use App\Services\HtmlSanitizer;
use App\Services\MediaStorageService;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PWA Routes (public, no auth)
|--------------------------------------------------------------------------
*/

Route::get('manifest.webmanifest', function () {
    return response()->json([
        'name' => config('app.name'),
        'short_name' => config('app.name'),
        'description' => 'Your personal memory keeper',
        'start_url' => '/',
        'scope' => '/',
        'display' => 'standalone',
        'background_color' => '#faf9f7',
        'theme_color' => '#b45a35',
        'orientation' => 'any',
        'icons' => [
            ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
            ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
            ['src' => '/icons/icon-maskable-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
            ['src' => '/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ],
    ], 200, ['Content-Type' => 'application/manifest+json']);
})->name('pwa.manifest');

Route::get('offline', fn () => view('offline'))->name('offline');

/*
|--------------------------------------------------------------------------
| Auth Routes
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('two-factor/challenge', [TwoFactorChallengeController::class, 'show'])
        ->name('two-factor.challenge');
    Route::post('two-factor/challenge', [TwoFactorChallengeController::class, 'verify'])
        ->name('two-factor.verify');
    Route::post('two-factor/resend', [TwoFactorChallengeController::class, 'resend'])
        ->middleware('throttle:5,1')
        ->name('two-factor.resend');

    Route::middleware('two-factor')->group(function () {
        Route::get('media/{filename}', [MediaController::class, 'show'])->name('media.show');

        Route::get('/', function () {
            return view('memory-feed', [
                'memories' => Memory::latest('memory_date')->paginate(10),
            ]);
        });

        Route::get('memories/create', function () {
            $latestMemoryDate = Memory::max('memory_date');

            return view('memories.create', [
                'latestMemoryDate' => $latestMemoryDate,
            ]);
        })->name('memories.create');

        Route::post('memories', function (StoreMemoryRequest $request, HtmlSanitizer $sanitizer, MediaStorageService $mediaStorage) {
            $memory = Memory::create([
                'title' => $request->validated('title'),
                'content' => $sanitizer->sanitize($request->validated('content')),
                'memory_date' => $request->validated('memory_date'),
            ]);

            if ($request->hasFile('media')) {
                $mediaStorage->storeForMemory($memory, $request->file('media'));
            }

            foreach ($request->validated('clippings', []) as $url) {
                $memory->webClippings()->create(['url' => $url]);
            }

            return redirect('/')->with('success', 'Memory saved.');
        })->name('memories.store');

        Route::delete('memories/{memory}', function (Memory $memory) {
            $memory->deleteWithRelations();

            return redirect('/')->with('success', 'Memory deleted.');
        })->name('memories.destroy');

        Route::get('settings/two-factor', [TwoFactorSettingsController::class, 'show'])
            ->name('two-factor.settings');
        Route::post('settings/two-factor/enable', [TwoFactorSettingsController::class, 'enable'])
            ->name('two-factor.enable');
        Route::post('settings/two-factor/disable', [TwoFactorSettingsController::class, 'disable'])
            ->name('two-factor.disable');
        Route::post('settings/two-factor/recovery-codes', [TwoFactorSettingsController::class, 'regenerateRecoveryCodes'])
            ->name('two-factor.recovery-codes');
        Route::get('settings/two-factor/authenticator/setup', [TwoFactorSettingsController::class, 'showAuthenticatorSetup'])
            ->name('two-factor.authenticator.setup');
        Route::post('settings/two-factor/authenticator/confirm', [TwoFactorSettingsController::class, 'confirmAuthenticator'])
            ->name('two-factor.authenticator.confirm');
    });
});
