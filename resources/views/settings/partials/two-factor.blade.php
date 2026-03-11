<section class="settings-section">
    <h2 class="settings-section__title">Two-Factor Authentication</h2>

    @if($user->hasTwoFactorEnabled())
        <div class="settings-section__status">
            <span class="settings-section__status-badge settings-section__status-badge--enabled">Enabled</span>
            {{ $user->two_factor_method === \App\Enums\TwoFactorMethod::EMAIL ? 'Email' : 'Authenticator app' }}
        </div>

        <div class="settings-section__methods">
            <form method="POST" action="{{ route('two-factor.disable') }}">
                @csrf
                <div class="form-group">
                    <label for="disable-password" class="form-label">Password</label>
                    <input id="disable-password" name="password" type="password" class="form-input" required>
                    @error('password')
                    <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="btn btn--danger">Disable Two-Factor</button>
            </form>

            <div class="divider"></div>

            <form method="POST" action="{{ route('two-factor.recovery-codes') }}">
                @csrf
                <div class="form-group">
                    <label for="regen-password" class="form-label">Password</label>
                    <input id="regen-password" name="password" type="password" class="form-input" required>
                </div>
                <button type="submit" class="btn btn--secondary">Regenerate Recovery Codes</button>
            </form>
        </div>
    @else
        <div class="settings-section__status">
            <span class="settings-section__status-badge">Disabled</span>
        </div>

        <form method="POST" action="{{ route('two-factor.enable') }}">
            @csrf

            <div class="form-group">
                <label for="enable-password" class="form-label">Password</label>
                <input id="enable-password" name="password" type="password" class="form-input" required>
                @error('password')
                <p class="form-error">{{ $message }}</p>
                @enderror
                @error('method')
                <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-actions">
                <button type="submit" name="method" value="email" class="btn btn--primary">Enable with Email</button>
                @if($authenticatorAvailable)
                    <button type="submit" name="method" value="authenticator" class="btn btn--secondary">Enable with Authenticator App</button>
                @endif
            </div>
        </form>
    @endif
</section>
