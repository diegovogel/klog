<section class="settings-section">
    <h2 class="settings-section__title">Account</h2>

    @php($accountFailed = $errors->account->any())

    <form method="POST" action="{{ route('settings.account.update') }}">
        @csrf
        @method('PATCH')

        <div class="form-group">
            <label for="account-name" class="form-label">Name</label>
            <input id="account-name" name="name" type="text" class="form-input"
                   value="{{ $accountFailed ? old('name') : $user->name }}" required>
            @error('name', 'account')
            <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="form-group">
            <label for="account-email" class="form-label">Email</label>
            <input id="account-email" name="email" type="email" class="form-input"
                   value="{{ $accountFailed ? old('email') : $user->email }}" required>
            @error('email', 'account')
            <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="form-group">
            <label for="account-current-password" class="form-label">Current password
                <small class="form-hint">(required if you change your email)</small>
            </label>
            <input id="account-current-password" name="current_password" type="password"
                   class="form-input" autocomplete="current-password">
            @error('current_password', 'account')
            <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="btn btn--primary">Save account</button>
    </form>

    <hr class="settings-section__divider">

    <h3 class="settings-section__subtitle">Change password</h3>

    <form method="POST" action="{{ route('settings.password.update') }}">
        @csrf
        @method('PATCH')

        <div class="form-group">
            <label for="password-current" class="form-label">Current password</label>
            <input id="password-current" name="current_password" type="password"
                   class="form-input" autocomplete="current-password" required>
            @error('current_password', 'password')
            <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="form-group">
            <label for="password-new" class="form-label">New password</label>
            <input id="password-new" name="password" type="password"
                   class="form-input" autocomplete="new-password" required>
            @error('password', 'password')
            <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="form-group">
            <label for="password-confirm" class="form-label">Confirm new password</label>
            <input id="password-confirm" name="password_confirmation" type="password"
                   class="form-input" autocomplete="new-password" required>
        </div>

        <button type="submit" class="btn btn--primary">Update password</button>
    </form>

    <hr class="settings-section__divider">

    <h3 class="settings-section__subtitle">Other devices</h3>
    <p>Sign out every other browser or device that's still logged in to your account.</p>

    <form method="POST" action="{{ route('settings.log-out-other-devices') }}">
        @csrf

        <div class="form-group">
            <label for="logout-others-password" class="form-label">Current password</label>
            <input id="logout-others-password" name="password" type="password"
                   class="form-input" autocomplete="current-password" required>
            @error('password', 'logout_others')
            <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="btn btn--secondary">Log out other devices</button>
    </form>
</section>
