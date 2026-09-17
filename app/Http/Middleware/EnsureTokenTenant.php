<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTokenTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $tenant = app()->has('tenant') ? app('tenant') : null;
        $activeTenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        if ($activeTenantId) {
            // Se o usuário autenticado possui tenant_id preenchido e diferente do tenant ativo
            if (! empty($user->tenant_id) && $user->tenant_id !== $activeTenantId) {
                return response()->json([
                    'message' => 'Acesso não autorizado para este tenant.',
                    'code' => 'tenant_forbidden',
                ], 403);
            }

            // Super admin com tenant_id null: só passa se a rota for claramente admin global;
            // nas rotas de produto com tenant no header, 403 tenant_forbidden.
            if (is_null($user->tenant_id) || $user->tenant_id === '') {
                if (! $this->isGlobalAdminRoute($request)) {
                    return response()->json([
                        'message' => 'Acesso não autorizado para este tenant.',
                        'code' => 'tenant_forbidden',
                    ], 403);
                }
            }
        }

        return $next($request);
    }

    /**
     * Determina se a rota atual é claramente administrativa global.
     */
    private function isGlobalAdminRoute(Request $request): bool
    {
        return $request->is('api/v1/admin/*')
            || $request->is('*/admin/*')
            || $request->is('admin/*')
            || $request->routeIs('admin.*');
    }
}
