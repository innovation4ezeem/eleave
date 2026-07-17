<?php

namespace App\Mail;

use App\Models\Penalty;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PenaltyIssued extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Penalty $penalty)
    {
    }

    public function envelope(): Envelope
    {
        $type = $this->penalty->isCash() ? 'Cash Penalty' : 'Warning';

        return new Envelope(
            subject: "You have received a {$type} ({$this->penalty->levelLabel()})",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.penalty-issued',
        );
    }
}
