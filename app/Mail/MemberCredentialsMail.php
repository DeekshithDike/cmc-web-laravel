<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MemberCredentialsMail extends Mailable
{
    public function __construct(
        public readonly int $loginId,
        public readonly string $password,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your '.config('citymax.name').' login details',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.member-credentials',
        );
    }
}
