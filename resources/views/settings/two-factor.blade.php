<x-layouts.app>
    <x-slot:title>Two-Factor Authentication - {{ config('app.name', 'Klog') }}</x-slot:title>

    <h1 class="page-title">Two-Factor Authentication</h1>

    @if(session('success'))
        <div class="alert alert--success">{{ session('success') }}</div>
    @endif

    @if(session('recovery_codes'))
        <div class="alert alert--warning">
            <p><strong>Save your recovery codes</strong></p>
            <p>Store these codes in a safe place. Each code can only be used once. If you lose access to your authentication method, you can use one of these codes to sign in.</p>
            <pre class="recovery-codes">@foreach(session('recovery_codes') as $code){{ $code }}
@endforeach</pre>
        </div>
    @endif

    <div class="settings-section">
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
    </div>

    <p class="back-link">
        <a href="/">&larr; Back</a>
    </p>
</x-layouts.app>
