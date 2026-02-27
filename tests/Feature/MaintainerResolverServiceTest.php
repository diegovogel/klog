<?php

use App\Models\AppSetting;
use App\Models\User;
use App\Services\MaintainerResolverService;

describe('MaintainerResolverService', function () {
    beforeEach(function () {
        $this->service = new MaintainerResolverService;
    });

    describe('resolve', function () {
        it('returns MAINTAINER_EMAIL when configured', function () {
            config()->set('klog.maintainer_email', 'admin@example.com');

            expect($this->service->resolve())->toBe('admin@example.com');
        });

        it('returns stored app_setting when no env var', function () {
            config()->set('klog.maintainer_email', null);
            AppSetting::setValue('maintainer_email', 'stored@example.com');

            expect($this->service->resolve())->toBe('stored@example.com');
        });

        it('returns null when neither env nor setting exists', function () {
            config()->set('klog.maintainer_email', null);

            expect($this->service->resolve())->toBeNull();
        });

        it('prefers env var over stored setting', function () {
            config()->set('klog.maintainer_email', 'env@example.com');
            AppSetting::setValue('maintainer_email', 'stored@example.com');

            expect($this->service->resolve())->toBe('env@example.com');
        });

        it('treats empty string env var as unset', function () {
            config()->set('klog.maintainer_email', '');

            expect($this->service->resolve())->toBeNull();
        });
    });

    describe('getUserEmailsInOrder', function () {
        it('returns user emails ordered by id', function () {
            User::factory()->create(['email' => 'first@example.com']);
            User::factory()->create(['email' => 'second@example.com']);
            User::factory()->create(['email' => 'third@example.com']);

            expect($this->service->getUserEmailsInOrder())
                ->toBe(['first@example.com', 'second@example.com', 'third@example.com']);
        });

        it('returns empty array when no users exist', function () {
            expect($this->service->getUserEmailsInOrder())->toBe([]);
        });
    });

    describe('saveDiscoveredEmail', function () {
        it('stores the email in app_settings', function () {
            $this->service->saveDiscoveredEmail('discovered@example.com');

            expect(AppSetting::getValue('maintainer_email'))->toBe('discovered@example.com');
        });

        it('overwrites a previously stored email', function () {
            $this->service->saveDiscoveredEmail('old@example.com');
            $this->service->saveDiscoveredEmail('new@example.com');

            expect(AppSetting::getValue('maintainer_email'))->toBe('new@example.com');
        });
    });
});
