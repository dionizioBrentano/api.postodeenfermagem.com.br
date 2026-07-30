<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class AuthController extends Controller
{
    /**
     * Map User Roles to Sanctum Abilities (Scopes)
     */
    private function getAbilitiesForUserType(string $userType): array
    {
        return match ($userType) {
            'admin' => ['tenant:admin', 'audit:read'],
            'professional' => ['patient:read', 'patient:write', 'clinical:read', 'clinical:write', 'consent:read'],
            'patient' => ['patient:read', 'consent:read', 'consent:write'],
            default => [],
        };
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Credenciais inválidas.'], 401);
        }

        // Se MFA estiver habilitado, emitimos token de permissão restrita
        if ($user->mfa_enabled) {
            $token = $user->createToken('mfa-pending', ['mfa:verify'])->plainTextToken;

            AuditService::logAs($user, 'login_mfa_pending', $user);

            return response()->json([
                'message' => 'MFA required.',
                'access_token' => $token,
                'mfa_required' => true,
            ]);
        }

        // Sem MFA, emite token pleno
        $abilities = $this->getAbilitiesForUserType($user->user_type);
        $token = $user->createToken('access-token', $abilities)->plainTextToken;

        AuditService::logAs($user, 'login', $user);

        return response()->json([
            'access_token' => $token,
            'user' => $user,
            'abilities' => $abilities,
        ]);
    }

    public function setupMfa(Request $request)
    {
        $user = $request->user();

        if ($user->mfa_enabled) {
            return response()->json(['message' => 'MFA já está habilitado.'], 400);
        }

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $user->mfa_secret = $secret;
        $user->save();

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            'PostoDeEnfermagem',
            $user->email,
            $secret
        );

        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $qrCodeSvg = $writer->writeString($qrCodeUrl);

        return response()->json([
            'secret' => $secret,
            'qr_code_svg' => base64_encode($qrCodeSvg),
        ]);
    }

    public function verifyMfa(Request $request)
    {
        $request->validate([
            'totp_code' => 'required|string',
        ]);

        $user = $request->user(); // Autenticado com o token mfa-pending

        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($user->mfa_secret, $request->totp_code);

        if (!$valid) {
            return response()->json(['message' => 'Código TOTP inválido.'], 401);
        }

        // Ativa o MFA no banco de dados se for o setup inicial
        if (!$user->mfa_enabled) {
            $user->mfa_enabled = true;
            $user->save();
        }

        // Revoga o token temporário de mfa-pending
        $user->currentAccessToken()->delete();

        // Emite Token Pleno
        $abilities = $this->getAbilitiesForUserType($user->user_type);
        $token = $user->createToken('access-token', $abilities)->plainTextToken;

        AuditService::logAs($user, 'mfa_verified', $user);

        return response()->json([
            'access_token' => $token,
            'user' => $user,
            'abilities' => $abilities,
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        AuditService::logAs($user, 'logout', $user);

        $user->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout efetuado com sucesso.']);
    }
}
