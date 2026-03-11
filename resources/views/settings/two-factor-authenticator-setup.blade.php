<x-layouts.app>
    <x-slot:title>Set Up Authenticator - {{ config('app.name', 'Klog') }}</x-slot:title>

    <h1 class="page-title">Set Up Authenticator App</h1>

    <p>Scan the QR code below with your authenticator app (e.g. Google Authenticator, Authy, 1Password).</p>

    <div class="qr-code-wrapper">
        {!! $qrCodeSvg !!}
    </div>

    <details>
        <summary>Can't scan? Enter the key manually</summary>
        <pre class="manual-key">{{ $secret }}</pre>
    </details>

    <form method="POST" action="{{ route('two-factor.authenticator.confirm') }}">
        @csrf

        <div class="form-group">
            <label for="code" class="form-label">Enter the 6-digit code from your app to confirm</label>
            <input
                id="code"
                name="code"
                type="text"
                class="form-input"
                inputmode="numeric"
                autocomplete="one-time-code"
                maxlength="6"
                required
                autofocus
            >
            @error('code')
            <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="btn btn--primary">Confirm &amp; Enable</button>
    </form>

    <p class="back-link">
        <a href="{{ route('settings') }}">&larr; Cancel</a>
    </p>
</x-layouts.app>
