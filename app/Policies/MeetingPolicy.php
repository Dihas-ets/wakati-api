<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\User;

class MeetingPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if (in_array($user->role, [User::ROLE_SUPER_ADMIN, User::ROLE_COMPANY_ADMIN], true)) {
            return true;
        }

        return null;
    }

    /**
     * Tous les utilisateurs authentifiés peuvent consulter la liste des réunions
     * (les réunions sont filtrées selon leur rôle).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Visualisation d'une réunion :
     * - RH et Direction : accès complet.
     * - Animateur / Rédacteur : accès.
     * - Collaborateur : seulement s'il est invité à la séance.
     */
    public function view(User $user, Meeting $meeting): bool
    {
        if (in_array($user->role, [User::ROLE_RH, User::ROLE_DIRECTEUR], true)) {
            return true;
        }

        if ($meeting->redactor_id === $user->id) {
            return true;
        }

        return $meeting->meetingParticipants()->where('user_id', $user->id)->exists();
    }

    /**
     * Création de réunion :
     * Réservée à la Direction, aux RH ou aux Animateurs autorisés (les employés simples ne peuvent pas créer).
     */
    public function create(User $user): bool
    {
        return in_array($user->role, [User::ROLE_RH, User::ROLE_DIRECTEUR], true);
    }

    /**
     * Modification d'une réunion / ordre du jour :
     * Réservée à la Direction, aux RH ou à l'Animateur/Rédacteur de la séance.
     */
    public function update(User $user, Meeting $meeting): bool
    {
        if (in_array($user->role, [User::ROLE_RH, User::ROLE_DIRECTEUR], true)) {
            return true;
        }

        return $meeting->redactor_id === $user->id;
    }

    /**
     * Suppression d'une réunion.
     */
    public function delete(User $user, Meeting $meeting): bool
    {
        if (in_array($user->role, [User::ROLE_RH, User::ROLE_DIRECTEUR], true)) {
            return true;
        }

        return $meeting->redactor_id === $user->id;
    }

    /**
     * Piloter la session en direct (Mode Live : démarrer / suspendre) :
     * Réservé à l'Animateur / Secrétaire ou à la Direction / RH.
     */
    public function pilotLive(User $user, Meeting $meeting): bool
    {
        if (in_array($user->role, [User::ROLE_RH, User::ROLE_DIRECTEUR], true)) {
            return true;
        }

        return $meeting->redactor_id === $user->id;
    }

    /**
     * Télécharger ou générer la fiche Word officielle modifiable (.docx) :
     * STRICTEMENT INTERDIT aux simples employés invités.
     * Accessible uniquement à l'Animateur / Rédacteur et à la Direction / RH.
     */
    public function downloadDocx(User $user, Meeting $meeting): bool
    {
        if (in_array($user->role, [User::ROLE_RH, User::ROLE_DIRECTEUR], true)) {
            return true;
        }

        return $meeting->redactor_id === $user->id;
    }

    /**
     * Consulter la section 6 "Notes Confidentielles DRH" :
     * Accessible EXCLUSIVEMENT aux profils RH et Direction.
     */
    public function viewDrhNotes(User $user): bool
    {
        return in_array($user->role, [User::ROLE_RH, User::ROLE_DIRECTEUR], true);
    }

    /**
     * Ajouter des sujets et attribuer des tâches Kanban :
     * Réservé à l'Animateur / Rédacteur ou à la Direction / RH.
     */
    public function manageTopics(User $user, Meeting $meeting): bool
    {
        if (in_array($user->role, [User::ROLE_RH, User::ROLE_DIRECTEUR], true)) {
            return true;
        }

        return $meeting->redactor_id === $user->id;
    }
}
