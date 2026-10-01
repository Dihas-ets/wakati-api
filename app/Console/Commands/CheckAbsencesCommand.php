<?php

namespace App\Console\Commands;

use App\Models\Absence;
use App\Models\Attendance;
use App\Models\CompanySetting;
use App\Models\Leave;
use App\Models\Schedule;
use App\Models\WorkPattern;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckAbsencesCommand extends Command
{
    protected $signature = 'attendance:check-absences';
    protected $description = 'Détecte et enregistre automatiquement les absences (créneaux ponctuels ou récurrents) dépassés sans pointage.';

    public function handle()
    {
        $now = Carbon::now(CompanySetting::get('timezone', config('app.timezone')));
        $today = $now->toDateString();

        $delaySetting = CompanySetting::where('key', 'absence_delay_minutes')->first();
        $absenceDelay = $delaySetting ? (int) $delaySetting->value : 60;

        $absencesCount = 0;

        // Employés déjà couverts aujourd'hui : pointage, absence déjà
        // enregistrée, OU congé déclaré (ils ne sont pas censés travailler).
        $alreadyHandledUserIds = Attendance::whereDate('date', $today)->pluck('user_id')
            ->merge(Absence::whereDate('date', $today)->pluck('user_id'))
            ->merge(Leave::whereDate('date', $today)->where('status', Leave::STATUS_APPROVED)->pluck('user_id'))
            ->unique();

        // 1. Exceptions ponctuelles du jour.
        $todaysSchedules = Schedule::whereDate('date', $today)
            ->whereNotIn('user_id', $alreadyHandledUserIds)
            ->get();

        foreach ($todaysSchedules as $schedule) {
            $scheduledStart = Carbon::parse($today.' '.$schedule->start_time);

            if ($now->greaterThanOrEqualTo($scheduledStart->copy()->addMinutes($absenceDelay))) {
                Absence::create([
                    'user_id' => $schedule->user_id,
                    'schedule_id' => $schedule->id,
                    'date' => $today,
                    'status' => 'unexcused',
                    'reason' => "Absence automatique : Aucun pointage enregistré après le délai d'attente.",
                ]);

                $alreadyHandledUserIds->push($schedule->user_id);
                $absencesCount++;
            }
        }

        // 2. Plannings récurrents actifs aujourd'hui, pour le reste des employés.
        $activePatterns = WorkPattern::where('day_of_week', $now->dayOfWeek)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $today);
            })
            ->whereNotIn('user_id', $alreadyHandledUserIds)
            ->get();

        foreach ($activePatterns as $pattern) {
            $scheduledStart = Carbon::parse($today.' '.$pattern->start_time);

            if ($now->greaterThanOrEqualTo($scheduledStart->copy()->addMinutes($absenceDelay))) {
                Absence::create([
                    'user_id' => $pattern->user_id,
                    'work_pattern_id' => $pattern->id,
                    'date' => $today,
                    'status' => 'unexcused',
                    'reason' => "Absence automatique : Aucun pointage enregistré après le délai d'attente.",
                ]);

                $absencesCount++;
            }
        }

        $this->info("Détection terminée : {$absencesCount} absence(s) enregistrée(s).");
    }
}