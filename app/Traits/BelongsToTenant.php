<?php

namespace App\Traits;

use App\Models\Agency;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    /**
     * Boot the BelongsToTenant trait for the model.
     *
     * 1. addGlobalScope :
     *    - super_admin => aucun filtre
     *    - company_admin => filtre par company_id
     *    - manager / employee => filtre par agency_id
     *
     * 2. creating hook :
     *    - injecte automatiquement le company_id et agency_id du user authentifié lors des INSERT.
     */
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (! auth()->check()) {
                return;
            }

            /** @var User|null $user */
            $user = auth()->user();

            if (! $user) {
                return;
            }

            // super_admin : accès total plateforme, aucun filtre
            if ($user->role === User::ROLE_SUPER_ADMIN) {
                return;
            }

            $table = $builder->getModel()->getTable();

            // company_admin : voit TOUTES les agences et données de son entreprise
            if ($user->role === User::ROLE_COMPANY_ADMIN) {
                if ($user->company_id !== null) {
                    $builder->where($table . '.company_id', $user->company_id);
                }
                return;
            }

            // manager / employee : voit UNIQUEMENT les données de son agence
            if (in_array($user->role, [User::ROLE_MANAGER, User::ROLE_EMPLOYEE, User::ROLE_EMPLOYE], true)) {
                if ($user->agency_id !== null) {
                    $builder->where($table . '.agency_id', $user->agency_id);
                } elseif ($user->company_id !== null) {
                    $builder->where($table . '.company_id', $user->company_id);
                }
                return;
            }

            // Autres rôles historiques (ex: directeur/rh sans agency_id mais avec company_id)
            if ($user->agency_id !== null) {
                $builder->where($table . '.agency_id', $user->agency_id);
            } elseif ($user->company_id !== null) {
                $builder->where($table . '.company_id', $user->company_id);
            }
        });

        static::creating(function (Model $model) {
            if (auth()->check()) {
                /** @var User|null $user */
                $user = auth()->user();

                if ($user) {
                    if (empty($model->company_id) && $user->company_id !== null) {
                        $model->company_id = $user->company_id;
                    }

                    if (empty($model->agency_id) && $user->agency_id !== null) {
                        $model->agency_id = $user->agency_id;
                    }
                }
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
