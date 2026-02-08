<x-layouts.public>
    <x-slot:title>Log in - {{ config('app.name', 'Klog') }}</x-slot:title>

    <div class="login-wrapper">
        <h1 class="login-title">{{ config('app.name', 'Klog') }}</h1>

        <form method="POST"
              action="{{ route('login') }}">
            @csrf

            <div>
                <label for="email">Email</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    autocomplete="email"
                    required
                    autofocus
                    value="{{ old('email') }}"
                >
                @error('email')
                <p>{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password">Password</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required
                >
                @error('password')
                <p>{{ $message }}</p>
                @enderror
            </div>

            <button type="submit">Log in
            </button>
        </form>
    </div>
</x-layouts.public>
