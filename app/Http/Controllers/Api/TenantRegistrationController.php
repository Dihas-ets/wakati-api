<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TenantRegistrationController extends Controller
{
    /**
     * Inscription d'un nouveau tenant (Entreprise + PDG/Company Admin).
     */
    public function registerCompany(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['required', 'string', 'max:30'],
            'company_name' => ['required', 'string', 'max:255'],
            'plan_id' => ['nullable', 'exists:plans,id'],
        ]);

        return DB::transaction(function () use ($validated) {
            // 1. Créer la Company
            $company = Company::create([
                'name' => trim($validated['company_name']),
                'phone' => $validated['phone'],
            ]);

            // 2. Associer le plan gratuit/essai par défaut si applicable
            $plan = null;
            if (! empty($validated['plan_id'])) {
                $plan = Plan::find($validated['plan_id']);
            }

            if (! $plan) {
                $plan = Plan::where('is_trial', true)->first()
                    ?? Plan::where('price', 0)->first()
                    ?? Plan::first();
            }

            if (! $plan) {
                $plan = Plan::create([
                    'name' => 'Plan Essai Gratuit',
                    'price' => 0,
                    'currency' => 'XOF',
                    'duration' => 14,
                    'duration_unit' => 'day',
                    'is_trial' => true,
                    'trial_period_days' => 14,
                    'max_employees' => 15,
                    'features' => ['pointage', 'reunions', 'kanban', 'agences'],
                ]);
            }

            $durationDays = $plan->is_trial && $plan->trial_period_days > 0
                ? $plan->trial_period_days
                : match ($plan->duration_unit) {
                    'year' => $plan->duration * 365,
                    'month' => $plan->duration * 30,
                    default => $plan->duration,
                };

            $startDate = now();
            $endDate = (clone $startDate)->addDays($durationDays);

            $subscription = Subscription::create([
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => Subscription::STATUS_ACTIVE,
            ]);

            // 3. Créer le User avec le rôle company_admin lié à cette Company
            $fullName = trim($validated['first_name'] . ' ' . $validated['last_name']);
            $user = User::create([
                'company_id' => $company->id,
                'agency_id' => null,
                'name' => $fullName,
                'email' => strtolower(trim($validated['email'])),
                'password' => Hash::make($validated['password']),
                'role' => User::ROLE_COMPANY_ADMIN,
                'status' => User::STATUS_PAS_POINTE,
            ]);

            // 4. Retourner le token d'authentification Sanctum et les données utilisateur
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Entreprise et compte administrateur créés avec succès.',
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'company_id' => $user->company_id,
                    'agency_id' => $user->agency_id,
                ],
                'company' => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'phone' => $company->phone,
                ],
                'subscription' => [
                    'id' => $subscription->id,
                    'plan_id' => $plan->id,
                    'plan_name' => $plan->name,
                    'status' => $subscription->status,
                    'start_date' => $subscription->start_date?->toIso8601String(),
                    'end_date' => $subscription->end_date?->toIso8601String(),
                ],
            ], 201);
        });
    }
}
