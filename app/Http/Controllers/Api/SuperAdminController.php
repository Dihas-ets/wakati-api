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
use Illuminate\Support\Str;

class SuperAdminController extends Controller
{
    /**
     * GET /api/super-admin/stats
     * Statistiques globales de la plateforme multi-entreprises
     */
    public function stats(): JsonResponse
    {
        $totalCompanies = Company::count();
        $activeSubscriptions = Subscription::whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIAL])
            ->where('end_date', '>=', now())
            ->count();
        $trialCompanies = Company::where('status', Company::STATUS_TRIAL)->count();
        $totalRevenue = Payment::where('status', Payment::STATUS_COMPLETED)->sum('amount');
        $totalUsers = User::whereNotNull('company_id')->count();

        $recentCompanies = Company::with(['currentSubscription.plan'])
            ->latest()
            ->take(5)
            ->get();

        $recentPayments = Payment::with(['company:id,name'])
            ->latest('payment_date')
            ->take(5)
            ->get();

        return response()->json([
            'total_companies' => $totalCompanies,
            'active_subscriptions' => $activeSubscriptions,
            'trial_companies' => $trialCompanies,
            'total_revenue' => $totalRevenue,
            'total_users' => $totalUsers,
            'recent_companies' => $recentCompanies,
            'recent_payments' => $recentPayments,
        ]);
    }

    /**
     * GET /api/super-admin/companies
     * Liste des entreprises avec recherche et filtres
     */
    public function companies(Request $request): JsonResponse
    {
        $query = Company::with(['currentSubscription.plan', 'users:id,name,email,company_id,role']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('registration_number', 'like', "%{$search}%");
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
     * GET /api/super-admin/companies/{company}
     */
    public function showCompany(Company $company): JsonResponse
    {
        $company->load([
            'users',
            'subscriptions.plan',
            'payments',
            'histories.user:id,name',
        ]);

        return response()->json($company);
    }

    /**
     * POST /api/super-admin/companies
     * Création directe d'une entreprise par le Super-Admin
     */
    public function storeCompany(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'registration_number' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'logo_url' => 'nullable|string',
            'access_type' => 'required|in:subscription,free_grant',
            'plan_id' => 'nullable|exists:plans,id',
        ]);

        $company = Company::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']) . '-' . Str::random(5),
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'registration_number' => $validated['registration_number'] ?? null,
            'address' => $validated['address'] ?? null,
            'logo_url' => $validated['logo_url'] ?? null,
            'access_type' => $validated['access_type'],
            'status' => Company::STATUS_ACTIVE,
        ]);

        if (!empty($validated['plan_id'])) {
            $plan = Plan::find($validated['plan_id']);
            if ($plan) {
                Subscription::create([
                    'company_id' => $company->id,
                    'plan_id' => $plan->id,
                    'start_date' => now(),
                    'end_date' => now()->addMonths(1),
                    'status' => Subscription::STATUS_ACTIVE,
                    'is_trial' => false,
                    'price_paid' => $plan->price,
                    'currency' => $plan->currency,
                    'notes' => 'Abonnement créé par le Super-Admin',
                ]);
            }
        }

        $company->recordHistory(
            'creation',
            "Entreprise créée manuellement par le Super-Admin.",
            $request->user()->id
        );

        return response()->json($company->load('currentSubscription.plan'), 201);
    }

    /**
     * PUT /api/super-admin/companies/{company}
     * Mise à jour des références et infos de l'entreprise
     */
    public function updateCompany(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'registration_number' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'logo_url' => 'nullable|string',
            'website' => 'nullable|string|max:255',
            'access_type' => 'nullable|in:subscription,free_grant',
            'status' => 'nullable|in:active,trial,expired,suspended',
        ]);

        $company->update($validated);

        $company->recordHistory(
            'update',
            "Informations et références de l'entreprise mises à jour par le Super-Admin.",
            $request->user()->id
        );

        return response()->json($company);
    }

    /**
     * PATCH /api/super-admin/companies/{company}/access
     * Paramétrage Super-Admin : Accès gratuit ou conditionné à un abonnement
     */
    public function updateAccess(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'access_type' => 'required|in:subscription,free_grant',
            'status' => 'nullable|in:active,trial,expired,suspended',
        ]);

        $oldAccess = $company->access_type;
        $company->update($validated);

        $description = $validated['access_type'] === Company::ACCESS_TYPE_FREE_GRANT
            ? "Accès accordé en mode GRATUIT ILLIMITÉ par le Super-Admin."
            : "Accès reconfiguré en mode CONDITIONNÉ À UN ABONNEMENT par le Super-Admin.";

        $company->recordHistory(
            'access_type_changed',
            $description,
            $request->user()->id,
            ['old_access' => $oldAccess, 'new_access' => $validated['access_type']]
        );

        return response()->json([
            'message' => 'Paramètre d\'accès mis à jour avec succès.',
            'company' => $company->fresh(['currentSubscription.plan']),
        ]);
    }

    /**
     * POST /api/super-admin/companies/{company}/subscriptions
     * Attribution ou prolongation d'un forfait par le Super-Admin
     */
    public function assignSubscription(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'status' => 'nullable|in:active,trial,expired,cancelled',
            'notes' => 'nullable|string',
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);

        $subscription = Subscription::create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'start_date' => Carbon::parse($validated['start_date']),
            'end_date' => Carbon::parse($validated['end_date']),
            'status' => $validated['status'] ?? Subscription::STATUS_ACTIVE,
            'is_trial' => $plan->is_trial,
            'price_paid' => $plan->price,
            'currency' => $plan->currency,
            'notes' => $validated['notes'] ?? 'Attribution manuelle Super-Admin',
        ]);

        $company->update(['status' => Company::STATUS_ACTIVE]);

        $company->recordHistory(
            'subscription_assigned',
            "Abonnement au forfait {$plan->name} activé du {$subscription->start_date->format('d/m/Y')} au {$subscription->end_date->format('d/m/Y')} par le Super-Admin.",
            $request->user()->id
        );

        return response()->json($subscription->load('plan'));
    }

    // ==========================================
    // GESTION DES PLANS / FORFAITS (Super-Admin)
    // ==========================================

    public function plans(): JsonResponse
    {
        $plans = Plan::withCount('subscriptions')
            ->orderBy('sort_order', 'asc')
            ->orderBy('price', 'asc')
            ->get();

        return response()->json($plans);
    }

    public function storePlan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|max:10',
            'duration' => 'required|integer|min:1',
            'duration_unit' => 'required|in:day,month,year',
            'duration_type' => 'required|in:recurring,fixed,trial',
            'is_trial' => 'required|boolean',
            'trial_period_days' => 'nullable|integer|min:0',
            'features' => 'nullable|array',
            'max_employees' => 'nullable|integer|min:1',
            'is_popular' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(4);
        $plan = Plan::create($validated);

        return response()->json($plan, 201);
    }

    public function updatePlan(Request $request, Plan $plan): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|max:10',
            'duration' => 'required|integer|min:1',
            'duration_unit' => 'required|in:day,month,year',
            'duration_type' => 'required|in:recurring,fixed,trial',
            'is_trial' => 'required|boolean',
            'trial_period_days' => 'nullable|integer|min:0',
            'features' => 'nullable|array',
            'max_employees' => 'nullable|integer|min:1',
            'is_popular' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $plan->update($validated);

        return response()->json($plan);
    }

    public function deletePlan(Plan $plan): JsonResponse
    {
        if ($plan->subscriptions()->count() > 0) {
            $plan->update(['is_active' => false]);
            return response()->json(['message' => 'Le plan a été désactivé car il possède des abonnements rattachés.']);
        }

        $plan->delete();
        return response()->json(['message' => 'Plan supprimé avec succès.']);
    }

    // ==========================================
    // HISTORIQUES PAIEMENTS (Super-Admin)
    // ==========================================

    public function payments(Request $request): JsonResponse
    {
        $query = Payment::with(['company:id,name,registration_number,logo_url', 'subscription.plan:id,name']);

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->input('company_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $payments = $query->latest('payment_date')->get();

        return response()->json($payments);
    }

    public function storePayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'subscription_id' => 'nullable|exists:subscriptions,id',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|max:10',
            'payment_method' => 'required|string|max:100',
            'transaction_reference' => 'nullable|string|max:100',
            'status' => 'required|in:completed,pending,failed,refunded',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        if (empty($validated['transaction_reference'])) {
            $validated['transaction_reference'] = 'PAY-' . strtoupper(Str::random(10));
        }

        $payment = Payment::create($validated);

        $company = Company::find($validated['company_id']);
        if ($company) {
            $company->recordHistory(
                'manual_payment',
                "Paiement de {$payment->amount} {$payment->currency} enregistré manuellement par le Super-Admin.",
                $request->user()->id,
                ['payment_id' => $payment->id]
            );
        }

        return response()->json($payment->load('company:id,name'), 201);
    }

    // ==========================================
    // HISTORIQUES ENTREPRISES (Super-Admin)
    // ==========================================

    public function histories(Request $request): JsonResponse
    {
        $query = CompanyHistory::with(['company:id,name,logo_url', 'user:id,name,email']);

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->input('company_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        $histories = $query->latest('id')->take(100)->get();

        return response()->json($histories);
    }
}
