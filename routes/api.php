<?php

use App\Http\Controllers\Api\AbsenceController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BadgeController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CompanySettingController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\ExplanationRequestController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\KioskHeartbeatController;
use App\Http\Controllers\Api\KioskSecurityController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\SuperAdminController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WorkPatternController;
use Illuminate\Support\Facades\Route;

// --- Routes publiques ---
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');
Route::post('/register-company', [AuthController::class, 'registerCompany'])->middleware('throttle:login');
Route::get('/plans', [PlanController::class, 'index']);

// --- Synchronisation hors-ligne & Heartbeat borne (secret partagé / session admin) ---
Route::middleware('offline-sync')->group(function () {
    Route::post('/attendance/sync-offline', [AttendanceController::class, 'syncOffline'])->middleware('throttle:sync-batch');
    Route::get('/attendance/valid-badges', [AttendanceController::class, 'validBadges']);
    Route::post('/kiosk/heartbeat', [KioskHeartbeatController::class, 'heartbeat'])->middleware('throttle:kiosk-heartbeat');
});

// --- Routes protégées (nécessitent un token Bearer valide) ---
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // --- Profil & Abonnements de l'entreprise courante ---
    Route::get('/company/profile', [CompanyController::class, 'profile']);
    Route::put('/company/profile', [CompanyController::class, 'updateProfile']);
    Route::get('/company/subscription', [CompanyController::class, 'subscription']);
    Route::post('/company/subscription/change', [CompanyController::class, 'changeSubscription']);
    Route::get('/company/payments', [CompanyController::class, 'payments']);

    // --- Module Multi-Agences (Branches) ---
    Route::get('/branches', [BranchController::class, 'index']);
    Route::get('/branches/{branch}', [BranchController::class, 'show']);
    Route::middleware('role:company_admin,branch_manager,directeur,super_admin')->group(function () {
        Route::post('/branches', [BranchController::class, 'store']);
        Route::put('/branches/{branch}', [BranchController::class, 'update']);
        Route::delete('/branches/{branch}', [BranchController::class, 'destroy']);
        Route::post('/branches/{branch}/assign-manager', [BranchController::class, 'assignManager']);
    });

    // --- Module Super-Admin (plateforme multi-entreprises, forfaits, historique) ---
    Route::middleware('role:super_admin')->prefix('super-admin')->group(function () {
        Route::get('/stats', [SuperAdminController::class, 'stats']);
        Route::get('/companies', [SuperAdminController::class, 'companies']);
        Route::post('/companies', [SuperAdminController::class, 'storeCompany']);
        Route::get('/companies/{company}', [SuperAdminController::class, 'showCompany']);
        Route::put('/companies/{company}', [SuperAdminController::class, 'updateCompany']);
        Route::patch('/companies/{company}/access', [SuperAdminController::class, 'updateAccess']);
        Route::post('/companies/{company}/subscriptions', [SuperAdminController::class, 'assignSubscription']);

        Route::get('/plans', [SuperAdminController::class, 'plans']);
        Route::post('/plans', [SuperAdminController::class, 'storePlan']);
        Route::put('/plans/{plan}', [SuperAdminController::class, 'updatePlan']);
        Route::delete('/plans/{plan}', [SuperAdminController::class, 'deletePlan']);

        Route::get('/payments', [SuperAdminController::class, 'payments']);
        Route::post('/payments', [SuperAdminController::class, 'storePayment']);

        Route::get('/histories', [SuperAdminController::class, 'histories']);
    });

    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::get('/departments', [DepartmentController::class, 'index']);
    Route::patch('/employees/me/status', [EmployeeController::class, 'updateOwnStatus']);
    Route::get('/absences', [AbsenceController::class, 'index']);
    Route::get('/attendances', [AttendanceController::class, 'index']);
    Route::get('/explanation-requests', [ExplanationRequestController::class, 'index']);
    Route::post('/explanation-requests/{explanationRequest}/respond', [ExplanationRequestController::class, 'respond']);

    Route::middleware('role:rh,directeur,company_admin')->group(function () {
        Route::post('/employees', [EmployeeController::class, 'store']);
        Route::put('/employees/{user}', [EmployeeController::class, 'update']);
        Route::delete('/employees/{user}', [EmployeeController::class, 'destroy']);
        Route::post('/departments', [DepartmentController::class, 'store']);
        Route::put('/departments/{department}', [DepartmentController::class, 'update']);
        Route::patch('/departments/{department}', [DepartmentController::class, 'update']);
        Route::delete('/departments/{department}', [DepartmentController::class, 'destroy']);
        Route::post('/employees/{user}/badge-pairing', [BadgeController::class, 'startPairing']);

        Route::get('/company-settings', [CompanySettingController::class, 'index']);
        Route::put('/company-settings', [CompanySettingController::class, 'update']);

        Route::patch('/absences/{absence}/justify', [AbsenceController::class, 'justify']);
        Route::get('/dashboard/rh-summary', [StatsController::class, 'rhSummary']);
        Route::get('/kiosk/heartbeats', [KioskHeartbeatController::class, 'index']);
    });

    Route::middleware('role:comptable,directeur,company_admin')->group(function () {
        Route::get('/dashboard/accounting-summary', [StatsController::class, 'accountingSummary']);
        Route::get('/exports/attendances.csv', [ExportController::class, 'attendancesCsv']);
    });

    Route::middleware('role:rh,directeur,company_admin')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
    });

    // --- Présences, retards, absences, congés ---
    Route::apiResource('schedules', ScheduleController::class);
    Route::apiResource('work-patterns', WorkPatternController::class)->except(['show']);
    Route::apiResource('leaves', LeaveController::class)->except(['show']);
    Route::post('attendance/check-in', [AttendanceController::class, 'checkIn'])->middleware('throttle:scan');
    Route::post('attendance/check-out', [AttendanceController::class, 'checkOut'])->middleware('throttle:scan');
    Route::post('/employees/{user}/ensure-badge', [BadgeController::class, 'ensureBadge']);

    // --- Sécurité borne de pointage ---
    Route::post('/kiosk/verify-access', [KioskSecurityController::class, 'verifyAccess']);
});