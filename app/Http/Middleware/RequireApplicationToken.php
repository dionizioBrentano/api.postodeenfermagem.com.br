<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class RequireApplicationToken
{
    public function handle(Request $request, Closure $next)
    {
        $tokenString = $request->header('X-App-Token');

        if (!$tokenString) {
            return response()->json(['message' => 'Application token is missing (X-App-Token header).'], 401);
        }

        $token = PersonalAccessToken::findToken($tokenString);

        if (!$token || $token->tokenable_type !== \App\Models\Application::class) {
            return response()->json(['message' => 'Invalid application token.'], 401);
        }

        // Opcional: Injetar a aplicação no request caso precise validar escopos do App no futuro
        $request->attributes->set('application', $token->tokenable);

        return $next($request);
    }
}
