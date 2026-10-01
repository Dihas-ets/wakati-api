<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $dbOk = false;
    $dbDriver = 'sqlite';

    try {
        DB::connection()->getPdo();
        $dbOk = true;
        $dbDriver = DB::connection()->getDriverName();
    } catch (\Throwable $e) {
        $dbOk = false;
    }

    if (request()->wantsJson()) {
        return response()->json([
            'service' => 'Wakati API',
            'status' => $dbOk ? 'healthy' : 'degraded',
            'message' => 'Wakati API Gateway is operational.',
            'environment' => app()->environment(),
            'database' => $dbOk ? 'connected' : 'disconnected',
            'timestamp' => now()->toIso8601String(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ], $dbOk ? 200 : 503);
    }

    return view('welcome', [
        'dbOk' => $dbOk,
        'dbDriver' => $dbDriver,
        'phpVersion' => PHP_VERSION,
        'laravelVersion' => app()->version(),
        'environment' => app()->environment(),
        'timestamp' => now(),
    ]);
});
