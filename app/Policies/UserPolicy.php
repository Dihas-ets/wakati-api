<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Bypasser pour le SUPER_ADMIN de la plateforme SaaS
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * Lister les utilisateurs / employés :
     * - COMPANY_ADMIN : tous les employés de son entreprise (toutes agences).
     * - BRANCH_MANAGER / HR_MANAGER : les employés de son agence.
     */
    public function viewAny(User $user): bool
    {
        return $user->isCompanyAdmin()
            || $user->isBranchManager()
            || $user->isHrManager();
    }

    /**
     * Consulter un utilisateur / employé :
     * - COMPANY_ADMIN : tout utilisateur de son entreprise.
     * - BRANCH_MANAGER / HR_MANAGER : utilisateur de sa propre agence.
     * - EMPLOYEE : uniquement lui-même.
     */
    public function view(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return true;
        }

        if ($user->company_id !== $model->company_id) {
            return false;
        }

        if ($user->isCompanyAdmin()) {
            return true;
        }

        if (($user->isBranchManager() || $user->isHrManager()) && $user->branch_id === $model->branch_id) {
            return true;
        }

        return false;
    }

    /**
     * Création d'un employé :
     * - COMPANY_ADMIN : au sein de n'importe quelle agence de son entreprise.
     * - BRANCH_MANAGER / HR_MANAGER : au sein de son agence.
     */
    public function create(User $user): bool
    {
        return $user->isCompanyAdmin()
            || $user->isBranchManager()
            || $user->isHrManager();
    }

    /**
     * Modification d'un employé :
     */
    public function update(User $user, User $model): bool
    {
        if ($user->company_id !== $model->company_id) {
            return false;
        }

        if ($user->isCompanyAdmin()) {
            return true;
        }

        if ($user->isBranchManager() && $user->branch_id === $model->branch_id) {
            return ! $model->isCompanyAdmin();
        }

        if ($user->isHrManager() && $user->branch_id === $model->branch_id) {
            return ! $model->isCompanyAdmin() && ! $model->isBranchManager();
        }

        return false;
    }

    /**
     * Suppression d'un utilisateur (interdiction de supprimer son propre compte)
     */
    public function delete(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        if ($user->company_id !== $model->company_id) {
            return false;
        }

        if ($user->isCompanyAdmin()) {
            return ! $model->isCompanyAdmin();
        }

        if ($user->isBranchManager() && $user->branch_id === $model->branch_id) {
            return ! $model->isCompanyAdmin() && ! $model->isBranchManager();
        }

        return false;
    }

    /**
     * Appairage de badge RFID / QR Code : réservé aux administrateurs et managers de l'agence
     */
    public function pairBadge(User $user, User $model): bool
    {
        if ($user->company_id !== $model->company_id) {
            return false;
        }

        if ($user->isCompanyAdmin()) {
            return true;
        }

        return ($user->isBranchManager() || $user->isHrManager()) && $user->branch_id === $model->branch_id;
    }
}
