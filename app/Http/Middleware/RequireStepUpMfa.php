<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireStepUpMfa
{
    /**
     * Handle an incoming request.
     *
     * Garante que o usuário possua MFA habilitado e que o token atual
     * não seja restrito (ex.: apenas profile:read ou mfa:verify).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            // Se o usuário não tem MFA habilitado
            if (! $user->mfa_enabled) {
                return response()->json([
                    'message' => 'Autenticação de dois fatores obrigatória para esta operação.',
                    'code' => 'mfa_required',
                ], 403);
            }

            // Verifica as permissões do token Sanctum
            if (method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
                $abilities = (array) ($user->currentAccessToken()->abilities ?? []);
                if ($abilities === ['profile:read'] || $abilities === ['mfa:verify'] || (count($abilities) === 1 && in_array('profile:read', $abilities, true))) {
                    return response()->json([
                        'message' => 'Autenticação de dois fatores obrigatória para esta operação.',
                        'code' => 'mfa_required',
                    ], 403);
                }
            }

            if (method_exists($user, 'tokenCan')) {
                if ($user->tokenCan('mfa:verify')) {
                    return response()->json([
                        'message' => 'Autenticação de dois fatores obrigatória para esta operação.',
                        'code' => 'mfa_required',
                    ], 403);
                }

                if ($user->tokenCan('profile:read') && ! $user->tokenCan('patient:read') && ! $user->tokenCan('patient:write') && ! $user->tokenCan('tenant:admin') && ! $user->tokenCan('clinical:read') && ! $user->tokenCan('*')) {
                    return response()->json([
                        'message' => 'Autenticação de dois fatores obrigatória para esta operação.',
                        'code' => 'mfa_required',
                    ], 403);
                }
            }
        }

        return $next($request);
    }
}
