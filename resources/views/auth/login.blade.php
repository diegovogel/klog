<x-layouts.public>
    <x-slot:title>Log in - {{ config('app.name', 'Klog') }}</x-slot:title>

    <div class="login-wrapper">
        <h1 class="login-title">{{ config('app.name', 'Klog') }}</h1>

        <form method="POST"
              action="{{ route('login') }}">
            @csrf

            <div class="form-group">
                <label for="email"
                       class="form-label">Email</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    autocomplete="email"
                    required
                    autofocus
                    value="{{ old('email') }}"
                    class="form-input"
                >
                @error('email')
                <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-group">
                <label for="password"
                       class="form-label">Password</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required
                    class="form-input"
                >
                @error('password')
                <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                    class="btn-submit">Log in
            </button>
        </form>
    </div>
</x-layouts.public>
