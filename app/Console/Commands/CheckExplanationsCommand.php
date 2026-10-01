<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\CompanySetting;
use App\Models\ExplanationRequest;
use App\Models\Leave;
use App\Models\Schedule;
use App\Models\WorkPattern;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckExplanationsCommand extends Command
{
    protected $signature = 'attendance:check-explanations';
    protected $description = "Envoie une demande d'explication aux employés qui n'ont pas encore pointé quelques minutes avant leur créneau.";

    public function handle()
    {
        $now = Carbon::now(CompanySetting::get('timezone', config('app.timezone')));
        $today = $now->toDateString();

        $delaySetting = CompanySetting::where('key', 'explanation_request_minutes_before')->first();
        $minutesBefore = $delaySetting ? (int) $delaySetting->value : 5;

        $alreadyHandled = Attendance::where('date', $today)->pluck('user_id')
            ->merge(Leave::where('date', $today)->pluck('user_id'))
            ->merge(ExplanationRequest::where('date', $today)->pluck('user_id'))
            ->unique();

        $count = 0;

        // Exceptions ponctuelles du jour.
        $todaysSchedules = Schedule::where('date', $today)->whereNotIn('user_id', $alreadyHandled)->get();
        foreach ($todaysSchedules as $schedule) {
            $start = Carbon::parse($today.' '.$schedule->start_time);
            $triggerAt = $start->copy()->subMinutes($minutesBefore);

            if ($now->greaterThanOrEqualTo($triggerAt) && $now->lessThan($start)) {
                ExplanationRequest::create([
                    'user_id' => $schedule->user_id,
                    'schedule_id' => $schedule->id,
                    'date' => $today,
                    'sent_at' => $now,
                ]);
                $alreadyHandled->push($schedule->user_id);
                $count++;
            }
        }

        // Plannings récurrents actifs aujourd'hui.
        $activePatterns = WorkPattern::where('day_of_week', $now->dayOfWeek)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $today);
            })
            ->whereNotIn('user_id', $alreadyHandled)
            ->get();

        foreach ($activePatterns as $pattern) {
            $start = Carbon::parse($today.' '.$pattern->start_time);
            $triggerAt = $start->copy()->subMinutes($minutesBefore);

            if ($now->greaterThanOrEqualTo($triggerAt) && $now->lessThan($start)) {
                ExplanationRequest::create([
                    'user_id' => $pattern->user_id,
                    'work_pattern_id' => $pattern->id,
                    'date' => $today,
                    'sent_at' => $now,
                ]);
                $count++;
            }
        }

        $this->info("{$count} demande(s) d'explication envoyée(s).");
    }
}