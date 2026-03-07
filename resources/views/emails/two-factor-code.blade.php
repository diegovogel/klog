<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2>Your verification code</h2>

    <p style="font-size: 32px; font-weight: bold; letter-spacing: 8px; background: #f3f4f6; padding: 16px 24px; border-radius: 4px; display: inline-block;">{{ $code }}</p>

    <p>This code expires in {{ $expiresInMinutes }} minutes.</p>

    <p style="color: #6b7280; font-size: 13px;">If you did not request this code, you can safely ignore this email. Someone may have entered your email address by mistake.</p>

    <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;">
    <p style="color: #6b7280; font-size: 12px;">
        Sent from {{ config('app.name', 'Klog') }} at {{ config('app.url') }}
    </p>
</body>
</html>
