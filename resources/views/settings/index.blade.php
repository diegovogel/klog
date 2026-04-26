<x-layouts.app>
    <x-slot:title>Settings - {{ config('app.name', 'Klog') }}</x-slot:title>

    <div class="page-header">
        <h1 class="page-title">Settings</h1>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn--secondary">Log out</button>
        </form>
    </div>

    @if(session('success'))
        <div class="alert alert--success">{{ session('success') }}</div>
    @endif

    @php($adminErrorKeys = ['invite', 'role', 'deactivate', 'screenshots'])
    @if(collect($adminErrorKeys)->contains(fn ($key) => $errors->has($key)))
        <div class="alert alert--error">
            <ul>
                @foreach($adminErrorKeys as $key)
                    @foreach($errors->get($key) as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                @endforeach
            </ul>
        </div>
    @endif

    @if(session('recovery_codes'))
        <div class="alert alert--warning">
            <p><strong>Save your recovery codes</strong></p>
            <p>Store these codes in a safe place. Each code can only be used once. If you lose access to your authentication method, you can use one of these codes to sign in.</p>
            <pre class="recovery-codes">@foreach(session('recovery_codes') as $code){{ $code }}
@endforeach</pre>
        </div>
    @endif

    @include('settings.partials.account', ['user' => $user])

    @include('settings.partials.two-factor', [
        'user' => $user,
        'authenticatorAvailable' => $authenticatorAvailable,
    ])

    @if($isAdmin)
        @include('settings.partials.maintainer-email', [
            'maintainerEmail' => $maintainerEmail,
            'maintainerEmailFromEnv' => $maintainerEmailFromEnv,
        ])

        @include('settings.partials.two-factor-expiration', [
            'rememberDays' => $rememberDays,
        ])

        @include('settings.partials.screenshots', [
            'enabled' => $screenshotsEnabled,
            'installed' => $screenshotsInstalled,
            'status' => $screenshotsStatus,
        ])

        @include('settings.partials.users', [
            'users' => $users,
            'currentUser' => $user,
        ])
    @endif

    <p class="back-link">
        <a href="/">&larr; Back</a>
    </p>
</x-layouts.app>
