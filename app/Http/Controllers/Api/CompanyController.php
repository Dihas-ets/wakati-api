<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CompanyController extends Controller
{
    /**
     * Récupère l'entreprise de l'utilisateur connecté
     */
    protected function getCompany(Request $request): Company
    {
        $user = $request->user();
        if (!$user->company_id) {
            abort(404, "Aucune entreprise rattachée à cet utilisateur.");
        }

        return Company::findOrFail($user->company_id);
    }

    /**
     * GET /api/company/profile
     * Profil de l'entreprise, références et logo
     */
    public function profile(Request $request): JsonResponse
    {
        $company = $this->getCompany($request)->load([
            'currentSubscription.plan',
        ]);

        return response()->json([
            'company' => $company,
            'has_valid_access' => $company->hasValidAccess(),
        ]);
    }

    /**
     * PUT /api/company/profile
     * Mise à jour des références de l'entreprise et du logo
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'registration_number' => 'nullable|string|max:100', // RCCM, IFU, SIRET...
            'logo_url' => 'nullable|string', // Logo
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'website' => 'nullable|string|max:255',
        ]);

        $company->update($validated);

        $company->recordHistory(
            'profile_update',
            "Mise à jour des coordonnées et références de l'entreprise.",
            $request->user()->id
        );

        return response()->json([
            'message' => 'Profil de l\'entreprise mis à jour avec succès.',
            'company' => $company->fresh(['currentSubscription.plan']),
        ]);
    }

    /**
     * GET /api/company/subscription
     * État de l'abonnement en cours et liste des plans disponibles
     */
    public function subscription(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);
        $currentSub = $company->currentSubscription()->with('plan')->first();
        $plans = Plan::where('is_active', true)->orderBy('sort_order', 'asc')->get();

        return response()->json([
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'access_type' => $company->access_type,
                'has_used_trial' => $company->has_used_trial,
                'status' => $company->status,
                'has_valid_access' => $company->hasValidAccess(),
            ],
            'current_subscription' => $currentSub ? [
                'id' => $currentSub->id,
                'plan' => $currentSub->plan,
                'start_date' => $currentSub->start_date->toISOString(),
                'end_date' => $currentSub->end_date->toISOString(),
                'days_remaining' => $currentSub->daysRemaining(),
                'is_trial' => $currentSub->is_trial,
                'status' => $currentSub->status,
                'price_paid' => $currentSub->price_paid,
                'currency' => $currentSub->currency,
            ] : null,
            'available_plans' => $plans,
        ]);
    }

    /**
     * POST /api/company/subscription/change
     * Changement ou renouvellement de forfait
     * Règle : Le plan gratuit / période d'essai est valable une seule fois par entreprise !
     */
    public function changeSubscription(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);

        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'payment_method' => 'nullable|string|max:100',
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);

        // Vérification de la règle : Le plan gratuit / essai est valable UNE SEULE FOIS
        if ($plan->is_trial || $plan->price <= 0) {
            if ($company->has_used_trial) {
                throw ValidationException::withMessages([
                    'plan_id' => ["Le plan gratuit ou d'essai n'est valable qu'une seule fois. Votre entreprise en a déjà bénéficié."],
                ]);
            }
        }

        $startDate = now();
        $isTrial = $plan->is_trial;

        if ($isTrial) {
            $durationDays = $plan->trial_period_days ?: 14;
            $endDate = now()->addDays($durationDays);
            $status = Subscription::STATUS_TRIAL;
            $company->update([
                'has_used_trial' => true,
                'trial_ends_at' => $endDate,
                'status' => Company::STATUS_TRIAL,
            ]);
        } else {
            $endDate = match ($plan->duration_unit) {
                Plan::DURATION_UNIT_DAY => now()->addDays($plan->duration),
                Plan::DURATION_UNIT_YEAR => now()->addYears($plan->duration),
                default => now()->addMonths($plan->duration),
            };
            $status = Subscription::STATUS_ACTIVE;
            $company->update([
                'status' => Company::STATUS_ACTIVE,
            ]);
        }

        // Désactiver l'ancien abonnement s'il existe
        $company->subscriptions()
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIAL])
            ->update(['status' => Subscription::STATUS_CANCELLED]);

        // Créer le nouvel abonnement
        $subscription = Subscription::create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $status,
            'is_trial' => $isTrial,
            'price_paid' => $plan->price,
            'currency' => $plan->currency,
            'notes' => 'Changement de forfait effectué en ligne.',
        ]);

        // Enregistrer le paiement si le plan est payant
        if ($plan->price > 0) {
            Payment::create([
                'company_id' => $company->id,
                'subscription_id' => $subscription->id,
                'amount' => $plan->price,
                'currency' => $plan->currency,
                'payment_method' => $validated['payment_method'] ?? 'Mobile Money',
                'transaction_reference' => 'PAY-' . strtoupper(Str::random(10)),
                'status' => Payment::STATUS_COMPLETED,
                'payment_date' => now(),
                'notes' => "Souscription au forfait {$plan->name}",
            ]);
        }

        $company->recordHistory(
            'subscription_changed',
            "Passage au forfait {$plan->name} (" . ($isTrial ? 'Essai' : 'Payant') . ") valable jusqu'au {$endDate->format('d/m/Y')}.",
            $request->user()->id
        );

        return response()->json([
            'message' => 'Abonnement activé avec succès.',
            'subscription' => $subscription->load('plan'),
        ]);
    }

    /**
     * GET /api/company/payments
     * Historique des paiements de l'entreprise
     */
    public function payments(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);
        $payments = $company->payments()->with('subscription.plan:id,name')->get();

        return response()->json($payments);
    }
}
