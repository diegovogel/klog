<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Str;

class ErrorOccurred extends Mailable
{
    public function __construct(
        public string $errorMessage,
        public string $errorLevel,
        public string $occurredAt,
        public ?string $stackTrace = null,
    ) {}

    public function envelope(): Envelope
    {
        $appName = config('app.name', 'Klog');

        return new Envelope(
            subject: "[{$appName}] Error: ".Str::limit($this->errorMessage, 80),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.error-occurred',
        );
    }
}
