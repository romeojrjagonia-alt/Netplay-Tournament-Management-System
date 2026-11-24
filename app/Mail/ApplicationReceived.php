<?php

namespace App\Mail;

use App\Models\PlayerApplication;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ApplicationReceived extends Mailable
{
    public function __construct(public PlayerApplication $application) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your NetPlay application reference code');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.application-received');
    }
}
