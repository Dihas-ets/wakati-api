<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Seul le Directeur peut lister tous les comptes utilisateurs administratifs.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === User::ROLE_DIRECTEUR;
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

        if ($user->role === User::ROLE_RH && in_array($model->role, [User::ROLE_EMPLOYE, User::ROLE_COMPTABLE], true)) {
            return true;
        }

        return false;
    }

    /**
     * Suppression d'un utilisateur (interdiction absolue de supprimer son propre compte).
     */
    public function delete(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        return in_array($user->role, [User::ROLE_DIRECTEUR, User::ROLE_RH], true);
    }

    /**
     * Appairage de badge RFID / QR Code : réservé au Directeur et RH.
     */
    public function pairBadge(User $user, User $model): bool
    {
        return in_array($user->role, [User::ROLE_DIRECTEUR, User::ROLE_RH], true);
    }
}
