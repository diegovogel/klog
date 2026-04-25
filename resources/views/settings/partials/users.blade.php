<section class="settings-section">
    <h2 class="settings-section__title">Users</h2>

    <h3 class="settings-section__subtitle">Invite a new user</h3>
    @php($inviteFailed = $errors->invite->any())
    <form method="POST" action="{{ route('settings.users.invite') }}">
        @csrf

        <div class="form-group">
            <label for="invite-name" class="form-label">Name</label>
            <input id="invite-name" name="name" type="text" class="form-input"
                   value="{{ $inviteFailed ? old('name') : '' }}" required>
            @error('name', 'invite')
            <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="form-group">
            <label for="invite-email" class="form-label">Email</label>
            <input id="invite-email" name="email" type="email" class="form-input"
                   value="{{ $inviteFailed ? old('email') : '' }}" required>
            @error('email', 'invite')
            <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="form-group">
            <label for="invite-role" class="form-label">Role</label>
            <select id="invite-role" name="role" class="form-input">
                @foreach(\App\Enums\UserRole::cases() as $role)
                    <option value="{{ $role->value }}" {{ $inviteFailed && old('role') === $role->value ? 'selected' : '' }}>
                        {{ ucfirst($role->value) }}
                    </option>
                @endforeach
            </select>
            @error('role', 'invite')
            <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="btn btn--primary">Send invite</button>
    </form>

    <hr class="settings-section__divider">

    <h3 class="settings-section__subtitle">Existing users</h3>
    <table class="settings-table">
        <thead>
        <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        @foreach($users as $u)
            <tr>
                <td>{{ $u->name }}</td>
                <td>{{ $u->email }}</td>
                <td>
                    <form method="POST" action="{{ route('settings.users.role.update', $u) }}" class="settings-table__inline-form">
                        @csrf
                        @method('PATCH')
                        <select name="role" onchange="this.form.submit()" {{ $u->id === $currentUser->id ? 'disabled' : '' }}>
                            @foreach(\App\Enums\UserRole::cases() as $role)
                                <option value="{{ $role->value }}" {{ $u->role === $role ? 'selected' : '' }}>
                                    {{ ucfirst($role->value) }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                </td>
                <td>
                    @if($u->isDeactivated())
                        <span class="settings-section__status-badge">Deactivated</span>
                    @elseif($u->invite && ! $u->invite->isAccepted())
                        <span class="settings-section__status-badge">Invited</span>
                    @else
                        <span class="settings-section__status-badge settings-section__status-badge--enabled">Active</span>
                    @endif
                </td>
                <td class="settings-table__actions">
                    @if($u->invite && ! $u->invite->isAccepted())
                        <form method="POST" action="{{ route('settings.users.resend-invite', $u) }}" class="settings-table__inline-form">
                            @csrf
                            <button type="submit" class="btn btn--secondary btn--small">Resend invite</button>
                        </form>
                    @endif

                    @if($u->id !== $currentUser->id)
                        @if($u->isActive())
                            <form method="POST" action="{{ route('settings.users.deactivate', $u) }}"
                                  class="settings-table__inline-form"
                                  data-confirm="Deactivate {{ $u->name }}? Their memories stay; they can no longer log in.">
                                @csrf
                                <button type="submit" class="btn btn--danger btn--small">Deactivate</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('settings.users.reactivate', $u) }}" class="settings-table__inline-form">
                                @csrf
                                <button type="submit" class="btn btn--secondary btn--small">Reactivate</button>
                            </form>
                        @endif
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</section>
