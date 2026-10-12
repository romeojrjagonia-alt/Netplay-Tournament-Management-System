<?php

namespace App\Mail;

use App\Models\PlayerLinkRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PlayerLinkDecided extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public PlayerLinkRequest $linkRequest) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->linkRequest->status === PlayerLinkRequest::APPROVED
            ? 'Your NetPlay records were linked'
            : 'Update on your NetPlay record link request');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.player-link-decided');
    }
}
