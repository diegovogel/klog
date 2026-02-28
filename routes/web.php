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
});
