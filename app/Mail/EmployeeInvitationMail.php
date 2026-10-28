<?php

namespace App\Mail;

use App\Models\EmployeeInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmployeeInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public EmployeeInvitation $invitation
    ) {}

    public function envelope(): Envelope
    {
        $fromAddress = config('mail.from.address', 'cto@dihas.tech');
        $fromName = config('mail.from.name', 'Wakati');

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            subject: "Invitation à rejoindre Wakati",
        );
    }

    public function content(): Content
    {
        $this->invitation->loadMissing(['department', 'position']);

        $frontendUrl = rtrim((string) env('FRONTEND_URL', config('app.frontend_url', 'http://localhost:3000')), '/');
        $activationUrl = "{$frontendUrl}/accept-invitation?token={$this->invitation->token}";

        return new Content(
            view: 'emails.employee_invitation',
            with: [
                'invitation' => $this->invitation,
                'activationUrl' => $activationUrl,
                'expiresAt' => $this->invitation->expires_at,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
