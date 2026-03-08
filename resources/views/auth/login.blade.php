<x-layouts.public>
    <x-slot:title>Log in - {{ config('app.name', 'Klog') }}</x-slot:title>

    <div class="login-page">
        <div class="login-card">
            <div class="login-card__header">
                <h1 class="login-card__logo">{{ config('app.name', 'Klog') }}</h1>
                <p class="login-card__tagline">Your personal memory keeper</p>
            </div>

            <form method="POST"
                  action="{{ route('login') }}">
                @csrf

                <div class="form-group">
                    <label for="email" class="form-label">Email</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        class="form-input"
                        autocomplete="email"
                        required
                        autofocus
                        value="{{ old('email') }}"
                    >
                    @error('email')
                    <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        class="form-input"
                        autocomplete="current-password"
                        required
                    >
                    @error('password')
                    <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <button type="submit" class="btn btn--primary btn--block">Log in</button>
            </form>
        </div>
    </div>
</x-layouts.public>
