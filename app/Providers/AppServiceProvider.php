<?php

namespace App\Providers;

use App\Models\Absence;
use App\Models\Attendance;
use App\Models\CompanySetting;
use App\Models\Department;
use App\Models\ExplanationRequest;
use App\Models\Leave;
use App\Models\Schedule;
use App\Models\User;
use App\Models\WorkPattern;
use App\Policies\AbsencePolicy;
use App\Policies\AttendancePolicy;
use App\Policies\CompanySettingPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\ExplanationRequestPolicy;
use App\Policies\LeavePolicy;
use App\Policies\SchedulePolicy;
use App\Policies\UserPolicy;
use App\Policies\WorkPatternPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 1. Enregistrement explicite des Policies de sécurité (Anti-IDOR & RBAC)
        Gate::policy(Attendance::class, AttendancePolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Absence::class, AbsencePolicy::class);
        Gate::policy(ExplanationRequest::class, ExplanationRequestPolicy::class);
        Gate::policy(CompanySetting::class, CompanySettingPolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(Leave::class, LeavePolicy::class);
        Gate::policy(Schedule::class, SchedulePolicy::class);
        Gate::policy(WorkPattern::class, WorkPatternPolicy::class);

        // 2. Limiteurs de débit stricts (Anti-Brute Force et Anti-DDoS applicatif)
        
        // Authentification : max 5 tentatives par minute par IP avec message explicite
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = 'login|' . ($request->ip() ?: 'unknown');

            return Limit::perMinute(5)->by($throttleKey)->response(function () {
                return response()->json([
                    'message' => 'Trop de tentatives de connexion. Par mesure de sécurité, veuillez patienter 1 minute.',
                ], 429);
            });
        });

        // Scan et pointage : max 30 requêtes par minute par IP ou employé
        RateLimiter::for('scan', function (Request $request) {
            $key = $request->user()?->id ? 'user:' . $request->user()->id : 'ip:' . $request->ip();

            return Limit::perMinute(30)->by($key)->response(function () {
                return response()->json([
                    'message' => 'Fréquence de pointage trop élevée. Veuillez patienter.',
                ], 429);
            });
        });

        // Synchronisation par lot (borne / daemon) : max 60 requêtes par minute
        RateLimiter::for('sync-batch', function (Request $request) {
            $key = 'sync|' . ($request->ip() ?: 'global');

            return Limit::perMinute(60)->by($key)->response(function () {
                return response()->json([
                    'message' => 'Limite de requêtes de synchronisation atteinte. Veuillez temporiser l\'envoi des lots.',
                ], 429);
            });
        });

        // Heartbeat des bornes : max 60 signaux par minute par IP
        RateLimiter::for('kiosk-heartbeat', function (Request $request) {
            $key = 'heartbeat|' . ($request->ip() ?: 'global');

            return Limit::perMinute(60)->by($key)->response(function () {
                return response()->json([
                    'message' => 'Fréquence de heartbeat trop élevée.',
                ], 429);
            });
        });

        // 3. Application dynamique des paramètres d'e-mails (SMTP) configurés en base
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('company_settings')) {
                $mailMailer = CompanySetting::get('mail_mailer');
                if ($mailMailer) {
                    config(['mail.default' => $mailMailer]);
                }
                $mailHost = CompanySetting::get('mail_host');
                if ($mailHost) {
                    $scheme = CompanySetting::get('mail_encryption', 'tls');
                    if ($scheme === 'none' || $scheme === 'null' || ! $scheme) {
                        $scheme = null;
                    }
                    config([
                        'mail.mailers.smtp.host' => $mailHost,
                        'mail.mailers.smtp.port' => (int) CompanySetting::get('mail_port', 587),
                        'mail.mailers.smtp.scheme' => $scheme,
                        'mail.mailers.smtp.username' => CompanySetting::get('mail_username'),
                        'mail.mailers.smtp.password' => CompanySetting::get('mail_password'),
                    ]);
                }
                $fromAddress = CompanySetting::get('mail_from_address');
                if ($fromAddress) {
                    config([
                        'mail.from.address' => $fromAddress,
                        'mail.from.name' => CompanySetting::get('mail_from_name', CompanySetting::get('company_name', config('app.name'))),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // Ignorer silencieusement si la base n'est pas encore initialisée
        }
    }
}
