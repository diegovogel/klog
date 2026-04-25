<?php

namespace App\Http\Controllers;

use App\Http\Requests\Settings\UpdateMaintainerEmailRequest;
use App\Http\Requests\Settings\UpdateTwoFactorExpirationRequest;
use App\Services\MaintainerResolverService;
use App\Services\TwoFactorConfigService;
use Illuminate\Http\RedirectResponse;

class AppSettingsController extends Controller
{
    public function __construct(
        private MaintainerResolverService $maintainer,
        private TwoFactorConfigService $twoFactorConfig,
    ) {}

    public function updateMaintainerEmail(UpdateMaintainerEmailRequest $request): RedirectResponse
    {
        $this->maintainer->save($request->validated('maintainer_email'));

        return redirect()->route('settings')->with('success', 'Maintainer email updated.');
    }

    public function updateTwoFactorExpiration(UpdateTwoFactorExpirationRequest $request): RedirectResponse
    {
        $this->twoFactorConfig->saveRememberDays((int) $request->validated('remember_days'));

        return redirect()->route('settings')->with('success', 'Two-factor expiration updated.');
    }
}
