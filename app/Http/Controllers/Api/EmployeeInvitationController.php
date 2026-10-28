<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\EmployeeInvitationMail;
use App\Models\Company;
use App\Models\EmployeeInvitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EmployeeInvitationController extends Controller
{
    /**
     * Liste des invitations (RH ou Admin).
     */
    public function index(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        if (! $currentUser->isAdmin() && ! $currentUser->hasPermission('rh') && ! in_array($currentUser->role, [User::ROLE_SUPER_ADMIN, User::ROLE_COMPANY_ADMIN, User::ROLE_RH, User::ROLE_DIRECTEUR], true)) {
            return response()->json([
                'message' => 'Action non autorisée. Seul un administrateur ou un responsable RH peut consulter les invitations.'
            ], 403);
        }

        $invitations = EmployeeInvitation::with(['department:id,name', 'position:id,title', 'agency:id,name'])
            ->orderByDesc('created_at')
            ->paginate((int) $request->query('per_page', 20));

        return response()->json($invitations);
    }

    /**
     * POST /api/invitations/send
     * Protégée auth:sanctum / RH ou Admin
     */
    public function sendInvitation(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        if (! $currentUser->isAdmin() && ! $currentUser->hasPermission('rh') && ! in_array($currentUser->role, [User::ROLE_SUPER_ADMIN, User::ROLE_COMPANY_ADMIN, User::ROLE_RH, User::ROLE_DIRECTEUR], true)) {
            return response()->json([
                'message' => 'Action non autorisée. Seul un administrateur ou un responsable RH peut envoyer des invitations.'
            ], 403);
        }

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'position_id' => ['required', 'exists:positions,id'],
            'agency_id' => ['nullable', 'exists:agencies,id'],
        ], [
            'email.required' => "L'adresse e-mail est obligatoire.",
            'email.email' => "L'adresse e-mail renseignée n'est pas valide.",
            'email.unique' => "Cette adresse e-mail est déjà associée à un compte collaborateur existant sur Wakati.",
            'first_name.required' => "Le prénom est obligatoire.",
            'last_name.required' => "Le nom est obligatoire.",
            'position_id.required' => "Veuillez sélectionner le poste assigné au collaborateur.",
            'position_id.exists' => "Le poste sélectionné est introuvable.",
            'department_id.exists' => "Le département sélectionné est introuvable.",
            'agency_id.exists' => "L'agence sélectionnée est introuvable.",
        ]);

        $companyId = $currentUser->company_id;
        $agencyId = $validated['agency_id'] ?? $currentUser->agency_id;

        // Contrôle du quota d'employés du forfait
        if ($currentUser->role !== User::ROLE_SUPER_ADMIN && $companyId) {
            $company = Company::find($companyId);
            $plan = $company?->getActivePlan();
            if ($plan && $plan->max_employees !== null) {
                $currentEmployees = User::where('company_id', $companyId)->count();
                if ($currentEmployees >= $plan->max_employees) {
                    return response()->json([
                        'message' => "Limite d'employés atteinte pour votre forfait ({$plan->max_employees} max). Veuillez passer à un forfait supérieur pour inviter plus d'employés.",
                    ], 422);
                }
            }
        }

        $normalizedEmail = strtolower(trim($validated['email']));

        // Invalider les invitations précédentes encore en attente pour cet email
        EmployeeInvitation::where('email', $normalizedEmail)
            ->where('status', EmployeeInvitation::STATUS_PENDING)
            ->update(['status' => EmployeeInvitation::STATUS_EXPIRED]);

        // Générer un jeton sécurisé aléatoire
        $token = Str::random(40);

        // Enregistrer la ligne dans employee_invitations avec expires_at = now()->addHours(48)
        $invitation = EmployeeInvitation::create([
            'company_id' => $companyId,
            'agency_id' => $agencyId,
            'department_id' => $validated['department_id'] ?? null,
            'position_id' => $validated['position_id'],
            'email' => $normalizedEmail,
            'first_name' => trim($validated['first_name']),
            'last_name' => trim($validated['last_name']),
            'token' => $token,
            'status' => EmployeeInvitation::STATUS_PENDING,
            'expires_at' => now()->addHours(48),
        ]);

        // Déclencher l'envoi du mail via ShouldQueue
        Mail::to($invitation->email)->queue(new EmployeeInvitationMail($invitation));

        return response()->json([
            'message' => "Invitation envoyée avec succès à {$invitation->email}.",
            'invitation' => $invitation->load(['department:id,name', 'position:id,title', 'agency:id,name', 'company:id,name']),
        ], 201);
    }

    /**
     * GET /api/invitations/verify/{token}
     * Publique
     */
    public function verifyToken(string $token): JsonResponse
    {
        $invitation = EmployeeInvitation::withoutGlobalScopes()
            ->with(['department', 'position', 'company', 'agency'])
            ->where('token', $token)
            ->first();

        if (! $invitation) {
            return response()->json([
                'message' => "Ce lien d'invitation est invalide ou n'existe pas."
            ], 404);
        }

        if ($invitation->status === EmployeeInvitation::STATUS_ACCEPTED) {
            return response()->json([
                'message' => 'Cette invitation a déjà été acceptée. Vous pouvez vous connecter à votre compte.'
            ], 400);
        }

        if ($invitation->expires_at->isPast()) {
            if ($invitation->status === EmployeeInvitation::STATUS_PENDING) {
                $invitation->update(['status' => EmployeeInvitation::STATUS_EXPIRED]);
            }
            return response()->json([
                'message' => "Ce lien d'invitation a expiré (validité 48 heures). Veuillez contacter votre administrateur RH."
            ], 400);
        }

        if ($invitation->status !== EmployeeInvitation::STATUS_PENDING) {
            return response()->json([
                'message' => "Cette invitation n'est plus active."
            ], 400);
        }

        return response()->json([
            'valid' => true,
            'email' => $invitation->email,
            'first_name' => $invitation->first_name,
            'last_name' => $invitation->last_name,
            'company_name' => $invitation->company?->name,
            'agency_name' => $invitation->agency?->name,
            'department_name' => $invitation->department?->name,
            'position_title' => $invitation->position?->title,
            'expires_at' => $invitation->expires_at->toIso8601String(),
        ]);
    }

    /**
     * POST /api/invitations/accept
     * Publique
     */
    public function acceptInvitation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'token.required' => "Le jeton d'invitation est requis.",
            'first_name.required' => "Le prénom est obligatoire.",
            'last_name.required' => "Le nom est obligatoire.",
            'phone.required' => "Le numéro de téléphone est obligatoire.",
            'password.required' => "Le mot de passe est obligatoire.",
            'password.min' => "Le mot de passe doit comporter au moins 8 caractères.",
            'password.confirmed' => "La confirmation du mot de passe ne correspond pas au mot de passe saisi.",
        ]);

        $invitation = EmployeeInvitation::withoutGlobalScopes()
            ->with(['position', 'company'])
            ->where('token', $validated['token'])
            ->first();

        if (! $invitation) {
            return response()->json([
                'message' => "Ce lien d'invitation est invalide ou introuvable."
            ], 404);
        }

        if ($invitation->status === EmployeeInvitation::STATUS_ACCEPTED) {
            return response()->json([
                'message' => 'Cette invitation a déjà été acceptée.'
            ], 400);
        }

        if ($invitation->expires_at->isPast() || $invitation->status !== EmployeeInvitation::STATUS_PENDING) {
            $invitation->update(['status' => EmployeeInvitation::STATUS_EXPIRED]);
            return response()->json([
                'message' => "Ce lien d'invitation a expiré."
            ], 400);
        }

        if (User::withoutGlobalScopes()->where('email', $invitation->email)->exists()) {
            return response()->json([
                'message' => 'Un compte utilisateur existe déjà avec cette adresse email. Veuillez vous connecter.'
            ], 422);
        }

        $result = DB::transaction(function () use ($invitation, $validated) {
            $fullName = trim($validated['first_name'] . ' ' . $validated['last_name']);

            // 1. Crée l'utilisateur dans l'entreprise invitante
            $user = User::create([
                'company_id' => $invitation->company_id,
                'agency_id' => $invitation->agency_id,
                'name' => $fullName,
                'email' => $invitation->email,
                'phone' => $validated['phone'],
                'password' => Hash::make($validated['password']),
                'role' => User::ROLE_EMPLOYEE,
                'job_title' => $invitation->position?->title,
                'department_id' => $invitation->department_id,
                'status' => User::STATUS_PAS_POINTE,
            ]);

            // 2. Rattache le position_id principal dans la table pivot user_positions
            if ($invitation->position_id) {
                $user->positions()->attach($invitation->position_id, [
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 3. Passe l'invitation au statut accepted
            $invitation->update([
                'status' => EmployeeInvitation::STATUS_ACCEPTED,
            ]);

            // 4. Génère un token d'authentification Sanctum
            $token = $user->createToken('wakati-frontend')->plainTextToken;

            $user->load([
                'department:id,name',
                'positions:id,department_id,title,permissions,is_sensitive',
                'company:id,name,status,access_type,logo',
                'agency:id,name',
            ]);

            return [
                'user' => $user,
                'token' => $token,
            ];
        });

        $user = $result['user'];
        $token = $result['token'];

        return response()->json([
            'message' => 'Votre inscription a été finalisée avec succès.',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'company_id' => $user->company_id,
                'agency_id' => $user->agency_id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'job_title' => $user->job_title,
                'department' => $user->department,
                'department_id' => $user->department_id,
                'status' => $user->status,
                'card_uid' => $user->card_uid ?? $user->ensureCardUid(),
                'positions' => $user->positions,
                'is_admin' => $user->isAdmin(),
                'has_sensitive_role' => $user->hasSensitiveRole(),
                'company' => $user->company,
                'agency' => $user->agency,
            ],
        ], 201);
    }
}
