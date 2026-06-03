<?php

use App\Http\Controllers\AccountSettingsController;
use App\Http\Controllers\AppSettingsController;
use App\Http\Controllers\Auth\DemoLoginController;
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

    // One-click login for the public demo. The controller 404s when the app
    // is not running in demo mode, so this route is inert in production.
    Route::post('demo/login', [DemoLoginController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('demo.login');

    Route::get('invites/{token}', [InviteController::class, 'show'])->name('invites.show');
    Route::post('invites/{token}', [InviteController::class, 'accept'])->name('invites.accept');
});

// The demo-writes / demo-chunks rate limiters (see AppServiceProvider) bound
// abuse on the public demo and are unlimited in production.
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

        Route::post('uploads/init', [UploadController::class, 'init'])
            ->middleware('throttle:demo-writes')
            ->name('uploads.init');
        Route::post('uploads/{uploadSession}/chunk', [UploadController::class, 'chunk'])
            ->middleware('throttle:demo-chunks')
            ->name('uploads.chunk');
        Route::delete('uploads/{uploadSession}', [UploadController::class, 'cancel'])
            ->middleware('throttle:demo-writes')
            ->name('uploads.cancel');

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
        })->middleware('throttle:demo-writes')->name('memories.store');

        Route::delete('memories/{memory}', function (Memory $memory) {
            $memory->deleteWithRelations();

            return redirect('/')->with('success', 'Memory deleted.');
        })->name('memories.destroy');

        Route::get('settings', [SettingsController::class, 'show'])
            ->name('settings');
        Route::get('settings/two-factor/authenticator/setup', [TwoFactorSettingsController::class, 'showAuthenticatorSetup'])
            ->name('two-factor.authenticator.setup');

        // Member self-service. Every mutation is blocked on the shared demo
        // account: the login page publishes the demo password, so otherwise any
        // visitor could change the credentials, enable 2FA, or cycle the session
        // and lock everyone out until the next reset. block-in-demo is a no-op
        // in production, so the normal single-user flows are unaffected.
        Route::middleware('block-in-demo')->group(function () {
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
            Route::post('settings/two-factor/authenticator/confirm', [TwoFactorSettingsController::class, 'confirmAuthenticator'])
                ->name('two-factor.authenticator.confirm');
        });

        // Admin-only
        Route::middleware('admin')->group(function () {
            Route::get('settings/screenshots/status', [ScreenshotSettingsController::class, 'status'])
                ->name('settings.screenshots.status');

            // Every admin mutation is unsafe on a public demo: maintainer-email
            // reroutes error notifications, the screenshots toggle/install shell
            // out to composer/npm, invites send mail, and user-state changes can
            // lock out the shared demo account. Grouping under block-in-demo (a
            // no-op in production) keeps new demo-unsafe admin routes guarded by
            // default rather than relying on each one remembering the middleware.
            Route::middleware('block-in-demo')->group(function () {
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
});
