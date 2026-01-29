<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in — {{ config('app.name', 'Klog') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f9f9f9;
            padding: 1rem;
        }

        @media (prefers-color-scheme: dark) {
            body { background: #0a0a0a; color: #ededed; }
        }

        .login-container { width: 100%; max-width: 24rem; }

        .login-title {
            text-align: center;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 2rem;
            color: #111;
        }

        @media (prefers-color-scheme: dark) {
            .login-title { color: #ededed; }
        }

        .form-group { margin-bottom: 1.25rem; }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
            color: #374151;
        }

        @media (prefers-color-scheme: dark) {
            .form-label { color: #d1d5db; }
        }

        .form-input {
            display: block;
            width: 100%;
            padding: 0.5rem 0.75rem;
            font-size: 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            background: #fff;
            color: #111;
        }

        .form-input:focus {
            outline: none;
            border-color: #6b7280;
            box-shadow: 0 0 0 1px #6b7280;
        }

        @media (prefers-color-scheme: dark) {
            .form-input {
                background: #1a1a1a;
                border-color: #3f3f46;
                color: #ededed;
            }
            .form-input:focus {
                border-color: #a1a1aa;
                box-shadow: 0 0 0 1px #a1a1aa;
            }
        }

        .form-error {
            margin-top: 0.25rem;
            font-size: 0.875rem;
            color: #dc2626;
        }

        @media (prefers-color-scheme: dark) {
            .form-error { color: #f87171; }
        }

        .btn-submit {
            width: 100%;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: #fff;
            background: #111;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
        }

        .btn-submit:hover { background: #333; }

        @media (prefers-color-scheme: dark) {
            .btn-submit {
                background: #ededed;
                color: #111;
            }
            .btn-submit:hover { background: #fff; }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1 class="login-title">{{ config('app.name', 'Klog') }}</h1>

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="form-group">
                <label for="email" class="form-label">Email</label>
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
                <label for="password" class="form-label">Password</label>
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

            <button type="submit" class="btn-submit">Log in</button>
        </form>
    </div>
</body>
</html>
