<?php

use App\Models\AppSetting;

describe('AppSetting', function () {
    it('stores and retrieves a setting value', function () {
        AppSetting::setValue('test_key', 'test_value');

        expect(AppSetting::getValue('test_key'))->toBe('test_value');
    });

    it('returns default when setting does not exist', function () {
        expect(AppSetting::getValue('nonexistent', 'fallback'))->toBe('fallback');
    });

    it('returns null when setting does not exist and no default given', function () {
        expect(AppSetting::getValue('nonexistent'))->toBeNull();
    });

    it('overwrites an existing setting value', function () {
        AppSetting::setValue('overwrite_key', 'original');
        AppSetting::setValue('overwrite_key', 'updated');

        expect(AppSetting::getValue('overwrite_key'))->toBe('updated');
        expect(AppSetting::where('key', 'overwrite_key')->count())->toBe(1);
    });

    it('stores null as a value', function () {
        AppSetting::setValue('nullable_key', null);

        expect(AppSetting::where('key', 'nullable_key')->exists())->toBeTrue();
        expect(AppSetting::getValue('nullable_key', 'default'))->toBe('default');
    });
});
