<?php

namespace App\Mail;

use App\Models\Matches;
use App\Models\Player;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MatchScheduled extends Mailable
{
    public function __construct(public Matches $match, public Player $player) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your NetPlay match schedule');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.match-scheduled');
    }
}
