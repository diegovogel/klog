<x-layouts.public title="Offline">
    <div class="login-page">
        <div class="login-card">
            <div class="login-card__header">
                <h1 class="login-card__logo">{{ config('app.name', 'Klog') }}</h1>
                <p class="login-card__tagline">You appear to be offline</p>
            </div>

            <div class="card">
                <div class="card__body" style="text-align: center;">
                    <p style="margin-block-end: var(--klog-space-lg); color: var(--klog-text-secondary); font-size: 0.9375rem;">
                        Check your connection and try again.
                    </p>
                    <button class="btn btn--primary btn--block" onclick="location.reload()">
                        Try Again
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-layouts.public>
