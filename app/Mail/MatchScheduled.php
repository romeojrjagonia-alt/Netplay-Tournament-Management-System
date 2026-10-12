<?php

namespace App\Mail;

use App\Models\Matches;
use App\Models\Player;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MatchScheduled extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

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
