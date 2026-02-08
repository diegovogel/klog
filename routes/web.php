<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Requests\StoreMemoryRequest;
use App\Models\Memory;
use App\Services\HtmlSanitizer;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/', function () {
        return view('memory-feed', [
            'memories' => Memory::latest('captured_at')->paginate(20),
        ]);
    });

    Route::get('memories/create', function () {
        return view('memories.create');
    })->name('memories.create');

    Route::post('memories', function (StoreMemoryRequest $request, HtmlSanitizer $sanitizer) {
        $memory = Memory::create([
            'title' => $request->validated('title'),
            'content' => $sanitizer->sanitize($request->validated('content')),
            'captured_at' => now(),
        ]);

        return redirect('/')->with('success', 'Memory saved.');
    })->name('memories.store');
});
