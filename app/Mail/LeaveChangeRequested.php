<?php

namespace App\Mail;

use App\Models\LeaveApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LeaveChangeRequested extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public LeaveApplication $leaveApplication)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Leave Change Request from '.$this->leaveApplication->user->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.leave-change-requested',
        );
    }
}
