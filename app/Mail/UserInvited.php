<?php

namespace App\Mail;

use App\Models\UserInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserInvited extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public UserInvite $invite) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been invited to ".config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.user-invited',
            with: [
                'user' => $this->invite->user,
                'acceptUrl' => route('invites.show', ['token' => $this->invite->token]),
                'appName' => config('app.name'),
                'expiresAt' => $this->invite->expires_at,
            ],
        );
    }
}
