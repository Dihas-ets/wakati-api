<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Le Directeur et les RH peuvent lister tous les comptes utilisateurs.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [User::ROLE_DIRECTEUR, User::ROLE_RH], true);
    }

    /**
     * Un utilisateur peut voir ses propres données, ou le Directeur / RH peuvent consulter un employé.
     */
    public function view(User $user, User $model): bool
    {
        if (in_array($user->role, [User::ROLE_DIRECTEUR, User::ROLE_RH], true)) {
            return true;
        }

        return $user->id === $model->id;
    }

    /**
     * Seul le Directeur ou les RH peuvent créer un employé/utilisateur.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, [User::ROLE_DIRECTEUR, User::ROLE_RH], true);
    }

    /**
     * Modification d'un utilisateur.
     */
    public function update(User $user, User $model): bool
    {
        if ($user->role === User::ROLE_DIRECTEUR) {
            return true;
        }

        if ($user->role === User::ROLE_RH && $model->role !== User::ROLE_DIRECTEUR) {
            return true;
        }

        return false;
    }

    /**
     * Suppression d'un utilisateur (interdiction absolue de supprimer son propre compte ou pour un RH de supprimer un Directeur).
     */
    public function delete(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        if ($user->role === User::ROLE_DIRECTEUR) {
            return true;
        }

        if ($user->role === User::ROLE_RH && $model->role !== User::ROLE_DIRECTEUR) {
            return true;
        }

        return false;
    }

    /**
     * Appairage de badge RFID / QR Code : réservé au Directeur et RH.
     */
    public function pairBadge(User $user, User $model): bool
    {
        return in_array($user->role, [User::ROLE_DIRECTEUR, User::ROLE_RH], true);
    }
}
