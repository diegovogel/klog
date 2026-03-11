<?php

namespace App\Http\Controllers;

use App\Services\AuthenticatorService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        private AuthenticatorService $authenticatorService,
    ) {}

    public function show(Request $request): View
    {
        return view('settings.index', [
            'user' => $request->user(),
            'authenticatorAvailable' => $this->authenticatorService->isAvailable(),
        ]);
    }
}
