<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
<p style="background: #fef3c7; border: 1px solid #f59e0b; padding: 12px; border-radius: 4px; font-size: 13px;">
    <strong>If you are not the owner of {{ config('app.name', 'Klog') }} ({{config('app.url')}}, please forward this
        message to them.</strong><br>
    <em>Tip: a recipient for error messages can be specified in <code>.env</code>, please see the README for more info.</em>
</p>

<h2 style="color: #dc2626;">{{ $errorLevel }} in {{ config('app.name', 'Klog') }}</h2>

<p><strong>Time:</strong> {{ $occurredAt }}</p>
<p><strong>Message:</strong></p>
<pre style="background: #f3f4f6; padding: 12px; border-radius: 4px; overflow-x: auto; white-space: pre-wrap;">{{ $errorMessage }}</pre>

@if($stackTrace)
    <p><strong>Stack Trace:</strong></p>
    <pre style="background: #f3f4f6; padding: 12px; border-radius: 4px; overflow-x: auto; white-space: pre-wrap; font-size: 12px;">{{ $stackTrace }}</pre>
@endif

<hr style="border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;">
<p style="color: #6b7280; font-size: 12px;">
    Sent from {{ config('app.name', 'Klog') }} at {{ config('app.url') }}
</p>
</body>
</html>
