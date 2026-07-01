<?php

namespace App\Mail;

use App\Models\Penalty;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CashPenaltyToCollect extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Penalty $penalty)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Cash penalty to collect from '.$this->penalty->user->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.cash-penalty-to-collect',
        );
    }
}
