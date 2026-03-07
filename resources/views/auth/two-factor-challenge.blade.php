<x-layouts.public>
    <x-slot:title>Two-Factor Authentication - {{ config('app.name', 'Klog') }}</x-slot:title>

    <div class="login-wrapper">
        <h1 class="login-title">{{ config('app.name', 'Klog') }}</h1>

        <p id="instructions">
            @if($method === \App\Enums\TwoFactorMethod::EMAIL)
                A verification code has been sent to your email.
            @else
                Enter the code from your authenticator app.
            @endif
        </p>

        @if(session('status'))
            <p style="color: green;">{{ session('status') }}</p>
        @endif

        <form method="POST"
              action="{{ route('two-factor.verify') }}"
              id="challenge-form">
            @csrf

            <div id="code-fields">
                <div>
                    <label for="code" id="code-label">Verification code</label>
                    <input
                        id="code"
                        name="code"
                        type="text"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        required
                        autofocus
                    >
                    @error('code')
                    <p>{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <input type="hidden" name="recovery" id="recovery-input" value="0">

            <div>
                <label>
                    <input type="checkbox" name="remember" value="1">
                    Remember this device for {{ config('klog.two_factor.remember_days', 30) }} days
                </label>
            </div>

            <button type="submit">Verify</button>
        </form>

        <div style="margin-top: 1rem; font-size: 0.875rem;">
            @if($method === \App\Enums\TwoFactorMethod::EMAIL)
                <form method="POST" action="{{ route('two-factor.resend') }}" style="display: inline;">
                    @csrf
                    <button type="submit" style="background: none; border: none; color: inherit; text-decoration: underline; cursor: pointer; padding: 0; font-size: inherit;">
                        Resend code
                    </button>
                </form>
                &middot;
            @endif
            <a href="#" id="toggle-recovery">Use a recovery code</a>
        </div>

        <form method="POST" action="{{ route('logout') }}" style="margin-top: 0.5rem; font-size: 0.875rem;">
            @csrf
            <button type="submit" style="background: none; border: none; color: inherit; text-decoration: underline; cursor: pointer; padding: 0; font-size: inherit;">
                Log out
            </button>
        </form>
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
