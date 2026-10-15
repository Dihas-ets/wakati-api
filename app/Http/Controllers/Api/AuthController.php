<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/register-company
     * Inscription d'entreprise multi-tenant :
     * - L'inscripteur devient 'company_admin'
     * - Prénom, nom, numéro, adresse mail, mot de passe, nom de l'entreprise
     * - Choix du plan (essai ou régulier)
     */
    public function registerCompany(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'phone' => 'required|string|max:50',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
            'plan_id' => 'required|exists:plans,id',
            'registration_number' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'payment_method' => 'nullable|string|max:100',
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);

        return DB::transaction(function () use ($validated, $plan) {
            // 1. Création de l'entreprise
            $isTrial = $plan->is_trial || ($plan->trial_period_days > 0 && $plan->price <= 0);
            $durationDays = $plan->trial_period_days > 0 ? $plan->trial_period_days : 14;

            $startDate = now();
            if ($isTrial) {
                $endDate = now()->addDays($durationDays);
                $status = Subscription::STATUS_TRIAL;
                $companyStatus = Company::STATUS_TRIAL;
                $hasUsedTrial = true;
            } else {
                $endDate = match ($plan->duration_unit) {
                    Plan::DURATION_UNIT_DAY => now()->addDays($plan->duration),
                    Plan::DURATION_UNIT_YEAR => now()->addYears($plan->duration),
                    default => now()->addMonths($plan->duration),
                };
                $status = Subscription::STATUS_ACTIVE;
                $companyStatus = Company::STATUS_ACTIVE;
                $hasUsedTrial = false;
            }

            $slugBase = Str::slug($validated['company_name']);
            $slug = $slugBase . '-' . strtolower(Str::random(5));

            $company = Company::create([
                'name' => $validated['company_name'],
                'slug' => $slug,
                'domain_slug' => $slug,
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'registration_number' => $validated['registration_number'] ?? null,
                'address' => $validated['address'] ?? null,
                'access_type' => Company::ACCESS_TYPE_SUBSCRIPTION, // Par défaut conditionné à un abonnement
                'status' => $companyStatus,
                'has_used_trial' => $hasUsedTrial,
                'trial_ends_at' => $isTrial ? $endDate : null,
            ]);

            // 1.b Création de l'agence principale initiale
            $branch = \App\Models\Branch::create([
                'company_id' => $company->id,
                'name' => 'Siège Principal',
                'code' => 'HQ',
                'address' => $validated['address'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'timezone' => 'Africa/Porto-Novo',
                'is_active' => true,
            ]);

            // 2. Création de l'administrateur de l'entreprise (company_admin)
            $user = User::create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'name' => trim($validated['first_name'] . ' ' . $validated['last_name']),
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'phone' => $validated['phone'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => User::ROLE_COMPANY_ADMIN,
                'job_title' => 'Directeur Général',
                'status' => User::STATUS_AU_TRAVAIL,
                'last_login_at' => now(),
            ]);

            // 3. Création de l'abonnement initial
            $subscription = Subscription::create([
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $status,
                'is_trial' => $isTrial,
                'price_paid' => $isTrial ? 0 : $plan->price,
                'currency' => $plan->currency,
                'notes' => 'Abonnement initial souscrit lors de la création du compte.',
            ]);

            // 4. Si plan payant, enregistrer une transaction de paiement initiale
            if ($plan->price > 0 && !$isTrial) {
                Payment::create([
                    'company_id' => $company->id,
                    'subscription_id' => $subscription->id,
                    'amount' => $plan->price,
                    'currency' => $plan->currency,
                    'payment_method' => $validated['payment_method'] ?? 'Mobile Money',
                    'transaction_reference' => 'REG-' . strtoupper(Str::random(10)),
                    'status' => Payment::STATUS_COMPLETED,
                    'payment_date' => now(),
                    'notes' => "Paiement à l'inscription - Forfait {$plan->name}",
                ]);
            }

            // 5. Historique de l'entreprise
            $company->recordHistory(
                'registration',
                "Création de l'entreprise par {$user->name}. Forfait sélectionné : {$plan->name} (" . ($isTrial ? "Période d'essai {$durationDays} jours" : 'Abonnement standard') . ").",
                $user->id
            );

            // 6. Token d'authentification
            $token = $user->createToken('wakati-frontend')->plainTextToken;

            return response()->json([
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'role' => $user->role,
                    'company_id' => $company->id,
                    'company' => [
                        'id' => $company->id,
                        'name' => $company->name,
                        'logo_url' => $company->logo_url,
                        'registration_number' => $company->registration_number,
                        'access_type' => $company->access_type,
                        'status' => $company->status,
                        'has_valid_access' => $company->hasValidAccess(),
                    ],
                    'subscription' => [
                        'id' => $subscription->id,
                        'plan_name' => $plan->name,
                        'start_date' => $subscription->start_date->toISOString(),
                        'end_date' => $subscription->end_date->toISOString(),
                        'days_remaining' => $subscription->daysRemaining(),
                        'is_trial' => $subscription->is_trial,
                        'status' => $subscription->status,
                    ],
                    'job_title' => $user->job_title,
                    'status' => $user->status,
                    'card_uid' => $user->card_uid ?? $user->ensureCardUid(),
                ],
            ], 201);
        });
    }

    /**
     * POST /api/login
     */
    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants incorrects.'],
            ]);
        }

        if ($user->status === 'blocked') {
            throw ValidationException::withMessages([
                'email' => ["Ce compte a été bloqué par l'administrateur."],
            ]);
        }

        $token = $user->createToken('wakati-frontend')->plainTextToken;

        $user->update([
            'status' => User::STATUS_AU_TRAVAIL,
            'last_login_at' => now(),
        ]);

        $user->load(['department:id,name', 'company.currentSubscription.plan']);

        $company = $user->company;
        $sub = $company?->currentSubscription;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'phone' => $user->phone,
                'email' => $user->email,
                'role' => $user->role,
                'job_title' => $user->job_title,
                'department' => $user->department,
                'company_id' => $user->company_id,
                'company' => $company ? [
                    'id' => $company->id,
                    'name' => $company->name,
                    'logo_url' => $company->logo_url,
                    'registration_number' => $company->registration_number,
                    'access_type' => $company->access_type,
                    'status' => $company->status,
                    'has_valid_access' => $company->hasValidAccess(),
                ] : null,
                'subscription' => $sub ? [
                    'id' => $sub->id,
                    'plan_name' => $sub->plan?->name,
                    'start_date' => $sub->start_date?->toISOString(),
                    'end_date' => $sub->end_date?->toISOString(),
                    'days_remaining' => $sub->daysRemaining(),
                    'is_trial' => $sub->is_trial,
                    'status' => $sub->status,
                ] : null,
                'status' => $user->status,
                'card_uid' => $user->card_uid ?? $user->ensureCardUid(),
            ],
        ]);
    }

    /**
     * POST /api/logout
     */
    public function logout(Request $request)
    {
        $request->user()->update(['status' => User::STATUS_PAS_POINTE]);
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    /**
     * GET /api/me
     */
    public function me(Request $request)
    {
        $user = $request->user()->load(['department:id,name', 'company.currentSubscription.plan']);
        $company = $user->company;
        $sub = $company?->currentSubscription;

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'phone' => $user->phone,
            'email' => $user->email,
            'role' => $user->role,
            'job_title' => $user->job_title,
            'department' => $user->department,
            'company_id' => $user->company_id,
            'company' => $company ? [
                'id' => $company->id,
                'name' => $company->name,
                'logo_url' => $company->logo_url,
                'registration_number' => $company->registration_number,
                'access_type' => $company->access_type,
                'status' => $company->status,
                'has_valid_access' => $company->hasValidAccess(),
            ] : null,
            'subscription' => $sub ? [
                'id' => $sub->id,
                'plan_name' => $sub->plan?->name,
                'start_date' => $sub->start_date?->toISOString(),
                'end_date' => $sub->end_date?->toISOString(),
                'days_remaining' => $sub->daysRemaining(),
                'is_trial' => $sub->is_trial,
                'status' => $sub->status,
            ] : null,
            'status' => $user->status,
            'card_uid' => $user->card_uid ?? $user->ensureCardUid(),
        ]);
    }
}
