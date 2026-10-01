<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\Attendance;
use App\Models\CompanySetting;
use App\Models\Leave;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    /**
     * GET /api/dashboard/rh-summary
     */
    public function rhSummary()
    {
        $today = Carbon::now(CompanySetting::get('timezone', config('app.timezone')))->toDateString();

        return response()->json([
            'total_employees' => User::whereIn('role', ['rh', 'comptable', 'directeur', 'employe'])->count(),
            'present_today' => Attendance::where('date', $today)->count(),
            'late_today' => Attendance::where('date', $today)->where('status', 'late')->count(),
            'absent_today' => Absence::where('date', $today)->where('status', 'unexcused')->count(),
            'leaves_today' => Leave::where('date', $today)->count(),
            'pending_absences' => Absence::where('status', 'unexcused')->count(),
        ]);
    }

    /**
     * GET /api/dashboard/accounting-summary?month=YYYY-MM
     */
    public function accountingSummary(Request $request)
    {
        $month = $request->query('month', Carbon::now(CompanySetting::get('timezone', config('app.timezone')))->format('Y-m'));
        $from = Carbon::parse($month.'-01')->startOfMonth();
        $to = $from->copy()->endOfMonth();

        $employees = User::whereIn('role', ['employe', 'rh', 'comptable'])->get(['id', 'name']);

        $summary = $employees->map(function ($employee) use ($from, $to) {
            $attendances = Attendance::where('user_id', $employee->id)
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->get();

            $unexcusedAbsences = Absence::where('user_id', $employee->id)
                ->where('status', 'unexcused')
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->count();

            return [
                'user_id' => $employee->id,
                'name' => $employee->name,
                'days_present' => $attendances->count(),
                'days_late' => $attendances->where('status', 'late')->count(),
                'unexcused_absences' => $unexcusedAbsences,
            ];
        });

        return response()->json([
            'month' => $month,
            'employees' => $summary,
        ]);
    }
}