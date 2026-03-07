<x-layouts.app>
    <x-slot:title>Set Up Authenticator - {{ config('app.name', 'Klog') }}</x-slot:title>
    <x-slot:pageTitle>Set Up Authenticator App</x-slot:pageTitle>

    <p>Scan the QR code below with your authenticator app (e.g. Google Authenticator, Authy, 1Password).</p>

    <div style="margin: 1.5rem 0; display: inline-block; background: #fff; padding: 16px; border-radius: 4px;">
        {!! $qrCodeSvg !!}
    </div>

    <details style="margin-bottom: 1.5rem;">
        <summary style="cursor: pointer; font-size: 0.875rem;">Can't scan? Enter the key manually</summary>
        <pre style="background: #f3f4f6; padding: 12px; border-radius: 4px; margin-top: 8px; letter-spacing: 2px; font-size: 0.875rem;">{{ $secret }}</pre>
    </details>

    <form method="POST" action="{{ route('two-factor.authenticator.confirm') }}">
        @csrf

        <div>
            <label for="code">Enter the 6-digit code from your app to confirm</label>
            <input
                id="code"
                name="code"
                type="text"
                inputmode="numeric"
                autocomplete="one-time-code"
                maxlength="6"
                required
                autofocus
            >
            @error('code')
            <p>{{ $message }}</p>
            @enderror
        </div>

        <button type="submit">Confirm &amp; Enable</button>
    </form>

    <p style="font-size: 0.875rem; margin-top: 1rem;">
        <a href="{{ route('two-factor.settings') }}">&larr; Cancel</a>
    </p>
</x-layouts.app>
