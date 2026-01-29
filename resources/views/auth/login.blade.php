<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">
    <title>Log in — {{ config('app.name', 'Klog') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="login-container">
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
</body>
</html>
