<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ApplicationAuthController;

Route::prefix('v1')->group(function () {
    
    // Rota pública de Health Check
    Route::get('/health', function () {
        $dbStatus = true;
        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            $dbStatus = false;
        }

        return response()->json([
            'status' => $dbStatus ? 'ok' : 'degraded',
            'api_version' => '1.0.0',
            'timestamp' => now()->toIso8601String(),
            'database' => $dbStatus ? 'connected' : 'disconnected',
        ], $dbStatus ? 200 : 503);
    });

    // ==========================================
    // AUTHENTICATION ROUTES
    // ==========================================

    // Application Auth (M2M)
    Route::post('/auth/application/token', [ApplicationAuthController::class, 'token']);

    // User Login (Público)
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Requer Autenticação Básica (MFA Steps, Logout)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', function (Request $request) {
            return $request->user();
        });

        Route::post('/auth/mfa/setup', [AuthController::class, 'setupMfa']);
        Route::post('/auth/mfa/verify', [AuthController::class, 'verifyMfa']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // ==========================================
        // PATIENT ROUTES (Sprint 2 - dados criptografados por registro)
        // ==========================================
        Route::middleware('ability:patient:read')->group(function () {
            Route::get('/patients', [\App\Http\Controllers\PatientController::class, 'index']);
            Route::get('/patients/{id}', [\App\Http\Controllers\PatientController::class, 'show']);
            Route::get('/patients/lookup/cpf/{cpf}', [\App\Http\Controllers\PatientController::class, 'findByCpf']);
        });

        Route::middleware('ability:patient:write')->group(function () {
            Route::post('/patients', [\App\Http\Controllers\PatientController::class, 'store']);
        });
    });
});
