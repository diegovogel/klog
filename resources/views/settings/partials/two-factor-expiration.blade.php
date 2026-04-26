<section class="settings-section">
    <h2 class="settings-section__title">Two-factor remember duration</h2>
    <p>How long a device stays trusted after a successful 2FA challenge before it must verify again. Existing trusted devices keep their original expiration.</p>

    <form method="POST" action="{{ route('settings.two-factor-expiration.update') }}">
        @csrf
        @method('PATCH')

        <div class="form-group">
            <label for="remember-days" class="form-label">Days</label>
            <input id="remember-days" name="remember_days" type="number"
                   class="form-input form-input--narrow"
                   min="{{ \App\Services\TwoFactorConfigService::MIN_DAYS }}"
                   max="{{ \App\Services\TwoFactorConfigService::MAX_DAYS }}"
                   value="{{ old('remember_days', $rememberDays) }}" required>
            @error('remember_days')
            <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="btn btn--primary">Save</button>
    </form>
</section>
