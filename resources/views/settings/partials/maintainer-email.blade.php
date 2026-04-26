<section class="settings-section">
    <h2 class="settings-section__title">Maintainer email</h2>
    <p>Address that receives error notifications. Leave blank to fall back to the first available user.</p>

    <form method="POST" action="{{ route('settings.maintainer-email.update') }}">
        @csrf
        @method('PATCH')

        <div class="form-group">
            <label for="maintainer-email" class="form-label">Email</label>
            <input id="maintainer-email" name="maintainer_email" type="email" class="form-input"
                   value="{{ old('maintainer_email', $maintainerEmail) }}"
                   placeholder="someone@example.com">
            @error('maintainer_email')
            <p class="form-error">{{ $message }}</p>
            @enderror
            @if($maintainerEmailFromEnv)
                <p class="form-hint">A <code>MAINTAINER_EMAIL</code> env var is set; the value here always wins.</p>
            @endif
        </div>

        <button type="submit" class="btn btn--primary">Save</button>
    </form>
</section>
