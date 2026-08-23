<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ApplicationAuthController;
use App\Http\Controllers\CareAuthorizationController;

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
    // CONTEÚDO PÚBLICO — PROCEDIMENTOS DE ENFERMAGEM
    // Sem autenticação, mas ainda com o middleware "tenant": o header
    // X-Tenant-ID mantém o global scope da HasTenant ativo, e o controller
    // só devolve registros com status "published".
    // ==========================================
    Route::middleware('tenant')->prefix('public')->group(function () {
        Route::get('/procedures', [\App\Http\Controllers\PublicProcedureController::class, 'index']);
        Route::get('/procedures/categories', [\App\Http\Controllers\PublicProcedureController::class, 'categories']);
        Route::get('/procedures/{slug}', [\App\Http\Controllers\PublicProcedureController::class, 'show']);
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
            
            // Rotas clínicas (autorização agora via Policy + CareAuthorizationService)
            Route::get('/patients/{id}', [\App\Http\Controllers\PatientController::class, 'show']);
            Route::get('/patients/lookup/cpf/{cpf}', [\App\Http\Controllers\PatientController::class, 'findByCpf']);
            
            // Vínculos Profissional-Paciente (Read)
            Route::get('/patients/{patient}/professionals', [\App\Http\Controllers\PatientUserController::class, 'index']);
            Route::get('/patients/{patient}/professionals/{id}', [\App\Http\Controllers\PatientUserController::class, 'show']);
            
            // LGPD Consents (Read)
            Route::get('/patients/{patient}/consents', [\App\Http\Controllers\ConsentController::class, 'index']);
            Route::get('/patients/{patient}/consents/{id}', [\App\Http\Controllers\ConsentController::class, 'show']);
            
            // Care Authorizations (Read)
            Route::get('/patients/{patient}/care-authorizations', [CareAuthorizationController::class, 'index']);
            Route::get('/patients/{patient}/care-authorizations/{id}', [CareAuthorizationController::class, 'show']);
            
            // PEP - Prontuário (Read)
            // Acesso controlado via Policy + CareAuthorizationService (sem bloquear globalmente via LGPD Consent)
            Route::group([], function () {
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
            Route::put('/patients/{id}', [\App\Http\Controllers\PatientController::class, 'update']);
            Route::delete('/patients/{id}', [\App\Http\Controllers\PatientController::class, 'destroy']);
            
            // Vínculos Profissional-Paciente (Write)
            Route::post('/patients/{patient}/professionals', [\App\Http\Controllers\PatientUserController::class, 'store']);
            Route::put('/patients/{patient}/professionals/{id}', [\App\Http\Controllers\PatientUserController::class, 'update']);
            Route::delete('/patients/{patient}/professionals/{id}', [\App\Http\Controllers\PatientUserController::class, 'destroy']);
            
            // LGPD Consents (Write)
            Route::post('/patients/{patient}/consents', [\App\Http\Controllers\ConsentController::class, 'store']);
            Route::post('/patients/{patient}/consents/{id}/accept', [\App\Http\Controllers\ConsentController::class, 'accept']);
            Route::post('/patients/{patient}/consents/{id}/deny', [\App\Http\Controllers\ConsentController::class, 'deny']);
            Route::patch('/patients/{patient}/consents/{id}/revoke', [\App\Http\Controllers\ConsentController::class, 'revoke']);
            
            // Care Authorizations (Write)
            Route::post('/patients/{patient}/care-authorizations', [CareAuthorizationController::class, 'store']);
            Route::post('/patients/{patient}/care-authorizations/{id}/revoke', [CareAuthorizationController::class, 'revoke']);
            
            // PEP - Prontuário (Write)
            // Acesso controlado via Policy + CareAuthorizationService
            Route::group([], function () {
                // Encounters
                Route::post('/patients/{patient}/encounters', [\App\Http\Controllers\EncounterController::class, 'store']);
                Route::put('/patients/{patient}/encounters/{id}', [\App\Http\Controllers\EncounterController::class, 'update']);
                
                // Observations
                Route::post('/patients/{patient}/encounters/{encounter}/observations', [\App\Http\Controllers\ObservationController::class, 'store']);
                Route::put('/patients/{patient}/encounters/{encounter}/observations/{id}', [\App\Http\Controllers\ObservationController::class, 'update']);
                
                // Conditions
                Route::post('/patients/{patient}/encounters/{encounter}/conditions', [\App\Http\Controllers\ConditionController::class, 'store']);
                Route::put('/patients/{patient}/encounters/{encounter}/conditions/{id}', [\App\Http\Controllers\ConditionController::class, 'update']);
                
                // Medications
                Route::post('/patients/{patient}/encounters/{encounter}/medications', [\App\Http\Controllers\MedicationRequestController::class, 'store']);
                Route::put('/patients/{patient}/encounters/{encounter}/medications/{id}', [\App\Http\Controllers\MedicationRequestController::class, 'update']);
            });
        });

        // ==========================================
        // PROCEDIMENTOS DE ENFERMAGEM (conteúdo editorial do tenant)
        // Leitura: qualquer usuário autenticado do tenant.
        // Escrita: admins (ability tenant:admin + ProcedurePolicy).
        // ==========================================
        Route::middleware('tenant')->group(function () {
            Route::get('/procedures', [\App\Http\Controllers\ProcedureController::class, 'index']);
            Route::get('/procedures/{id}', [\App\Http\Controllers\ProcedureController::class, 'show']);

            Route::middleware('ability:tenant:admin')->group(function () {
                Route::post('/procedures', [\App\Http\Controllers\ProcedureController::class, 'store']);
                Route::match(['put', 'patch'], '/procedures/{id}', [\App\Http\Controllers\ProcedureController::class, 'update']);
                Route::delete('/procedures/{id}', [\App\Http\Controllers\ProcedureController::class, 'destroy']);
                Route::post('/procedures/{id}/publish', [\App\Http\Controllers\ProcedureController::class, 'publish']);
                Route::post('/procedures/{id}/unpublish', [\App\Http\Controllers\ProcedureController::class, 'unpublish']);
                Route::post('/procedures/{id}/restore', [\App\Http\Controllers\ProcedureController::class, 'restore']);
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
