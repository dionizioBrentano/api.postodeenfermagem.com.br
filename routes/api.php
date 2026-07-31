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

    // User Registration & Login (Público, mas exige tenant)
    Route::middleware('tenant')->group(function () {
        Route::post('/auth/register', [AuthController::class, 'register']);
        Route::post('/auth/login', [AuthController::class, 'login']);
    });

    // Requer Autenticação Básica (MFA Steps, Logout)
    Route::middleware(['tenant', 'auth:sanctum'])->group(function () {
        Route::get('/user', function (\Illuminate\Http\Request $request) {
            return $request->user();
        });

        Route::post('/auth/mfa/setup', [AuthController::class, 'setupMfa']);
        Route::post('/auth/mfa/verify', [AuthController::class, 'verifyMfa']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // ==========================================
        // PATIENT ROUTES (Sprint 2 - dados criptografados por registro)
        // "tenant" middleware aqui garante que o global scope da HasTenant
        // realmente filtre por tenant (sem ele, app('tenant') nunca fica
        // definido nessas rotas e o isolamento multi-tenant não é aplicado).
        // ==========================================
        Route::middleware(['tenant', 'ability:patient:read'])->group(function () {
            Route::get('/patients', [\App\Http\Controllers\PatientController::class, 'index']);
            Route::get('/patients/{id}', [\App\Http\Controllers\PatientController::class, 'show'])->middleware('lgpd.consent');
            Route::get('/patients/lookup/cpf/{cpf}', [\App\Http\Controllers\PatientController::class, 'findByCpf'])->middleware('lgpd.consent');
            
            // Vínculos Profissional-Paciente (Read)
            Route::get('/patients/{patient}/professionals', [\App\Http\Controllers\PatientUserController::class, 'index'])->middleware('lgpd.consent');
            Route::get('/patients/{patient}/professionals/{id}', [\App\Http\Controllers\PatientUserController::class, 'show'])->middleware('lgpd.consent');
            
            // LGPD Consents (Read)
            Route::get('/patients/{patient}/consents', [\App\Http\Controllers\ConsentController::class, 'index']);
            Route::get('/patients/{patient}/consents/{id}', [\App\Http\Controllers\ConsentController::class, 'show']);
            
            // PEP - Prontuário (Read - com LGPD Consent)
            Route::middleware('lgpd.consent')->group(function () {
                // Encounters
                Route::get('/patients/{patient}/encounters', [\App\Http\Controllers\EncounterController::class, 'index']);
                Route::get('/patients/{patient}/encounters/{id}', [\App\Http\Controllers\EncounterController::class, 'show']);
                
                // Observations
                Route::get('/patients/{patient}/encounters/{encounter}/observations', [\App\Http\Controllers\ObservationController::class, 'index']);
                Route::get('/patients/{patient}/encounters/{encounter}/observations/{id}', [\App\Http\Controllers\ObservationController::class, 'show']);
                
                // Conditions
                Route::get('/patients/{patient}/encounters/{encounter}/conditions', [\App\Http\Controllers\ConditionController::class, 'index']);
                Route::get('/patients/{patient}/encounters/{encounter}/conditions/{id}', [\App\Http\Controllers\ConditionController::class, 'show']);
                
                // Medications
                Route::get('/patients/{patient}/encounters/{encounter}/medications', [\App\Http\Controllers\MedicationRequestController::class, 'index']);
                Route::get('/patients/{patient}/encounters/{encounter}/medications/{id}', [\App\Http\Controllers\MedicationRequestController::class, 'show']);
            });
        });

        Route::middleware(['tenant', 'ability:patient:write'])->group(function () {
            Route::post('/patients', [\App\Http\Controllers\PatientController::class, 'store']);
            Route::put('/patients/{id}', [\App\Http\Controllers\PatientController::class, 'update'])->middleware('lgpd.consent');
            Route::delete('/patients/{id}', [\App\Http\Controllers\PatientController::class, 'destroy'])->middleware('lgpd.consent');
            
            // Vínculos Profissional-Paciente (Write)
            Route::post('/patients/{patient}/professionals', [\App\Http\Controllers\PatientUserController::class, 'store'])->middleware('lgpd.consent');
            Route::put('/patients/{patient}/professionals/{id}', [\App\Http\Controllers\PatientUserController::class, 'update'])->middleware('lgpd.consent');
            Route::delete('/patients/{patient}/professionals/{id}', [\App\Http\Controllers\PatientUserController::class, 'destroy'])->middleware('lgpd.consent');
            
            // LGPD Consents (Write)
            Route::post('/patients/{patient}/consents', [\App\Http\Controllers\ConsentController::class, 'store']);
            Route::patch('/patients/{patient}/consents/{id}/revoke', [\App\Http\Controllers\ConsentController::class, 'revoke']);
            
            // PEP - Prontuário (Write - com LGPD Consent)
            Route::middleware('lgpd.consent')->group(function () {
                // Encounters
                Route::post('/patients/{patient}/encounters', [\App\Http\Controllers\EncounterController::class, 'store']);
                Route::put('/patients/{patient}/encounters/{id}', [\App\Http\Controllers\EncounterController::class, 'update']);
                
                // Observations
                Route::post('/patients/{patient}/encounters/{encounter}/observations', [\App\Http\Controllers\ObservationController::class, 'store']);
                
                // Conditions
                Route::post('/patients/{patient}/encounters/{encounter}/conditions', [\App\Http\Controllers\ConditionController::class, 'store']);
                Route::put('/patients/{patient}/encounters/{encounter}/conditions/{id}', [\App\Http\Controllers\ConditionController::class, 'update']);
                
                // Medications
                Route::post('/patients/{patient}/encounters/{encounter}/medications', [\App\Http\Controllers\MedicationRequestController::class, 'store']);
                Route::put('/patients/{patient}/encounters/{encounter}/medications/{id}', [\App\Http\Controllers\MedicationRequestController::class, 'update']);
            });
        });

        // ==========================================
        // LOG VIEWER (debug — desligado por padrão via LOG_VIEWER_ENABLED)
        // ==========================================
        Route::middleware('ability:tenant:admin')->prefix('admin')->group(function () {
            Route::get('/logs', [\App\Http\Controllers\Admin\LogViewerController::class, 'tail']);
            Route::delete('/logs', [\App\Http\Controllers\Admin\LogViewerController::class, 'clear']);
        });
    });
});
