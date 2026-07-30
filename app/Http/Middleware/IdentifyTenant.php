<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->header('X-Tenant-ID');

        if (! $tenantId) {
            return response()->json([
                'message' => 'Header X-Tenant-ID ausente.'
            ], 400);
        }

        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            return response()->json([
                'message' => 'Tenant não encontrado.'
            ], 403);
        }

        if ($tenant->status !== 'active') {
            return response()->json([
                'message' => 'Tenant inativo ou suspenso.'
            ], 403);
        }

        // Registrar o tenant atual na aplicação para que a trait HasTenant possa acessá-lo
        app()->instance('tenant', $tenant);

        return $next($request);
    }
}
