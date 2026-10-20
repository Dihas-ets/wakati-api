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

class SuperAdminController extends Controller
{
    /**
     * Statistiques globales de la plateforme pour le Super-Admin.
     */
    public function stats(): JsonResponse
    {
        $totalCompanies = Company::count();
        $activeSubscriptions = Subscription::where('status', Subscription::STATUS_ACTIVE)
            ->where('end_date', '>=', now())
            ->count();
        $trialCompanies = Company::where('status', Company::STATUS_TRIAL)
            ->orWhereHas('currentSubscription', fn ($q) => $q->where('is_trial', true))
            ->count();
        $totalRevenue = Payment::where('status', 'completed')->sum('amount');
        $totalUsers = User::count();

        $recentCompanies = Company::with(['currentSubscription.plan'])
            ->latest()
            ->take(5)
            ->get();

        $recentPayments = Payment::with(['company', 'subscription.plan'])
            ->latest('payment_date')
            ->take(5)
            ->get();

        return response()->json([
            'total_companies' => $totalCompanies,
            'active_subscriptions' => $activeSubscriptions,
            'trial_companies' => $trialCompanies,
            'total_revenue' => (float) $totalRevenue,
            'total_users' => $totalUsers,
            'recent_companies' => $recentCompanies,
            'recent_payments' => $recentPayments,
        ]);
    }

    /**
     * Liste filtrable des entreprises avec leurs utilisateurs et abonnement actuel.
     */
    public function companies(Request $request): JsonResponse
    {
        $query = Company::with([
            'currentSubscription.plan',
            'users' => fn ($q) => $q->select('id', 'name', 'email', 'role', 'company_id'),
        ]);

        if ($request->filled('search')) {
            $search = $request->string('search')->trim();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('rccm_ifu', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('access_type')) {
            $query->where('access_type', $request->input('access_type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $companies = $query->latest()->get();

        return response()->json($companies);
    }

    /**
     * Détails complets d'une entreprise (agences, utilisateurs, abonnements, paiements, historiques).
     */
    public function companyDetails(Company $company): JsonResponse
    {
        $company->load([
            'agencies',
            'users',
            'subscriptions.plan',
            'payments.subscription.plan',
            'histories.user',
        ]);

        return response()->json([
            'company' => $company,
            'has_valid_access' => $company->hasValidAccess(),
        ]);
    }

    /**
     * Modification du type d'accès (gratuit vs abonnement) et du statut de l'entreprise.
     */
    public function updateAccess(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'access_type' => ['required', 'string', 'in:subscription,free_grant'],
            'status' => ['nullable', 'string', 'in:active,trial,expired,suspended'],
        ]);

        $oldAccess = $company->access_type;
        $company->access_type = $validated['access_type'];

        if (! empty($validated['status'])) {
            $company->status = $validated['status'];
        }

        $company->save();

        CompanyHistory::create([
            'company_id' => $company->id,
            'user_id' => auth()->id(),
            'action' => 'access_updated',
            'description' => "Accès modifié de '{$oldAccess}' vers '{$company->access_type}' (Statut: {$company->status}).",
            'metadata' => [
                'old_access_type' => $oldAccess,
                'new_access_type' => $company->access_type,
                'status' => $company->status,
            ],
        ]);

        return response()->json([
            'message' => 'Droits d\'accès modifiés avec succès.',
            'company' => $company,
        ]);
    }

    /**
     * Mise à jour des informations de l'entreprise (nom, logo, références RCCM/IFU, contact).
     */
    public function updateCompany(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'rccm_ifu' => ['nullable', 'string', 'max:100'],
            'logo' => ['nullable', 'string'],
            'address' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:active,trial,expired,suspended'],
            'access_type' => ['nullable', 'string', 'in:subscription,free_grant'],
        ]);

        $company->update($validated);

        CompanyHistory::create([
            'company_id' => $company->id,
            'user_id' => auth()->id(),
            'action' => 'company_updated',
            'description' => "Informations de l'entreprise mises à jour par le Super-Admin.",
            'metadata' => $validated,
        ]);

        return response()->json([
            'message' => 'Entreprise mise à jour avec succès.',
            'company' => $company,
        ]);
    }

    /**
     * Attribution d'un forfait/abonnement à une entreprise.
     * Règle stricte : le plan gratuit / essai est valable UNE SEULE FOIS par entreprise.
     */
    public function assignSubscription(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'notes' => ['nullable', 'string'],
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);

        // Règle anti-abus : l'essai gratuit / plan gratuit est valable une seule fois
        if (($plan->is_trial || (float) $plan->price === 0.0) && $company->has_used_trial) {
            throw ValidationException::withMessages([
                'plan_id' => ["Cette entreprise a déjà bénéficié de l'offre d'essai gratuit. Le plan gratuit est valable une seule fois."],
            ]);
        }

        return DB::transaction(function () use ($company, $plan, $validated) {
            $isTrial = $plan->is_trial || (float) $plan->price === 0.0;

            if ($isTrial) {
                $company->has_used_trial = true;
                $company->status = Company::STATUS_TRIAL;
            } else {
                $company->status = Company::STATUS_ACTIVE;
            }
            $company->save();

            // Clôturer les anciens abonnements actifs
            $company->subscriptions()
                ->where('status', Subscription::STATUS_ACTIVE)
                ->update(['status' => Subscription::STATUS_EXPIRED]);

            $subscription = Subscription::create([
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'start_date' => Carbon::parse($validated['start_date']),
                'end_date' => Carbon::parse($validated['end_date']),
                'status' => Subscription::STATUS_ACTIVE,
                'is_trial' => $isTrial,
                'notes' => $validated['notes'] ?? null,
                'price_paid' => $plan->price,
                'currency' => $plan->currency,
            ]);

            CompanyHistory::create([
                'company_id' => $company->id,
                'user_id' => auth()->id(),
                'action' => 'subscription_assigned',
                'description' => "Attribution du forfait '{$plan->name}' du {$validated['start_date']} au {$validated['end_date']}.",
                'metadata' => [
                    'plan_id' => $plan->id,
                    'plan_name' => $plan->name,
                    'is_trial' => $isTrial,
                    'start_date' => $validated['start_date'],
                    'end_date' => $validated['end_date'],
                ],
            ]);

            return response()->json([
                'message' => 'Forfait attribué avec succès à l\'entreprise.',
                'subscription' => $subscription->load('plan'),
                'company' => $company,
            ], 201);
        });
    }

    /**
     * Liste complète de tous les forfaits pour le Super-Admin.
     */
    public function plans(): JsonResponse
    {
        $plans = Plan::orderBy('sort_order', 'asc')->get();
        return response()->json($plans);
    }

    /**
     * Création d'un forfait par le Super-Admin.
     */
    public function createPlan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:plans,slug'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'duration' => ['required', 'integer', 'min:1'],
            'duration_unit' => ['required', 'string', 'in:day,month,year'],
            'duration_type' => ['nullable', 'string', 'in:recurring,fixed,trial'],
            'is_trial' => ['boolean'],
            'trial_period_days' => ['integer', 'min:0'],
            'max_employees' => ['nullable', 'integer', 'min:1'],
            'features' => ['nullable', 'array'],
            'is_popular' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $plan = Plan::create($validated);

        return response()->json($plan, 201);
    }

    /**
     * Mise à jour d'un forfait existant.
     */
    public function updatePlan(Request $request, Plan $plan): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:plans,slug,' . $plan->id],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'required', 'string', 'max:10'],
            'duration' => ['sometimes', 'required', 'integer', 'min:1'],
            'duration_unit' => ['sometimes', 'required', 'string', 'in:day,month,year'],
            'duration_type' => ['nullable', 'string', 'in:recurring,fixed,trial'],
            'is_trial' => ['boolean'],
            'trial_period_days' => ['integer', 'min:0'],
            'max_employees' => ['nullable', 'integer', 'min:1'],
            'features' => ['nullable', 'array'],
            'is_popular' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $plan->update($validated);

        return response()->json($plan);
    }

    /**
     * Suppression d'un forfait (si aucune souscription n'est rattachée).
     */
    public function deletePlan(Plan $plan): JsonResponse
    {
        if ($plan->subscriptions()->exists()) {
            // Désactiver plutôt que supprimer si des entreprises l'utilisent
            $plan->update(['is_active' => false]);
            return response()->json([
                'message' => 'Le forfait est rattaché à des abonnements existants. Il a été désactivé pour les nouvelles souscriptions.',
            ]);
        }

        $plan->delete();
        return response()->json(['message' => 'Forfait supprimé avec succès.']);
    }

    /**
     * Historique des paiements avec filtres entreprise et statut.
     */
    public function payments(Request $request): JsonResponse
    {
        $query = Payment::with([
            'company:id,name,phone,logo,rccm_ifu',
            'subscription.plan:id,name',
        ]);

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->input('company_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $payments = $query->latest('payment_date')->get();

        return response()->json($payments);
    }

    /**
     * Enregistrement manuel d'un paiement par le Super-Admin.
     */
    public function storePayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'subscription_id' => ['nullable', 'exists:subscriptions,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'payment_method' => ['required', 'string', 'max:100'],
            'payment_date' => ['required', 'date'],
            'status' => ['required', 'string', 'in:completed,pending,failed,refunded'],
            'notes' => ['nullable', 'string'],
        ]);

        $payment = Payment::create([
            'company_id' => $validated['company_id'],
            'subscription_id' => $validated['subscription_id'] ?? null,
            'amount' => $validated['amount'],
            'currency' => $validated['currency'],
            'payment_method' => $validated['payment_method'],
            'transaction_reference' => 'PAY-' . strtoupper(bin2hex(random_bytes(5))),
            'status' => $validated['status'],
            'payment_date' => Carbon::parse($validated['payment_date']),
            'notes' => $validated['notes'] ?? null,
        ]);

        CompanyHistory::create([
            'company_id' => $validated['company_id'],
            'user_id' => auth()->id(),
            'action' => 'payment_recorded',
            'description' => "Paiement de {$validated['amount']} {$validated['currency']} enregistré par le Super-Admin ({$validated['payment_method']}).",
            'metadata' => $payment->toArray(),
        ]);

        return response()->json($payment->load(['company', 'subscription.plan']), 201);
    }

    /**
     * Historique des actions et événements entreprises pour le Super-Admin.
     */
    public function histories(Request $request): JsonResponse
    {
        $query = CompanyHistory::with([
            'company:id,name,logo',
            'user:id,name,email',
        ]);

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->input('company_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        $histories = $query->latest()->get();

        return response()->json($histories);
    }
}
