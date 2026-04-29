<?php

namespace App\Http\Controllers;

use App\Jobs\InstallScreenshotsJob;
use App\Jobs\UninstallScreenshotsJob;
use App\Services\ScreenshotFeatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ScreenshotSettingsController extends Controller
{
    public function __construct(private ScreenshotFeatureService $feature) {}

    public function updateFlag(Request $request): RedirectResponse
    {
        $enabled = $request->boolean('enabled');
        $this->feature->setEnabled($enabled);

        // Enabling implicitly requests the toolchain. If it's missing, kick
        // off an install so the admin doesn't have to click a second button.
        if ($enabled && ! $this->feature->isInstalled() && $this->feature->tryReserve('install')) {
            InstallScreenshotsJob::dispatch();

            return redirect()->route('settings')
                ->with('success', 'Screenshots enabled. Installing the toolchain now — this may take a minute.');
        }

        return redirect()->route('settings')
            ->with('success', 'Screenshots '.($enabled ? 'enabled' : 'disabled').'.');
    }

    public function install(): RedirectResponse
    {
        if (! $this->feature->tryReserve('install')) {
            return redirect()->route('settings')
                ->withErrors(['screenshots' => 'A screenshot operation is already in progress.']);
        }

        InstallScreenshotsJob::dispatch();

        return redirect()->route('settings')->with('success', 'Installing screenshot packages. This may take a minute.');
    }

    public function uninstall(): RedirectResponse
    {
        if (! $this->feature->tryReserve('uninstall')) {
            return redirect()->route('settings')
                ->withErrors(['screenshots' => 'A screenshot operation is already in progress.']);
        }

        UninstallScreenshotsJob::dispatch();

        return redirect()->route('settings')->with('success', 'Removing screenshot packages.');
    }

    public function status(): JsonResponse
    {
        return response()->json([
            'status' => $this->feature->status(),
            'installed' => $this->feature->isInstalled(),
            'enabled' => $this->feature->isEnabled(),
        ]);
    }
}
