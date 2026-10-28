<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Models\Payroll;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $user = $request->user();
        if (! $this->canAccessAccounting($user)) {
            abort(403, "Vous n'avez pas accès à la session comptable.");
        }

        $tz = CompanySetting::get('timezone', config('app.timezone', 'Africa/Porto-Novo'));
        $month = $request->query('month', Carbon::now($tz)->format('Y-m'));

        $from = Carbon::parse($month.'-01')->startOfMonth();
        $to = $from->copy()->endOfMonth();

        // Charger tous les employés avec département et postes
        $employees = User::with([
            'department:id,name',
            'positions' => function ($q) {
                $q->withPivot('is_primary');
            },
        ])
        ->where('status', '!=', 'blocked')
        ->orderBy('name')
        ->get();

        $payrolls = Payroll::where('month', $month)
            ->with(['rhValidator:id,name', 'paidBy:id,name'])
            ->get()
            ->keyBy('user_id');

        $rows = [];
        $totalMass = 0;
        $totalPaid = 0;
        $totalPending = 0;
        $countValidated = 0;
        $countPaid = 0;

        foreach ($employees as $employee) {
            $payroll = $payrolls->get($employee->id);

            // Statistiques de pointage du mois
            $attendancesCount = Attendance::where('user_id', $employee->id)
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->count();

            $lateCount = Attendance::where('user_id', $employee->id)
                ->where('status', 'late')
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->count();

            $unexcusedAbsences = Absence::where('user_id', $employee->id)
                ->where('status', 'unexcused')
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->count();

            $primaryPos = $employee->positions->firstWhere('pivot.is_primary', true) ?: $employee->positions->first();

            $baseSalary = $payroll ? (float) $payroll->base_salary : 0;
            $bonus = $payroll ? (float) $payroll->bonus : 0;
            $deductions = $payroll ? (float) $payroll->deductions : 0;
            $netSalary = $payroll ? (float) $payroll->net_salary : max(0, $baseSalary + $bonus - $deductions);
            $rhStatus = $payroll ? $payroll->rh_status : Payroll::RH_STATUS_DRAFT;
            $paymentStatus = $payroll ? $payroll->payment_status : Payroll::PAYMENT_STATUS_UNPAID;

            if ($rhStatus === Payroll::RH_STATUS_VALIDATED) {
                $countValidated++;
                $totalMass += $netSalary;
                if ($paymentStatus === Payroll::PAYMENT_STATUS_PAID) {
                    $totalPaid += $netSalary;
                    $countPaid++;
                } else {
                    $totalPending += $netSalary;
                }
            }

            $rows[] = [
                'id' => $payroll?->id,
                'user_id' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
                'department_name' => $employee->department?->name ?? '—',
                'position_title' => $primaryPos?->title ?? $employee->job_title ?? 'Employé',
                'base_salary' => $baseSalary,
                'bonus' => $bonus,
                'deductions' => $deductions,
                'net_salary' => $netSalary,
                'rh_status' => $rhStatus,
                'rh_validated_at' => $payroll?->rh_validated_at?->format('Y-m-d H:i'),
                'rh_validator_name' => $payroll?->rhValidator?->name,
                'payment_status' => $paymentStatus,
                'paid_at' => $payroll?->paid_at?->format('Y-m-d H:i'),
                'payment_reference' => $payroll?->payment_reference,
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
            'month' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'bonus' => ['nullable', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'numeric', 'min:0'],
            'rh_status' => ['nullable', 'in:draft,validated_by_rh'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $baseSalary = (float) $data['base_salary'];
        $bonus = (float) ($data['bonus'] ?? 0);
        $deductions = (float) ($data['deductions'] ?? 0);
        $netSalary = max(0, $baseSalary + $bonus - $deductions);
        $rhStatus = $data['rh_status'] ?? Payroll::RH_STATUS_DRAFT;

        $updateData = [
            'base_salary' => $baseSalary,
            'bonus' => $bonus,
            'deductions' => $deductions,
            'net_salary' => $netSalary,
            'rh_status' => $rhStatus,
            'notes' => $data['notes'] ?? null,
        ];

        if ($rhStatus === Payroll::RH_STATUS_VALIDATED) {
            $updateData['rh_validated_at'] = now();
            $updateData['rh_validator_id'] = $user->id;
        }

        $payroll = Payroll::updateOrCreate(
            ['user_id' => $data['user_id'], 'month' => $data['month']],
            $updateData
        );

        AuditLog::record(
            userId: $user->id,
            targetUserId: $payroll->user_id,
            action: $rhStatus === Payroll::RH_STATUS_VALIDATED ? 'payroll.validated_by_rh' : 'payroll.rh_draft_saved',
            details: [
                'month' => $payroll->month,
                'base_salary' => $payroll->base_salary,
                'bonus' => $payroll->bonus,
                'deductions' => $payroll->deductions,
                'net_salary' => $payroll->net_salary,
                'status' => $payroll->rh_status,
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

        if ($payroll->rh_status !== Payroll::RH_STATUS_VALIDATED) {
            return response()->json([
                'message' => "Cette fiche de paie doit d'abord être validée par le service RH avant tout versement.",
            ], 422);
        }

        $data = $request->validate([
            'payment_reference' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $payroll->update([
            'payment_status' => Payroll::PAYMENT_STATUS_PAID,
            'paid_at' => now(),
            'payment_reference' => $data['payment_reference'],
            'paid_by' => $user->id,
            'notes' => $data['notes'] ?? $payroll->notes,
        ]);

        AuditLog::record(
            userId: $user->id,
            targetUserId: $payroll->user_id,
            action: 'payroll.marked_as_paid',
            details: [
                'payroll_id' => $payroll->id,
                'month' => $payroll->month,
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
            || in_array($user->role, [User::ROLE_DIRECTEUR, User::ROLE_RH, User::ROLE_COMPTABLE], true);
    }

    private function canManageRhPayroll(User $user): bool
    {
        return $user->isAdmin()
            || $user->hasPermission('rh')
            || $user->hasPermission('manage_payroll')
            || in_array($user->role, [User::ROLE_DIRECTEUR, User::ROLE_RH], true);
    }

    private function canMarkAsPaid(User $user): bool
    {
        return $user->isAdmin()
            || $user->hasPermission('comptable')
            || $user->hasPermission('accounting')
            || in_array($user->role, [User::ROLE_DIRECTEUR, User::ROLE_COMPTABLE], true);
    }
}
