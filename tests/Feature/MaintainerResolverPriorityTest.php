<?php

use App\Models\AppSetting;
use App\Services\MaintainerResolverService;

it('does not let auto-discovered fallback override a configured env value', function () {
    config()->set('klog.maintainer_email', 'env@example.com');

    $service = app(MaintainerResolverService::class);

    // Simulate a fallback discovery being saved (e.g. EmailLogHandler caching).
    $service->saveDiscoveredEmail('fallback@example.com');

    expect($service->resolve())->toBe('env@example.com');
});

it('uses auto-discovered fallback when neither admin-configured nor env value exists', function () {
    config()->set('klog.maintainer_email', null);

    $service = app(MaintainerResolverService::class);
    $service->saveDiscoveredEmail('discovered@example.com');

    expect($service->resolve())->toBe('discovered@example.com');
});

it('still prefers admin-configured value over both env and auto-discovered', function () {
    config()->set('klog.maintainer_email', 'env@example.com');

    $service = app(MaintainerResolverService::class);
    $service->save('admin@example.com');
    $service->saveDiscoveredEmail('fallback@example.com');

    expect($service->resolve())->toBe('admin@example.com');
});

it('writes the auto-discovered email to a different key than the admin-configured one', function () {
    $service = app(MaintainerResolverService::class);
    $service->saveDiscoveredEmail('discovered@example.com');

    expect(AppSetting::getValue('maintainer_email'))->toBeNull();
    expect(AppSetting::getValue('maintainer_email_autodiscovered'))->toBe('discovered@example.com');
});
