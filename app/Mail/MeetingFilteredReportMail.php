<?php

namespace App\Mail;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MeetingFilteredReportMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Liste des tâches Kanban filtrées EXCLUSIVEMENT pour cet employé.
     */
    public $userTasks;

    public function __construct(
        public Meeting $meeting,
        public User $participant
    ) {
        // Filtrage de sécurité strict : Seules les tâches assignées à cet employé sont transmises
        // Les notes DRH et les tâches des autres collaborateurs ne sont JAMAIS injectées dans cette vue
        $this->userTasks = $this->meeting->kanbanTasks
            ->where('assigned_to', $this->participant->id)
            ->values();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Compte-Rendu & Actions assignées : {$this->meeting->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.meetings.filtered_report',
            with: [
                'userTasks' => $this->userTasks,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
