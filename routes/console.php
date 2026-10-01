<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('attendance:check-absences')
    ->everyFifteenMinutes()
    ->between('07:00', '20:00');

Schedule::command('attendance:check-explanations')
    ->everyMinute()
    ->between('06:00', '20:00');