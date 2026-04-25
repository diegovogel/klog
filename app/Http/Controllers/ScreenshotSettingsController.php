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

        return redirect()->route('settings')
            ->with('success', 'Screenshots '.($enabled ? 'enabled' : 'disabled').'.');
    }

    public function install(): RedirectResponse
    {
        $this->feature->markStatus('queued', 'Waiting for worker…', 'install');
        InstallScreenshotsJob::dispatch();

        return redirect()->route('settings')->with('success', 'Installing screenshot packages. This may take a minute.');
    }

    public function uninstall(): RedirectResponse
    {
        $this->feature->markStatus('queued', 'Waiting for worker…', 'uninstall');
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
