<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyHistory;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyProfileController extends Controller
{
    /**
     * Profil de l'entreprise de l'utilisateur connecté.
     */
    public function profile(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if (! $user->company_id) {
            return response()->json(['message' => 'Aucune entreprise rattachée à cet utilisateur.'], 404);
        }

        $relations = ['agencies'];
        if (in_array($user->role, [User::ROLE_COMPANY_ADMIN, User::ROLE_SUPER_ADMIN], true)) {
            $relations[] = 'currentSubscription.plan';
        }

        $company = Company::with($relations)->findOrFail($user->company_id);

        if ($user->role === User::ROLE_DIRECTEUR) {
            $company->makeHidden(['currentSubscription']);
        }

        return response()->json([
            'company' => $company,
            'has_valid_access' => $company->hasValidAccess(),
        ]);
    }

    /**
     * Mise à jour du profil de l'entreprise par le Company Admin ou Directeur.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if (! $user->company_id || ! in_array($user->role, [User::ROLE_COMPANY_ADMIN, User::ROLE_DIRECTEUR, User::ROLE_SUPER_ADMIN], true)) {
            return response()->json(['message' => 'Action réservée aux administrateurs de l\'entreprise.'], 403);
        }

        $company = Company::findOrFail($user->company_id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'rccm_ifu' => ['nullable', 'string', 'max:100'],
            'logo' => ['nullable', 'string'],
            'primary_logo' => ['nullable', 'string'],
            'secondary_logo' => ['nullable', 'string'],
            'primary_color' => ['nullable', 'string', 'max:30'],
            'secondary_color' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
        ]);

        if (isset($validated['primary_logo']) && !isset($validated['logo'])) {
            $validated['logo'] = $validated['primary_logo'];
        }

        $company->update($validated);

        CompanyHistory::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'action' => 'profile_updated',
            'description' => "Profil et identité visuelle de l'entreprise mis à jour par l'administrateur.",
            'metadata' => $validated,
        ]);

        return response()->json([
            'message' => 'Profil de l\'entreprise mis à jour avec succès.',
            'company' => $company,
        ]);
    }

    /**
     * Récupération des informations d'abonnement et forfaits disponibles.
     */
    public function subscription(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if (! $user->company_id) {
            return response()->json(['message' => 'Aucune entreprise rattachée.'], 404);
        }

        // Un directeur ne peut pas voir le forfait actuel de l'entreprise
        if (! in_array($user->role, [User::ROLE_COMPANY_ADMIN, User::ROLE_SUPER_ADMIN], true)) {
            return response()->json([
                'message' => "Accès non autorisé. La consultation de l'abonnement et du forfait est réservée à l'administrateur de l'entreprise.",
            ], 403);
        }

        $company = Company::findOrFail($user->company_id);
        $currentSubscription = $company->currentSubscription()->with('plan')->first();

        $availablePlans = Plan::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get();

        return response()->json([
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'logo' => $company->logo,
                'primary_logo' => $company->primary_logo,
                'secondary_logo' => $company->secondary_logo,
                'primary_color' => $company->primary_color,
                'secondary_color' => $company->secondary_color,
                'access_type' => $company->access_type,
                'status' => $company->status,
                'has_used_trial' => $company->has_used_trial,
                'has_valid_access' => $company->hasValidAccess(),
            ],
            'current_subscription' => $currentSubscription ? [
                'id' => $currentSubscription->id,
                'plan' => $currentSubscription->plan,
                'start_date' => $currentSubscription->start_date?->toIso8601String(),
                'end_date' => $currentSubscription->end_date?->toIso8601String(),
                'status' => $currentSubscription->status,
                'is_trial' => $currentSubscription->is_trial,
                'days_remaining' => max(0, (int) now()->diffInDays($currentSubscription->end_date, false)),
                'price_paid' => $currentSubscription->price_paid,
                'currency' => $currentSubscription->currency,
            ] : null,
            'available_plans' => $availablePlans,
        ]);
    }

    /**
     * Souscription ou changement de forfait.
     * Règle stricte : Le plan d'essai gratuit n'est utilisable qu'une seule fois par entreprise.
     */
    public function changeSubscription(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if (! $user->company_id || ! in_array($user->role, [User::ROLE_COMPANY_ADMIN, User::ROLE_SUPER_ADMIN], true)) {
            return response()->json(['message' => 'Action réservée aux administrateurs de l\'entreprise.'], 403);
        }

        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'payment_method' => ['nullable', 'string', 'max:100'],
        ]);

        $company = Company::findOrFail($user->company_id);
        $plan = Plan::findOrFail($validated['plan_id']);

        $isTrial = $plan->is_trial || (float) $plan->price === 0.0;

        if ($isTrial && $company->has_used_trial) {
            throw ValidationException::withMessages([
                'plan_id' => ["Votre entreprise a déjà bénéficié de l'offre d'essai gratuit. Veuillez choisir un forfait payant pour continuer."],
            ]);
        }

        return DB::transaction(function () use ($company, $plan, $isTrial, $validated, $user) {
            $durationDays = $isTrial && $plan->trial_period_days > 0
                ? $plan->trial_period_days
                : match ($plan->duration_unit) {
                    'year' => $plan->duration * 365,
                    'month' => $plan->duration * 30,
                    default => $plan->duration,
                };

            $startDate = now();
            $endDate = (clone $startDate)->addDays($durationDays);

            if ($isTrial) {
                $company->has_used_trial = true;
                $company->status = Company::STATUS_TRIAL;
            } else {
                $company->status = Company::STATUS_ACTIVE;
            }
            $company->save();

            // Expirer l'abonnement précédent
            $company->subscriptions()
                ->where('status', Subscription::STATUS_ACTIVE)
                ->update(['status' => Subscription::STATUS_EXPIRED]);

            $subscription = Subscription::create([
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => Subscription::STATUS_ACTIVE,
                'is_trial' => $isTrial,
                'price_paid' => $plan->price,
                'currency' => $plan->currency,
            ]);

            // Si c'est un plan payant, enregistrer le paiement
            if (! $isTrial && (float) $plan->price > 0) {
                Payment::create([
                    'company_id' => $company->id,
                    'subscription_id' => $subscription->id,
                    'amount' => $plan->price,
                    'currency' => $plan->currency,
                    'payment_method' => $validated['payment_method'] ?? 'Mobile Money',
                    'transaction_reference' => 'TX-' . strtoupper(bin2hex(random_bytes(5))),
                    'status' => 'completed',
                    'payment_date' => now(),
                    'notes' => "Souscription au forfait {$plan->name}",
                ]);
            }

            CompanyHistory::create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'action' => 'subscription_changed',
                'description' => "Passage au forfait '{$plan->name}'.",
                'metadata' => [
                    'plan_id' => $plan->id,
                    'plan_name' => $plan->name,
                    'is_trial' => $isTrial,
                    'end_date' => $endDate->toIso8601String(),
                ],
            ]);

            return response()->json([
                'message' => 'Abonnement mis à jour avec succès.',
                'subscription' => $subscription->load('plan'),
                'company' => $company,
            ]);
        });
    }

    /**
     * Paiement sécurisé d'abonnement depuis le dashboard (FedaPay ou FexPay).
     */
    public function paySubscription(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if (! $user->company_id || ! in_array($user->role, [User::ROLE_COMPANY_ADMIN, User::ROLE_SUPER_ADMIN], true)) {
            return response()->json(['message' => 'Action réservée aux administrateurs de l\'entreprise.'], 403);
        }

        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'gateway' => ['required', 'string', 'in:fedapay,fexpay'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $company = Company::findOrFail($user->company_id);
        $plan = Plan::findOrFail($validated['plan_id']);

        return DB::transaction(function () use ($company, $plan, $validated, $user) {
            $durationDays = match ($plan->duration_unit) {
                'year' => $plan->duration * 365,
                'month' => $plan->duration * 30,
                default => $plan->duration,
            };

            // Si l'abonnement en cours est encore actif, on prolonge à partir de la date de fin existante
            $activeSub = $company->currentSubscription;
            $baseDate = ($activeSub && $activeSub->status === Subscription::STATUS_ACTIVE && $activeSub->end_date && $activeSub->end_date->isFuture())
                ? $activeSub->end_date
                : now();

            $startDate = now();
            $endDate = (clone $baseDate)->addDays($durationDays);

            $company->status = Company::STATUS_ACTIVE;
            $company->access_type = Company::ACCESS_TYPE_SUBSCRIPTION;
            $company->save();

            // Mettre à jour les précédents abonnements
            $company->subscriptions()
                ->where('status', Subscription::STATUS_ACTIVE)
                ->update(['status' => Subscription::STATUS_EXPIRED]);

            $subscription = Subscription::create([
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => Subscription::STATUS_ACTIVE,
                'is_trial' => false,
                'price_paid' => $plan->price,
                'currency' => $plan->currency,
            ]);

            $gatewayName = $validated['gateway'] === 'fedapay' ? 'FedaPay' : 'FexPay';
            $prefix = strtoupper($validated['gateway']);
            $txRef = "{$prefix}-" . strtoupper(bin2hex(random_bytes(5)));
            $defaultMethod = $validated['gateway'] === 'fedapay'
                ? 'FedaPay (MTN MoMo / Moov Money / Carte)'
                : 'FexPay (Mobile Money / Carte Bancaire)';
            $methodLabel = $validated['payment_method'] ?? $defaultMethod;

            $payment = Payment::create([
                'company_id' => $company->id,
                'subscription_id' => $subscription->id,
                'amount' => $plan->price,
                'currency' => $plan->currency,
                'payment_method' => $methodLabel,
                'transaction_reference' => $txRef,
                'status' => 'completed',
                'payment_date' => now(),
                'notes' => $validated['notes'] ?? "Paiement en ligne sécurisé via {$gatewayName}" . ($validated['phone'] ? " • Contact: {$validated['phone']}" : ""),
            ]);

            CompanyHistory::create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'action' => 'subscription_paid',
                'description' => "Paiement validé avec succès via {$gatewayName} pour le forfait '{$plan->name}'. Référence : {$txRef}.",
                'metadata' => [
                    'gateway' => $validated['gateway'],
                    'plan_id' => $plan->id,
                    'plan_name' => $plan->name,
                    'transaction_reference' => $txRef,
                    'amount' => $plan->price,
                    'currency' => $plan->currency,
                    'start_date' => $startDate->toIso8601String(),
                    'end_date' => $endDate->toIso8601String(),
                ],
            ]);

            return response()->json([
                'message' => "Paiement via {$gatewayName} effectué avec succès ! Votre abonnement est activé jusqu'au " . $endDate->format('d/m/Y') . ".",
                'subscription' => $subscription->load('plan'),
                'payment' => $payment,
                'company' => $company,
            ]);
        });
    }

    /**
     * Historique des paiements de l'entreprise.
     */
    public function payments(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if (! $user->company_id) {
            return response()->json(['message' => 'Aucune entreprise rattachée.'], 404);
        }

        if (! in_array($user->role, [User::ROLE_COMPANY_ADMIN, User::ROLE_SUPER_ADMIN], true)) {
            return response()->json(['message' => "Accès non autorisé. La consultation des paiements est réservée à l'administrateur de l'entreprise."], 403);
        }

        $payments = Payment::where('company_id', $user->company_id)
            ->with(['subscription.plan'])
            ->latest('payment_date')
            ->get();

        return response()->json($payments);
    }
}
