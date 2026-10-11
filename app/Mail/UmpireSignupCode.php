<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class UmpireSignupCode extends Mailable
{
    public function __construct(
        public string $code,
        public string $name,
        public int $validForMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your NetPlay umpire verification code');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.umpire-signup-code');
    }
}
