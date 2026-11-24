<?php

namespace App\Mail;

use App\Models\Player;
use App\Models\PlayerApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicationApproved extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public PlayerApplication $application, public Player $player) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your NetPlay application was approved - next, payment');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.application-approved');
    }
}
