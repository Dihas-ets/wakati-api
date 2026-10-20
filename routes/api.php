<?php

use App\Http\Controllers\Api\AbsenceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BadgeController;
use App\Http\Controllers\Api\CompanySettingController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\WorkPatternController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\ExplanationRequestController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\KioskHeartbeatController;
use App\Http\Controllers\Api\KioskSecurityController;
use App\Http\Controllers\Api\MeetingController;
use App\Http\Controllers\Api\KanbanTaskController;
use App\Http\Controllers\Api\TenantRegistrationController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\CompanyProfileController;
use App\Http\Controllers\Api\SuperAdminController;
use Illuminate\Support\Facades\Route;

// --- Forfaits publics & Inscription Tenant (publique) ---
Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');
Route::post('/register-tenant', [TenantRegistrationController::class, 'registerCompany'])->name('register-tenant');
Route::post('/register-company', [TenantRegistrationController::class, 'registerCompany'])->name('register-company');

// --- Synchronisation hors-ligne & Heartbeat borne (secret partagé / session admin) ---
Route::middleware('offline-sync')->group(function () {
    Route::post('/attendance/sync-offline', [AttendanceController::class, 'syncOffline'])->middleware('throttle:sync-batch');
    Route::get('/attendance/valid-badges', [AttendanceController::class, 'validBadges']);
    Route::post('/kiosk/heartbeat', [KioskHeartbeatController::class, 'heartbeat'])->middleware('throttle:kiosk-heartbeat');
});

// --- Téléchargement sécurisé document Word (Token Bearer OU paramètre ?token=) ---
Route::get('/meetings/{meeting}/download-docx', [MeetingController::class, 'downloadDocx']);

// --- Routes protégées (nécessitent un token Bearer valide) ---
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::get('/departments', [DepartmentController::class, 'index']);
    Route::patch('/employees/me/status', [EmployeeController::class, 'updateOwnStatus']);
    Route::get('/absences', [AbsenceController::class, 'index']);
    Route::get('/attendances', [AttendanceController::class, 'index']);
    Route::get('/explanation-requests', [ExplanationRequestController::class, 'index']);
    Route::post('/explanation-requests/{explanationRequest}/respond', [ExplanationRequestController::class, 'respond']);

    Route::middleware('role:rh,directeur')->group(function () {
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
        Route::post('/company-settings/test-email', [CompanySettingController::class, 'testEmail']);

        Route::patch('/absences/{absence}/justify', [AbsenceController::class, 'justify']);
        Route::get('/dashboard/rh-summary', [StatsController::class, 'rhSummary']);
        Route::get('/kiosk/heartbeats', [KioskHeartbeatController::class, 'index']);
    });

    Route::middleware('role:comptable,directeur')->group(function () {
        Route::get('/dashboard/accounting-summary', [StatsController::class, 'accountingSummary']);
        Route::get('/exports/attendances.csv', [ExportController::class, 'attendancesCsv']);
    });

    Route::middleware('role:rh,directeur')->group(function () {
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

    // --- Module Gestion des Pauses (Break Management) ---
    Route::post('attendance/break-start', [AttendanceController::class, 'startBreak'])->middleware('throttle:scan');
    Route::post('attendance/break-end', [AttendanceController::class, 'endBreak'])->middleware('throttle:scan');
    Route::post('breaks/start', [AttendanceController::class, 'startBreak'])->middleware('throttle:scan');
    Route::post('breaks/end', [AttendanceController::class, 'endBreak'])->middleware('throttle:scan');
    Route::get('breaks/current', [AttendanceController::class, 'currentBreak']);

    Route::prefix('v1')->group(function () {
        Route::post('breaks/start', [AttendanceController::class, 'startBreak'])->middleware('throttle:scan');
        Route::post('breaks/end', [AttendanceController::class, 'endBreak'])->middleware('throttle:scan');
        Route::get('breaks/current', [AttendanceController::class, 'currentBreak']);
    });

    Route::post('/employees/{user}/ensure-badge', [BadgeController::class, 'ensureBadge']);

    // --- Sécurité borne de pointage ---
    Route::post('/kiosk/verify-access', [KioskSecurityController::class, 'verifyAccess']);

    // --- Module Gestion des Réunions & Émargement Jour J ---
    Route::get('/meetings/today', [MeetingController::class, 'today']);
    Route::post('/meetings/{meeting}/self-check-in', [MeetingController::class, 'selfCheckIn']);
    Route::post('/meetings/{meeting}/check-in', [MeetingController::class, 'checkIn']);
    Route::post('/meetings/{meeting}/send-convocations', [MeetingController::class, 'sendConvocations']);
    Route::post('/meetings/{meeting}/topics', [MeetingController::class, 'addTopic']);
    Route::post('/meetings/{meeting}/topics/{topic}/convert-to-task', [MeetingController::class, 'convertTopicToTask']);
    Route::post('/meetings/{meeting}/generate-docx', [MeetingController::class, 'generateDocx']);
    Route::post('/meetings/{meeting}/upload-docx', [MeetingController::class, 'uploadDocx']);
    Route::post('/meetings/{meeting}/start', [MeetingController::class, 'startLiveSession']);
    Route::post('/meetings/{meeting}/pause', [MeetingController::class, 'pauseLiveSession']);
    Route::post('/meetings/{meeting}/close-and-send-reports', [MeetingController::class, 'closeAndSendReports']);
    Route::apiResource('meetings', MeetingController::class);

    // --- Module Suivi Tâches Kanban ---
    Route::patch('/kanban-tasks/{kanban_task}/status', [KanbanTaskController::class, 'updateStatus']);
    Route::apiResource('kanban-tasks', KanbanTaskController::class);

    // --- Gestion du profil entreprise & abonnements (Company Admin) ---
    Route::get('/company/profile', [CompanyProfileController::class, 'profile']);
    Route::put('/company/profile', [CompanyProfileController::class, 'updateProfile']);
    Route::get('/company/subscription', [CompanyProfileController::class, 'subscription']);
    Route::post('/company/subscription/change', [CompanyProfileController::class, 'changeSubscription']);
    Route::get('/company/payments', [CompanyProfileController::class, 'payments']);

    // --- Espace Super-Admin (Gestion Plateforme SaaS) ---
    Route::middleware('role:super_admin')->prefix('super-admin')->group(function () {
        Route::get('/stats', [SuperAdminController::class, 'stats']);
        Route::get('/companies', [SuperAdminController::class, 'companies']);
        Route::get('/companies/{company}', [SuperAdminController::class, 'companyDetails']);
        Route::patch('/companies/{company}/access', [SuperAdminController::class, 'updateAccess']);
        Route::put('/companies/{company}', [SuperAdminController::class, 'updateCompany']);
        Route::post('/companies/{company}/subscriptions', [SuperAdminController::class, 'assignSubscription']);
        Route::get('/plans', [SuperAdminController::class, 'plans']);
        Route::post('/plans', [SuperAdminController::class, 'createPlan']);
        Route::put('/plans/{plan}', [SuperAdminController::class, 'updatePlan']);
        Route::delete('/plans/{plan}', [SuperAdminController::class, 'deletePlan']);
        Route::get('/payments', [SuperAdminController::class, 'payments']);
        Route::post('/payments', [SuperAdminController::class, 'storePayment']);
        Route::get('/histories', [SuperAdminController::class, 'histories']);
    });
});