<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->role === User::ROLE_SUPER_ADMIN) {
            return true;
        }

        return null;
    }

    /**
     * Le Directeur, Company Admin, RH et Manager peuvent lister les comptes de leur périmètre.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [
            User::ROLE_SUPER_ADMIN,
            User::ROLE_COMPANY_ADMIN,
            User::ROLE_MANAGER,
            User::ROLE_DIRECTEUR,
            User::ROLE_RH,
        ], true);
    }

    /**
     * Un utilisateur peut voir ses propres données, ou les admins/managers peuvent consulter un employé.
     */
    public function view(User $user, User $model): bool
    {
        if (in_array($user->role, [
            User::ROLE_SUPER_ADMIN,
            User::ROLE_COMPANY_ADMIN,
            User::ROLE_MANAGER,
            User::ROLE_DIRECTEUR,
            User::ROLE_RH,
        ], true)) {
            return true;
        }

        return $user->id === $model->id;
    }

    /**
     * Seul l'administrateur d'entreprise, le Directeur ou les RH peuvent créer un employé/utilisateur.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, [
            User::ROLE_SUPER_ADMIN,
            User::ROLE_COMPANY_ADMIN,
            User::ROLE_MANAGER,
            User::ROLE_DIRECTEUR,
            User::ROLE_RH,
        ], true);
    }

    /**
     * Modification d'un utilisateur.
     */
    public function update(User $user, User $model): bool
    {
        if (in_array($user->role, [User::ROLE_SUPER_ADMIN, User::ROLE_COMPANY_ADMIN, User::ROLE_DIRECTEUR], true)) {
            return true;
        }

        if (in_array($user->role, [User::ROLE_RH, User::ROLE_MANAGER], true)
            && ! in_array($model->role, [User::ROLE_SUPER_ADMIN, User::ROLE_COMPANY_ADMIN, User::ROLE_DIRECTEUR], true)) {
            return true;
        }

        return $user->id === $model->id;
    }

    /**
     * Suppression d'un utilisateur (interdiction absolue de supprimer son propre compte ou un administrateur supérieur).
     */
    public function delete(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        if (in_array($user->role, [User::ROLE_SUPER_ADMIN, User::ROLE_COMPANY_ADMIN, User::ROLE_DIRECTEUR], true)) {
            return true;
        }

        if (in_array($user->role, [User::ROLE_RH, User::ROLE_MANAGER], true)
            && ! in_array($model->role, [User::ROLE_SUPER_ADMIN, User::ROLE_COMPANY_ADMIN, User::ROLE_DIRECTEUR], true)) {
            return true;
        }

        return false;
    }

    /**
     * Appairage de badge RFID / QR Code : réservé à la Direction, Company Admin et RH.
     */
    public function pairBadge(User $user, User $model): bool
    {
        return in_array($user->role, [
            User::ROLE_SUPER_ADMIN,
            User::ROLE_COMPANY_ADMIN,
            User::ROLE_MANAGER,
            User::ROLE_DIRECTEUR,
            User::ROLE_RH,
        ], true);
    }

    /**
     * Attribution de postes sensibles : réservé aux administrateurs.
     */
    public function assignSensitivePosition(User $user): bool
    {
        return in_array($user->role, [User::ROLE_SUPER_ADMIN, User::ROLE_COMPANY_ADMIN, User::ROLE_DIRECTEUR], true);
    }
}
