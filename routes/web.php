<?php

use App\Http\Controllers\Auth\LoginController;
use App\Models\Memory;
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
});
