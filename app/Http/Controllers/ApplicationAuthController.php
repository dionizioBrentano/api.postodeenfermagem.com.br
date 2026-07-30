<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Application;
use Illuminate\Support\Facades\Hash;

class ApplicationAuthController extends Controller
{
    /**
     * Authenticate Application M2M and return token
     */
    public function token(Request $request)
    {
        $request->validate([
            'client_id' => 'required|string',
            'client_secret' => 'required|string',
        ]);

        $app = Application::where('client_id', $request->client_id)
            ->where('status', 'active')
            ->first();

        if (!$app || !Hash::check($request->client_secret, $app->client_secret)) {
            return response()->json(['message' => 'Credenciais da aplicação inválidas ou aplicação inativa.'], 401);
        }

        // Emitimos o token. As abilities da aplicação são os scopes configurados nela
        $token = $app->createToken('app-token', $app->scopes ?? ['*'])->plainTextToken;

        return response()->json([
            'app_token' => $token,
            'tenant_id' => $app->tenant_id,
        ]);
    }
}
