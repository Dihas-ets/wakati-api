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

        $company = Company::with(['currentSubscription.plan', 'agencies'])
            ->findOrFail($user->company_id);

        return response()->json([
            'company' => $company,
            'has_valid_access' => $company->hasValidAccess(),
        ]);
    }

    /**
     * Mise à jour du profil de l'entreprise par le Company Admin.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if (! $user->company_id || $user->role !== User::ROLE_COMPANY_ADMIN) {
            return response()->json(['message' => 'Action réservée au Company Admin.'], 403);
        }

        $company = Company::findOrFail($user->company_id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'rccm_ifu' => ['nullable', 'string', 'max:100'],
            'logo' => ['nullable', 'string'],
            'address' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
        ]);

        $company->update($validated);

        CompanyHistory::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'action' => 'profile_updated',
            'description' => "Profil de l'entreprise mis à jour par l'administrateur.",
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

        $company = Company::findOrFail($user->company_id);
        $currentSubscription = $company->currentSubscription()->with('plan')->first();

        $availablePlans = Plan::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get();

        return response()->json([
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
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

        if (! $user->company_id || $user->role !== User::ROLE_COMPANY_ADMIN) {
            return response()->json(['message' => 'Action réservée au Company Admin.'], 403);
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
     * Historique des paiements de l'entreprise.
     */
    public function payments(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if (! $user->company_id) {
            return response()->json(['message' => 'Aucune entreprise rattachée.'], 404);
        }

        $payments = Payment::where('company_id', $user->company_id)
            ->with(['subscription.plan'])
            ->latest('payment_date')
            ->get();

        return response()->json($payments);
    }
}
