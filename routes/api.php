<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

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

    // Rota protegida por tenant para validação no Sprint 0
    Route::middleware(['tenant'])->group(function () {
        Route::get('/tenant-test', function () {
            $tenant = app('tenant');
            return response()->json([
                'message' => 'Tenant identificado com sucesso!',
                'tenant_name' => $tenant->name,
                'tenant_id' => $tenant->id,
            ]);
        });
    });
});
