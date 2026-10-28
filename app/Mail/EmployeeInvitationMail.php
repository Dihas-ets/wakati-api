<?php

namespace App\Mail;

use App\Models\CompanySetting;
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
        $this->invitation->loadMissing(['company', 'agency', 'department', 'position']);
        $company = $this->invitation->company;

        if ($company) {
            CompanySetting::configureMailerForCompany($company->id);
        }

        $fromAddress = CompanySetting::get('mail_from_address') ?: ($company?->email ?: config('mail.from.address', 'noreply@wakati.app'));
        $fromName = CompanySetting::get('mail_from_name') ?: ($company?->name ?: config('mail.from.name', 'Wakati'));
        $replyToAddress = CompanySetting::get('mail_reply_to_address') ?: $fromAddress;
        $replyToName = CompanySetting::get('mail_reply_to_name') ?: $fromName;

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            replyTo: [new Address($replyToAddress, $replyToName)],
            subject: "Invitation à rejoindre " . ($company?->name ?? "Wakati"),
        );
    }

    public function content(): Content
    {
        $this->invitation->loadMissing(['company', 'agency', 'department', 'position']);

        $frontendUrl = rtrim((string) env('FRONTEND_URL', config('app.frontend_url', 'http://localhost:3000')), '/');
        $activationUrl = "{$frontendUrl}/accept-invitation?token={$this->invitation->token}";

        return new Content(
            view: 'emails.employee_invitation',
            with: [
                'invitation' => $this->invitation,
                'company' => $this->invitation->company,
                'agency' => $this->invitation->agency,
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
