<x-layouts.app>
    <x-slot:title>Two-Factor Authentication - {{ config('app.name', 'Klog') }}</x-slot:title>
    <x-slot:pageTitle>Two-Factor Authentication</x-slot:pageTitle>

    @if(session('success'))
        <p style="color: green; margin-bottom: 1rem;">{{ session('success') }}</p>
    @endif

    @if(session('recovery_codes'))
        <div style="background: #fef3c7; border: 1px solid #f59e0b; padding: 16px; border-radius: 4px; margin-bottom: 1.5rem;">
            <p><strong>Save your recovery codes</strong></p>
            <p style="font-size: 0.875rem;">Store these codes in a safe place. Each code can only be used once. If you lose access to your authentication method, you can use one of these codes to sign in.</p>
            <pre style="background: #fff; padding: 12px; border-radius: 4px; margin-top: 8px; font-size: 0.875rem;">@foreach(session('recovery_codes') as $code){{ $code }}
@endforeach</pre>
        </div>
    @endif

    <div style="margin-bottom: 2rem;">
        @if($user->hasTwoFactorEnabled())
            <p>
                <strong>Status:</strong> Enabled
                ({{ $user->two_factor_method === \App\Enums\TwoFactorMethod::EMAIL ? 'Email' : 'Authenticator app' }})
            </p>

            <div style="margin-top: 1rem; display: flex; gap: 1rem; flex-wrap: wrap;">
                <form method="POST" action="{{ route('two-factor.disable') }}">
                    @csrf
                    <label for="disable-password">Password</label>
                    <input id="disable-password" name="password" type="password" required>
                    @error('password')
                    <p>{{ $message }}</p>
                    @enderror
                    <button type="submit">Disable Two-Factor</button>
                </form>

                <form method="POST" action="{{ route('two-factor.recovery-codes') }}">
                    @csrf
                    <label for="regen-password">Password</label>
                    <input id="regen-password" name="password" type="password" required>
                    <button type="submit">Regenerate Recovery Codes</button>
                </form>
            </div>
        @else
            <p><strong>Status:</strong> Disabled</p>

            <div style="margin-top: 1rem;">
                <form method="POST" action="{{ route('two-factor.enable') }}">
                    @csrf

                    <div>
                        <label for="enable-password">Password</label>
                        <input id="enable-password" name="password" type="password" required>
                        @error('password')
                        <p>{{ $message }}</p>
                        @enderror
                        @error('method')
                        <p>{{ $message }}</p>
                        @enderror
                    </div>

                    <div style="margin-top: 1rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <button type="submit" name="method" value="email">Enable with Email</button>
                        @if($authenticatorAvailable)
                            <button type="submit" name="method" value="authenticator">Enable with Authenticator App</button>
                        @endif
                    </div>
                </form>
            </div>
        @endif
    </div>

    <p style="font-size: 0.875rem;">
        <a href="/">&larr; Back</a>
    </p>
</x-layouts.app>
