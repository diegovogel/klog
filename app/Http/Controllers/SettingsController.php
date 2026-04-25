<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuthenticatorService;
use App\Services\MaintainerResolverService;
use App\Services\ScreenshotFeatureService;
use App\Services\TwoFactorConfigService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        private AuthenticatorService $authenticatorService,
        private MaintainerResolverService $maintainer,
        private TwoFactorConfigService $twoFactorConfig,
        private ScreenshotFeatureService $screenshots,
    ) {}

    public function show(Request $request): View
    {
        $user = $request->user();
        $isAdmin = $user->isAdmin();

        $data = [
            'user' => $user,
            'isAdmin' => $isAdmin,
            'authenticatorAvailable' => $this->authenticatorService->isAvailable(),
        ];

        if ($isAdmin) {
            $data += [
                'maintainerEmail' => $this->maintainer->resolve(),
                'maintainerEmailFromEnv' => (string) config('klog.maintainer_email') !== '',
                'rememberDays' => $this->twoFactorConfig->rememberDays(),
                'screenshotsEnabled' => $this->screenshots->isEnabled(),
                'screenshotsInstalled' => $this->screenshots->isInstalled(),
                'screenshotsStatus' => $this->screenshots->status(),
                'users' => User::query()
                    ->with('invite')
                    ->orderBy('name')
                    ->get(),
            ];
        }

        return view('settings.index', $data);
    }
}
