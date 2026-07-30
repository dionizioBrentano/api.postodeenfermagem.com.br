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

        // Rota de pacientes (nova para o teste do Sprint 2)
        Route::get('/patients', [\App\Http\Controllers\PatientController::class, 'index']);
    });


    // ==========================================
    // PROTECTED API ROUTES (App + User + Tenant + Scopes)
    // ==========================================

    Route::middleware(['require_app_token', 'auth:sanctum', 'tenant'])->group(function () {
        
        // Exemplo: rota que exige o scope 'patient:read'
        Route::get('/tenant-test', function () {
            $tenant = app('tenant');
            $user = request()->user();
            return response()->json([
                'message' => 'Acesso Duplo Concedido! Tenant + User identificados.',
                'tenant' => $tenant->name,
                'user' => $user->name,
                'scopes' => $user->currentAccessToken()->abilities,
            ]);
        })->middleware('ability:patient:read');

    });
});
