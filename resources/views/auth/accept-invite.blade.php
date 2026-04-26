<x-layouts.public>
    <x-slot:title>Accept invitation - {{ config('app.name', 'Klog') }}</x-slot:title>

    <div class="login-page">
        <div class="login-card">
            <div class="login-card__header">
                <h1 class="login-card__logo">{{ config('app.name', 'Klog') }}</h1>
                <p class="login-card__tagline">You've been invited. Set your name and password to finish.</p>
            </div>

            <form method="POST" action="{{ route('invites.accept', ['token' => $token]) }}">
                @csrf

                <div class="form-group">
                    <label for="invite-email" class="form-label">Email</label>
                    <input id="invite-email" type="email" class="form-input"
                           value="{{ $invite->user->email }}" disabled readonly>
                </div>

                <div class="form-group">
                    <label for="invite-name" class="form-label">Name</label>
                    <input id="invite-name" name="name" type="text" class="form-input"
                           value="{{ old('name', $invite->user->name) }}" required autofocus>
                    @error('name')
                    <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="invite-password" class="form-label">Password</label>
                    <input id="invite-password" name="password" type="password" class="form-input"
                           autocomplete="new-password" required>
                    @error('password')
                    <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="invite-password-confirm" class="form-label">Confirm password</label>
                    <input id="invite-password-confirm" name="password_confirmation" type="password"
                           class="form-input" autocomplete="new-password" required>
                </div>

                <button type="submit" class="btn btn--primary btn--block">Accept &amp; log in</button>
            </form>
        </div>
    </div>
</x-layouts.public>
