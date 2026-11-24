<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Carries plain values rather than models: a deleted event's applications
 * are gone by the time the queued mail is sent.
 */
class EventCancelled extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public string $playerName, public string $tournamentName) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'A NetPlay event you applied for was cancelled');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.event-cancelled');
    }
}
