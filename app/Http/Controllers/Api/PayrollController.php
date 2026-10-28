<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Payroll;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $this->canAccessAccounting($user)) {
            abort(403, "Action non autorisée. Seul le service Comptable, RH ou la Direction peut accéder à la gestion des rémunérations.");
        }

        $month = $request->query('month', now()->format('Y-m'));

        try {
            $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth();
        } catch (\Throwable $e) {
            $startDate = now()->startOfMonth();
            $endDate = now()->endOfMonth();
            $month = now()->format('Y-m');
        }

        // Récupérer les employés du périmètre tenant
        $employeesQuery = User::with([
            'department:id,name',
            'agency:id,name',
            'positions' => function ($q) {
                $q->orderBy('title');
            },
        ])->whereNotIn('role', [User::ROLE_SUPER_ADMIN]);

        if ($user->agency_id) {
            $employeesQuery->where('agency_id', $user->agency_id);
        }

        if ($departmentId = $request->query('department_id')) {
            $employeesQuery->where('department_id', $departmentId);
        }

        if ($search = $request->query('q')) {
            $employeesQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('job_title', 'like', "%{$search}%");
            });
        }

        $employees = $employeesQuery->orderBy('name')->get();

        // Récupérer les fiches de paie existantes pour ce mois
        $payrolls = Payroll::where(function ($q) use ($month) {
            $q->where('month_year', $month)->orWhere('month', $month);
        })->with(['rhValidator:id,name', 'paidBy:id,name'])->get()->keyBy('user_id');

        // Récupérer les pointages et absences du mois pour calcul d'assiduité
        $attendances = Attendance::whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->groupBy('user_id');

        $absences = Absence::whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->groupBy('user_id');

        $rows = [];
        $totalMass = 0;
        $totalPaid = 0;
        $totalPending = 0;
        $countValidated = 0;
        $countPaid = 0;

        foreach ($employees as $emp) {
            $payroll = $payrolls->get($emp->id);
            $userAttendances = $attendances->get($emp->id, collect());
            $userAbsences = $absences->get($emp->id, collect());

            $attendancesCount = $userAttendances->count();
            $lateCount = $userAttendances->where('late_minutes', '>', 0)->count();
            $unexcusedAbsences = $userAbsences->where('status', '!=', 'justified')->count();

            $baseSalary = $payroll ? (float) $payroll->base_salary : 0;
            $bonus = $payroll ? (float) $payroll->bonus : 0;
            $deductions = $payroll ? (float) $payroll->deductions : ($unexcusedAbsences * 5000); // 5 000 FCFA par absence non justifiée par défaut
            $netSalary = $payroll ? (float) $payroll->net_salary : max(0, $baseSalary + $bonus - $deductions);

            $rhStatus = $payroll ? $payroll->status_rh : Payroll::RH_STATUS_DRAFT;
            $paymentStatus = $payroll ? $payroll->payment_status : Payroll::PAYMENT_STATUS_UNPAID;

            if ($rhStatus === Payroll::RH_STATUS_VALIDATED) {
                $countValidated++;
                $totalMass += $netSalary;
            }

            if ($paymentStatus === Payroll::PAYMENT_STATUS_PAID) {
                $countPaid++;
                $totalPaid += $netSalary;
            } else {
                $totalPending += $netSalary;
            }

            $primaryPos = $emp->primaryPosition();

            $rows[] = [
                'user_id' => $emp->id,
                'payroll_id' => $payroll?->id,
                'name' => $emp->name,
                'email' => $emp->email,
                'phone' => $emp->phone,
                'department_id' => $emp->department_id,
                'department_name' => $emp->department?->name ?? 'Non assigné',
                'agency_id' => $emp->agency_id,
                'agency_name' => $emp->agency?->name,
                'position_title' => $primaryPos?->title ?? $emp->job_title ?? 'Collaborateur',
                'is_sensitive' => $primaryPos?->is_sensitive ?? false,
                'base_salary' => $baseSalary,
                'bonus' => $bonus,
                'deductions' => $deductions,
                'net_salary' => $netSalary,
                'rh_status' => $rhStatus,
                'rh_validated_at' => $payroll?->rh_validated_at?->toIso8601String(),
                'rh_validator_name' => $payroll?->rhValidator?->name,
                'payment_status' => $paymentStatus,
                'paid_at' => $payroll?->paid_at?->toIso8601String(),
                'payment_reference' => $payroll?->payment_reference ?? $payroll?->payment_ref,
                'paid_by_name' => $payroll?->paidBy?->name,
                'notes' => $payroll?->notes,
                'stats' => [
                    'days_present' => $attendancesCount,
                    'days_late' => $lateCount,
                    'unexcused_absences' => $unexcusedAbsences,
                ],
            ];
        }

        return response()->json([
            'month' => $month,
            'summary' => [
                'total_employees' => count($rows),
                'total_payroll_mass' => $totalMass,
                'total_paid' => $totalPaid,
                'total_pending' => $totalPending,
                'count_validated' => $countValidated,
                'count_paid' => $countPaid,
            ],
            'payrolls' => $rows,
        ]);
    }

    public function upsertRh(Request $request)
    {
        $user = $request->user();
        if (! $this->canManageRhPayroll($user)) {
            abort(403, "Seul le service RH ou la Direction peut définir et valider les salaires.");
        }

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'month' => ['sometimes', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'month_year' => ['sometimes', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'bonus' => ['nullable', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'numeric', 'min:0'],
            'rh_status' => ['nullable', 'in:draft,validated_by_rh'],
            'status_rh' => ['nullable', 'in:draft,validated_by_rh'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $month = $data['month_year'] ?? $data['month'] ?? now()->format('Y-m');
        $baseSalary = (float) $data['base_salary'];
        $bonus = (float) ($data['bonus'] ?? 0);
        $deductions = (float) ($data['deductions'] ?? 0);
        $netSalary = max(0, $baseSalary + $bonus - $deductions);
        $rhStatus = $data['status_rh'] ?? $data['rh_status'] ?? Payroll::RH_STATUS_DRAFT;

        $targetUser = User::findOrFail($data['user_id']);

        $updateData = [
            'company_id' => $targetUser->company_id ?: $user->company_id,
            'agency_id' => $targetUser->agency_id,
            'month_year' => $month,
            'month' => $month,
            'base_salary' => $baseSalary,
            'bonus' => $bonus,
            'deductions' => $deductions,
            'net_salary' => $netSalary,
            'status_rh' => $rhStatus,
            'rh_status' => $rhStatus,
            'notes' => $data['notes'] ?? null,
        ];

        if ($rhStatus === Payroll::RH_STATUS_VALIDATED) {
            $updateData['rh_validated_at'] = now();
            $updateData['rh_validator_id'] = $user->id;
        }

        $payroll = Payroll::updateOrCreate(
            ['user_id' => $data['user_id'], 'month_year' => $month],
            $updateData
        );

        AuditLog::record(
            userId: $user->id,
            targetUserId: $payroll->user_id,
            action: $rhStatus === Payroll::RH_STATUS_VALIDATED ? 'payroll.validated_by_rh' : 'payroll.rh_draft_saved',
            details: [
                'month' => $payroll->month_year,
                'base_salary' => $payroll->base_salary,
                'bonus' => $payroll->bonus,
                'deductions' => $payroll->deductions,
                'net_salary' => $payroll->net_salary,
                'status' => $payroll->status_rh,
            ],
            ip: $request->ip()
        );

        return response()->json($payroll->load(['user:id,name,email', 'rhValidator:id,name']));
    }

    public function markAsPaid(Request $request, Payroll $payroll)
    {
        $user = $request->user();
        if (! $this->canMarkAsPaid($user)) {
            abort(403, "Seul le service Comptable ou la Direction peut marquer une rémunération comme payée.");
        }

        if ($payroll->status_rh !== Payroll::RH_STATUS_VALIDATED && $payroll->rh_status !== Payroll::RH_STATUS_VALIDATED) {
            return response()->json([
                'message' => "Cette fiche de paie doit d'abord être validée par le service RH avant tout versement.",
            ], 422);
        }

        $data = $request->validate([
            'payment_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'payment_ref' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $ref = $data['payment_reference'] ?? $data['payment_ref'] ?? ('VIR-' . strtoupper(uniqid()));

        $payroll->update([
            'payment_status' => Payroll::PAYMENT_STATUS_PAID,
            'paid_at' => now(),
            'payment_ref' => $ref,
            'payment_reference' => $ref,
            'paid_by' => $user->id,
            'notes' => $data['notes'] ?? $payroll->notes,
        ]);

        AuditLog::record(
            userId: $user->id,
            targetUserId: $payroll->user_id,
            action: 'payroll.marked_as_paid',
            details: [
                'payroll_id' => $payroll->id,
                'month' => $payroll->month_year,
                'net_salary' => $payroll->net_salary,
                'payment_reference' => $payroll->payment_reference,
            ],
            ip: $request->ip()
        );

        return response()->json($payroll->load(['user:id,name,email', 'paidBy:id,name']));
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $user = $request->user();
        if (! $this->canAccessAccounting($user)) {
            abort(403, "Action non autorisée.");
        }

        $month = $request->query('month', now()->format('Y-m'));

        $response = $this->index($request);
        $content = json_decode($response->getContent(), true);
        $rows = $content['payrolls'] ?? [];

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"etat_de_paie_{$month}.csv\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            // BOM UTF-8 pour Excel
            fputs($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Employé',
                'Email',
                'Téléphone',
                'Département',
                'Poste',
                'Salaire de Base (FCFA)',
                'Primes (FCFA)',
                'Retenues (FCFA)',
                'Rémunération Nette (FCFA)',
                'Statut RH',
                'Statut Paiement',
                'Date de Paiement',
                'Référence Virement',
            ], ';');

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['name'],
                    $row['email'],
                    $row['phone'] ?? '—',
                    $row['department_name'],
                    $row['position_title'],
                    number_format($row['base_salary'], 0, ',', ' '),
                    number_format($row['bonus'], 0, ',', ' '),
                    number_format($row['deductions'], 0, ',', ' '),
                    number_format($row['net_salary'], 0, ',', ' '),
                    $row['rh_status'] === 'validated_by_rh' ? 'Validé par RH' : 'Brouillon RH',
                    $row['payment_status'] === 'paid' ? 'Payé' : 'Non payé',
                    $row['paid_at'] ?? '—',
                    $row['payment_reference'] ?? '—',
                ], ';');
            }

            fclose($handle);
        }, 200, $headers);
    }

    private function canAccessAccounting(User $user): bool
    {
        return $user->isAdmin()
            || $user->hasPermission('comptable')
            || $user->hasPermission('accounting')
            || $user->hasPermission('rh')
            || in_array($user->role, [User::ROLE_SUPER_ADMIN, User::ROLE_COMPANY_ADMIN, User::ROLE_DIRECTEUR, User::ROLE_RH, User::ROLE_COMPTABLE], true);
    }

    private function canManageRhPayroll(User $user): bool
    {
        return $user->isAdmin()
            || $user->hasPermission('rh')
            || $user->hasPermission('manage_payroll')
            || in_array($user->role, [User::ROLE_SUPER_ADMIN, User::ROLE_COMPANY_ADMIN, User::ROLE_DIRECTEUR, User::ROLE_RH], true);
    }

    private function canMarkAsPaid(User $user): bool
    {
        return $user->isAdmin()
            || $user->hasPermission('comptable')
            || $user->hasPermission('accounting')
            || in_array($user->role, [User::ROLE_SUPER_ADMIN, User::ROLE_COMPANY_ADMIN, User::ROLE_DIRECTEUR, User::ROLE_COMPTABLE], true);
    }
}
