<?php

use App\Http\Controllers\AccountSettingsController;
use App\Http\Controllers\AppSettingsController;
use App\Http\Controllers\Auth\InviteController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\ScreenshotSettingsController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TwoFactorSettingsController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\UrlCheckController;
use App\Http\Controllers\UserManagementController;
use App\Http\Requests\StoreMemoryRequest;
use App\Models\Child;
use App\Models\Memory;
use App\Models\Tag;
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

    Route::get('invites/{token}', [InviteController::class, 'show'])->name('invites.show');
    Route::post('invites/{token}', [InviteController::class, 'accept'])->name('invites.accept');
});

Route::middleware(['auth', 'user-active'])->group(function () {
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

        Route::get('url-check', [UrlCheckController::class, 'check'])
            ->middleware('throttle:30,1')
            ->name('url-check');

        Route::post('uploads/init', [UploadController::class, 'init'])->name('uploads.init');
        Route::post('uploads/{uploadSession}/chunk', [UploadController::class, 'chunk'])->name('uploads.chunk');
        Route::delete('uploads/{uploadSession}', [UploadController::class, 'cancel'])->name('uploads.cancel');

        Route::get('/', function () {
            return view('memory-feed', [
                'memories' => Memory::with('children', 'tags', 'user')->latest('memory_date')->paginate(10),
            ]);
        });

        Route::get('search', [SearchController::class, 'index'])->name('search');

        Route::get('memories/create', function () {
            $latestMemoryDate = Memory::max('memory_date');

            return view('memories.create', [
                'latestMemoryDate' => $latestMemoryDate,
                'children' => Child::orderBy('name')->get(),
                'tags' => Tag::orderBy('name')->get(),
            ]);
        })->name('memories.create');

        Route::post('memories', function (StoreMemoryRequest $request, HtmlSanitizer $sanitizer, MediaStorageService $mediaStorage) {
            // Skip MemoryObserver here — its `saved` handler would index
            // an empty row before tags, web clippings, and children are
            // attached below. One explicit reindexSearch() at the bottom
            // does the real work.
            $memory = Memory::withoutEvents(fn () => Memory::create([
                'user_id' => $request->user()->id,
                'title' => $request->validated('title'),
                'content' => $sanitizer->sanitize($request->validated('content')),
                'memory_date' => $request->validated('memory_date'),
            ]));

            if ($request->has('uploads')) {
                $mediaStorage->attachUploadSessions($memory, $request->validated('uploads'));
            }

            if ($request->hasFile('media')) {
                $mediaStorage->storeForMemory($memory, $request->file('media'));
            }

            foreach ($request->validated('clippings', []) as $url) {
                $memory->webClippings()->create(['url' => $url]);
            }

            $childIds = collect($request->validated('children', []))->map(fn ($id) => (int) $id);
            foreach ($request->validated('new_children', []) as $name) {
                $childIds->push(Child::findOrCreateByName($name)->id);
            }
            if ($childIds->isNotEmpty()) {
                $memory->children()->attach($childIds->unique());
            }

            $tagIds = collect($request->validated('tags', []))->map(fn ($id) => (int) $id);
            foreach ($request->validated('new_tags', []) as $name) {
                $tagIds->push(Tag::findOrCreateByName($name)->id);
            }
            if ($tagIds->isNotEmpty()) {
                $memory->tags()->attach($tagIds->unique());
            }

            $memory->reindexSearch();

            return redirect('/')->with('success', 'Memory saved.');
        })->name('memories.store');

        Route::delete('memories/{memory}', function (Memory $memory) {
            $memory->deleteWithRelations();

            return redirect('/')->with('success', 'Memory deleted.');
        })->name('memories.destroy');

        Route::get('settings', [SettingsController::class, 'show'])
            ->name('settings');

        // Member self-service
        Route::patch('settings/account', [AccountSettingsController::class, 'update'])
            ->name('settings.account.update');
        Route::patch('settings/password', [AccountSettingsController::class, 'updatePassword'])
            ->name('settings.password.update');
        Route::post('settings/log-out-other-devices', [AccountSettingsController::class, 'logOutOtherDevices'])
            ->name('settings.log-out-other-devices');

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

        // Admin-only
        Route::middleware('admin')->group(function () {
            Route::patch('settings/maintainer-email', [AppSettingsController::class, 'updateMaintainerEmail'])
                ->name('settings.maintainer-email.update');
            Route::patch('settings/two-factor-expiration', [AppSettingsController::class, 'updateTwoFactorExpiration'])
                ->name('settings.two-factor-expiration.update');

            Route::patch('settings/screenshots', [ScreenshotSettingsController::class, 'updateFlag'])
                ->name('settings.screenshots.update');
            Route::post('settings/screenshots/install', [ScreenshotSettingsController::class, 'install'])
                ->name('settings.screenshots.install');
            Route::post('settings/screenshots/uninstall', [ScreenshotSettingsController::class, 'uninstall'])
                ->name('settings.screenshots.uninstall');
            Route::get('settings/screenshots/status', [ScreenshotSettingsController::class, 'status'])
                ->name('settings.screenshots.status');

            Route::post('settings/users/invite', [UserManagementController::class, 'invite'])
                ->name('settings.users.invite');
            Route::post('settings/users/{user}/resend-invite', [UserManagementController::class, 'resendInvite'])
                ->name('settings.users.resend-invite');
            Route::patch('settings/users/{user}/role', [UserManagementController::class, 'updateRole'])
                ->name('settings.users.role.update');
            Route::post('settings/users/{user}/deactivate', [UserManagementController::class, 'deactivate'])
                ->name('settings.users.deactivate');
            Route::post('settings/users/{user}/reactivate', [UserManagementController::class, 'reactivate'])
                ->name('settings.users.reactivate');
        });
    });
});
