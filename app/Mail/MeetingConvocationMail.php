<?php

namespace App\Mail;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MeetingConvocationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Meeting $meeting,
        public User $participant
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Convocation : {$this->meeting->title} - " . \Carbon\Carbon::parse($this->meeting->date)->format('d/m/Y'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.meetings.convocation',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
