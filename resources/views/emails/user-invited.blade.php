<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2>You've been invited to {{ $appName }}</h2>

    <p>Hi {{ $user->name }},</p>

    <p>An admin has invited you to {{ $appName }}. Click the link below to set your password and log in.</p>

    <p>
        <a href="{{ $acceptUrl }}"
           style="background: #b45a35; color: #fff; padding: 12px 20px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: 600;">
            Accept invitation
        </a>
    </p>

    <p style="color: #6b7280; font-size: 13px;">
        Or copy this URL into your browser: <br>
        <a href="{{ $acceptUrl }}">{{ $acceptUrl }}</a>
    </p>

    <p>This invitation expires {{ $expiresAt->diffForHumans() }} ({{ $expiresAt->toDayDateTimeString() }}).</p>

    <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;">
    <p style="color: #6b7280; font-size: 12px;">
        Sent from {{ $appName }} at {{ config('app.url') }}
    </p>
</body>
</html>
