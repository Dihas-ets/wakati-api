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
        $replyToAddress = \App\Models\CompanySetting::get('mail_reply_to_address')
            ?: \App\Models\CompanySetting::get('mail_from_address')
            ?: config('mail.reply_to.address')
            ?: config('mail.from.address');

        $replyToName = \App\Models\CompanySetting::get('mail_reply_to_name')
            ?: \App\Models\CompanySetting::get('mail_from_name')
            ?: config('mail.reply_to.name')
            ?: config('mail.from.name');

        $replyTo = [];
        if ($replyToAddress) {
            $replyTo[] = new \Illuminate\Mail\Mailables\Address($replyToAddress, $replyToName ?? '');
        }

        return new Envelope(
            subject: "Convocation : {$this->meeting->title} - " . \Carbon\Carbon::parse($this->meeting->date)->format('d/m/Y'),
            replyTo: $replyTo,
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
