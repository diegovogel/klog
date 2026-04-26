<x-layouts.public>
    <x-slot:title>Two-Factor Authentication - {{ config('app.name', 'Klog') }}</x-slot:title>

    <div class="login-page">
        <div class="login-card">
            <div class="login-card__header">
                <h1 class="login-card__logo">{{ config('app.name', 'Klog') }}</h1>
                <p class="login-card__tagline" id="instructions">
                    @if($method === \App\Enums\TwoFactorMethod::EMAIL)
                        A verification code has been sent to your email.
                    @else
                        Enter the code from your authenticator app.
                    @endif
                </p>
            </div>

            @if(session('status'))
                <div class="alert alert--success">{{ session('status') }}</div>
            @endif

            <form method="POST"
                  action="{{ route('two-factor.verify') }}"
                  id="challenge-form">
                @csrf

                <div class="form-group" id="code-fields">
                    <label for="code" class="form-label" id="code-label">Verification code</label>
                    <input
                        id="code"
                        name="code"
                        type="text"
                        class="form-input"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        required
                        autofocus
                    >
                    @error('code')
                    <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <input type="hidden" name="recovery" id="recovery-input" value="0">

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember" value="1">
                        Remember this device for {{ $rememberDays }} days
                    </label>
                </div>

                <button type="submit" class="btn btn--primary btn--block">Verify</button>
            </form>

            <div class="link-row">
                @if($method === \App\Enums\TwoFactorMethod::EMAIL)
                    <form method="POST" action="{{ route('two-factor.resend') }}" style="display: inline;">
                        @csrf
                        <button type="submit" class="btn btn--ghost btn--sm">Resend code</button>
                    </form>
                    &middot;
                @endif
                <a href="#" id="toggle-recovery" class="btn btn--ghost btn--sm">Use a recovery code</a>
            </div>

            <div class="link-row">
                <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn--ghost btn--sm">Log out</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('toggle-recovery').addEventListener('click', function (e) {
            e.preventDefault();
            const recoveryInput = document.getElementById('recovery-input');
            const codeLabel = document.getElementById('code-label');
            const codeInput = document.getElementById('code');
            const instructions = document.getElementById('instructions');

            if (recoveryInput.value === '0') {
                recoveryInput.value = '1';
                codeLabel.textContent = 'Recovery code';
                codeInput.placeholder = 'XXXXX-XXXXX';
                codeInput.inputMode = 'text';
                codeInput.autocomplete = 'off';
                instructions.textContent = 'Enter one of your recovery codes.';
                this.textContent = 'Use verification code';
            } else {
                recoveryInput.value = '0';
                codeLabel.textContent = 'Verification code';
                codeInput.placeholder = '';
                codeInput.inputMode = 'numeric';
                codeInput.autocomplete = 'one-time-code';
                instructions.textContent = @if($method === \App\Enums\TwoFactorMethod::EMAIL)
                    'A verification code has been sent to your email.';
                @else
                    'Enter the code from your authenticator app.';
                @endif
                this.textContent = 'Use a recovery code';
            }

            codeInput.value = '';
            codeInput.focus();
        });
    </script>
</x-layouts.public>
