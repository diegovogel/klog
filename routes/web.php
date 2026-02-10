<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\MediaController;
use App\Http\Requests\StoreMemoryRequest;
use App\Models\Memory;
use App\Services\HtmlSanitizer;
use App\Services\MediaStorageService;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('media/{filename}', [MediaController::class, 'show'])->name('media.show');

    Route::get('/', function () {
        return view('memory-feed', [
            'memories' => Memory::latest('captured_at')->paginate(20),
        ]);
    });

    Route::get('memories/create', function () {
        return view('memories.create');
    })->name('memories.create');

    Route::post('memories', function (StoreMemoryRequest $request, HtmlSanitizer $sanitizer, MediaStorageService $mediaStorage) {
        $memory = Memory::create([
            'title' => $request->validated('title'),
            'content' => $sanitizer->sanitize($request->validated('content')),
            'captured_at' => now(),
        ]);

        if ($request->hasFile('media')) {
            $mediaStorage->storeForMemory($memory, $request->file('media'));
        }

        return redirect('/')->with('success', 'Memory saved.');
    })->name('memories.store');
});
