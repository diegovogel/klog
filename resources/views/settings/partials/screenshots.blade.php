<section class="settings-section" data-screenshots-section>
    <h2 class="settings-section__title">Web clipping screenshots</h2>
    <p>Captures full-page screenshots of every web clipping. Adds a Chromium-based dependency.</p>

    <div class="settings-section__status">
        <span class="settings-section__status-badge {{ $installed ? 'settings-section__status-badge--enabled' : '' }}">
            {{ $installed ? 'Installed' : 'Not installed' }}
        </span>
        <span class="settings-section__status-badge {{ $enabled ? 'settings-section__status-badge--enabled' : '' }}">
            {{ $enabled ? 'Enabled' : 'Disabled' }}
        </span>
    </div>

    @if($enabled && ! $installed)
        <div class="alert alert--warning">
            Screenshots are enabled but the Chromium toolchain isn't installed. Run the install below.
        </div>
    @endif

    @if(($status['state'] ?? null) === 'failed')
        <div class="alert alert--error">
            <strong>Last operation failed.</strong>
            @if($status['message'])
                <pre style="white-space: pre-wrap;">{{ $status['message'] }}</pre>
            @endif
        </div>
    @endif

    <div class="settings-section__methods">
        <form method="POST" action="{{ route('settings.screenshots.update') }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="enabled" value="{{ $enabled ? '0' : '1' }}">
            <button type="submit" class="btn {{ $enabled ? 'btn--secondary' : 'btn--primary' }}">
                {{ $enabled ? 'Disable' : 'Enable' }}
            </button>
        </form>

        <div class="divider"></div>

        @if($installed)
            <form method="POST" action="{{ route('settings.screenshots.uninstall') }}"
                  data-confirm="Remove the screenshot toolchain? Existing screenshots stay attached as media.">
                @csrf
                <button type="submit" class="btn btn--danger">Uninstall toolchain</button>
            </form>
        @else
            <form method="POST" action="{{ route('settings.screenshots.install') }}">
                @csrf
                <button type="submit" class="btn btn--primary">Install toolchain</button>
            </form>
        @endif
    </div>

    @if(in_array($status['state'] ?? null, ['queued', 'running'], true))
        <div class="alert alert--info" data-screenshot-status>
            <p><strong>Working…</strong> {{ $status['message'] ?? '' }}</p>
            <p>This page will refresh automatically when the operation completes.</p>
        </div>
        <script>
            (function () {
                const poll = () => fetch(@json(route('settings.screenshots.status')), {headers: {'Accept': 'application/json'}})
                    .then(r => r.json())
                    .then(data => {
                        const state = data.status.state;
                        if (state === 'success' || state === 'failed' || state === 'idle') {
                            window.location.reload();
                        }
                    })
                    .catch(() => {});
                setInterval(poll, 2000);
            })();
        </script>
    @endif
</section>
