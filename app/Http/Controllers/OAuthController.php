<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserIdentity;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class OAuthController extends Controller
{
    private const ALLOWED_PROVIDERS = ['google', 'microsoft'];

    private function validateProvider(string $provider): bool
    {
        return in_array($provider, self::ALLOWED_PROVIDERS, true);
    }

    private function getAllowedReturnToList(): array
    {
        $raw = env('OAUTH_RETURN_TO_ALLOWLIST', 'http://localhost:5173,http://127.0.0.1:5173');
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    private function validateReturnTo(?string $returnTo): ?string
    {
        $allowedList = $this->getAllowedReturnToList();
        if (empty($returnTo)) {
            return $allowedList[0] ?? 'http://localhost:5173';
        }

        $parsed = parse_url($returnTo);
        if (!$parsed || !isset($parsed['scheme'], $parsed['host'])) {
            return null;
        }

        $origin = $parsed['scheme'] . '://' . $parsed['host'] . (isset($parsed['port']) ? ':' . $parsed['port'] : '');

        foreach ($allowedList as $allowed) {
            $allowedTrimmed = rtrim($allowed, '/');
            if ($returnTo === $allowedTrimmed || $origin === $allowedTrimmed || str_starts_with($returnTo, $allowedTrimmed . '/')) {
                return $returnTo;
            }
        }

        return null;
    }

    public function url(Request $request, string $provider)
    {
        $tenant = app('tenant');
        if (!$tenant) {
            return response()->json(['message' => 'Tenant obrigatório.'], 400);
        }

        if (!$this->validateProvider($provider)) {
            return response()->json(['message' => 'Provedor não suportado.'], 400);
        }

        $intent = $request->input('intent', 'login');
        if (!in_array($intent, ['login', 'link'], true)) {
            return response()->json(['message' => 'Intent inválido.'], 422);
        }

        $userId = null;
        if ($intent === 'link') {
            $user = $request->user('sanctum');
            if (!$user) {
                return response()->json(['message' => 'Autenticação necessária para vincular identidade.'], 401);
            }

            if ($user->tenant_id !== $tenant->id) {
                return response()->json(['message' => 'Usuário pertence a outro tenant.'], 403);
            }

            $token = $user->currentAccessToken();
            if ($token && $token->can('mfa:verify') && !$token->can('*')) {
                return response()->json(['message' => 'Token MFA não possui permissão para vincular conta.'], 403);
            }

            $userId = $user->id;
        }

        $rawReturnTo = $request->input('return_to');
        if ($rawReturnTo !== null && $rawReturnTo !== '') {
            $returnTo = $this->validateReturnTo($rawReturnTo);
            if (!$returnTo) {
                return response()->json(['message' => 'URL de redirecionamento não permitida.'], 422);
            }
        } else {
            $returnTo = $this->validateReturnTo(null);
        }

        $state = Str::random(40);

        Cache::put("oauth:state:{$state}", [
            'tenant_id' => $tenant->id,
            'intent' => $intent,
            'user_id' => $userId,
            'return_to' => $returnTo,
            'provider' => $provider,
        ], now()->addMinutes(10));

        $driver = Socialite::driver($provider)->stateless()->with(['state' => $state]);
        $redirectResponse = $driver->redirect();
        $targetUrl = method_exists($redirectResponse, 'getTargetUrl')
            ? $redirectResponse->getTargetUrl()
            : (string) $redirectResponse;

        return response()->json([
            'url' => $targetUrl,
            'state' => $state,
        ]);
    }

    public function callback(Request $request, string $provider)
    {
        $tenant = app('tenant');
        if (!$tenant) {
            return response()->json(['message' => 'Tenant obrigatório.'], 400);
        }

        if (!$this->validateProvider($provider)) {
            return response()->json(['message' => 'Provedor não suportado.'], 400);
        }

        $state = $request->query('state');
        if (!$state) {
            return response()->json(['message' => 'State ausente.'], 400);
        }

        $stateData = Cache::pull("oauth:state:{$state}");
        if (!$stateData) {
            return response()->json(['message' => 'State inválido ou expirado.'], 400);
        }

        if ($stateData['tenant_id'] !== $tenant->id) {
            return response()->json(['message' => 'Tenant incompatível com a autorização.'], 403);
        }

        if ($stateData['provider'] !== $provider) {
            return response()->json(['message' => 'Provedor divergente do state.'], 400);
        }

        if ($request->has('error')) {
            return response()->json([
                'message' => 'Erro retornado pelo provedor OAuth: ' . $request->query('error'),
            ], 400);
        }

        try {
            $oauthUser = Socialite::driver($provider)->stateless()->user();
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Falha ao autenticar com o provedor OAuth.',
            ], 400);
        }

        $sub = (string) $oauthUser->getId();
        $email = $oauthUser->getEmail();
        $name = $oauthUser->getName() ?? $oauthUser->getNickname() ?? 'Usuário';

        $intent = $stateData['intent'] ?? 'login';
        $returnTo = $stateData['return_to'];

        // Intent: Link
        if ($intent === 'link') {
            $user = User::where('id', $stateData['user_id'])
                ->where('tenant_id', $tenant->id)
                ->first();

            if (!$user) {
                return response()->json(['message' => 'Usuário não encontrado.'], 404);
            }

            $existingIdentity = UserIdentity::where('provider', $provider)
                ->where('provider_user_id', $sub)
                ->first();

            if ($existingIdentity) {
                if ((string) $existingIdentity->user_id === (string) $stateData['user_id']) {
                    $payload = [
                        'message' => 'Identidade vinculada com sucesso.',
                        'user' => $user,
                        'linked_provider' => $provider,
                    ];

                    return $this->finishOAuth($request, $tenant->id, $returnTo, $payload);
                }

                return response()->json([
                    'message' => 'Esta identidade já está vinculada a outro usuário.',
                    'code' => 'identity_already_linked',
                ], 409);
            }

            $user->identities()->create([
                'provider' => $provider,
                'provider_user_id' => $sub,
                'email_at_provider' => $email,
            ]);

            AuditService::logAs($user, 'identity_linked', $user, ['provider' => $provider]);

            $payload = [
                'message' => 'Identidade vinculada com sucesso.',
                'user' => $user,
                'linked_provider' => $provider,
            ];

            return $this->finishOAuth($request, $tenant->id, $returnTo, $payload);
        }

        // Intent: Login
        $existingIdentity = UserIdentity::where('provider', $provider)
            ->where('provider_user_id', $sub)
            ->first();

        if ($existingIdentity) {
            $user = $existingIdentity->user;
            if (!$user || $user->tenant_id !== $tenant->id) {
                return response()->json([
                    'message' => 'Esta identidade pertence a outro tenant.',
                    'code' => 'tenant_forbidden',
                ], 403);
            }

            if ($user->mfa_enabled) {
                $token = $user->createToken('mfa-pending', ['mfa:verify'])->plainTextToken;
                AuditService::logAs($user, 'login_mfa_pending', $user);

                $payload = [
                    'message' => 'MFA required.',
                    'access_token' => $token,
                    'mfa_required' => true,
                ];

                return $this->finishOAuth($request, $tenant->id, $returnTo, $payload);
            }

            $abilities = AuthController::getAbilitiesForUserType($user->user_type);
            $token = $user->createToken('access-token', $abilities)->plainTextToken;
            AuditService::logAs($user, 'login_oauth', $user, ['provider' => $provider]);

            $payload = [
                'access_token' => $token,
                'user' => $user,
                'abilities' => $abilities,
            ];

            return $this->finishOAuth($request, $tenant->id, $returnTo, $payload);
        }

        // sub inédito neste provider:
        // sub novo e users.email já usado por OUTRO user neste tenant: 409 código identity_email_conflict. NÃO fundir.
        if ($email) {
            $emailConflict = User::where('email', $email)
                ->where('tenant_id', $tenant->id)
                ->exists();

            if ($emailConflict) {
                return response()->json([
                    'message' => 'O e-mail informado já está em uso por outro usuário neste tenant.',
                    'code' => 'identity_email_conflict',
                ], 409);
            }
        }

        // Cria user NOVO no tenant do request + identity
        $user = DB::transaction(function () use ($tenant, $name, $email, $provider, $sub) {
            $newUser = User::create([
                'tenant_id' => $tenant->id,
                'name' => $name,
                'email' => $email,
                'password' => null,
                'user_type' => 'professional',
                'council_type' => null,
                'council_number' => null,
                'mfa_enabled' => false,
            ]);

            $newUser->identities()->create([
                'provider' => $provider,
                'provider_user_id' => $sub,
                'email_at_provider' => $email,
            ]);

            return $newUser;
        });

        AuditService::logAs($user, 'registered_oauth', $user, ['provider' => $provider]);

        $abilities = AuthController::getAbilitiesForUserType($user->user_type);
        $token = $user->createToken('access-token', $abilities)->plainTextToken;

        $payload = [
            'access_token' => $token,
            'user' => $user,
            'abilities' => $abilities,
        ];

        return $this->finishOAuth($request, $tenant->id, $returnTo, $payload);
    }

    private function finishOAuth(Request $request, string $tenantId, string $returnTo, array $payload)
    {
        $oneTimeCode = Str::random(40);

        Cache::put("oauth:code:{$oneTimeCode}", array_merge($payload, [
            'tenant_id' => $tenantId,
        ]), now()->addMinutes(5));

        if (config('app.env') === 'local' && $request->wantsJson()) {
            return response()->json(array_merge($payload, [
                'one_time_code' => $oneTimeCode,
            ]));
        }

        $delimiter = str_contains($returnTo, '?') ? '&' : '?';
        $redirectUrl = $returnTo . $delimiter . http_build_query(['code' => $oneTimeCode]);

        return redirect()->away($redirectUrl, 302);
    }

    public function exchange(Request $request, string $provider)
    {
        $tenant = app('tenant');
        if (!$tenant) {
            return response()->json(['message' => 'Tenant obrigatório.'], 400);
        }

        if (!$this->validateProvider($provider)) {
            return response()->json(['message' => 'Provedor não suportado.'], 400);
        }

        $request->validate([
            'one_time_code' => 'required|string',
        ]);

        $code = $request->input('one_time_code');
        $payload = Cache::pull("oauth:code:{$code}");

        if (!$payload) {
            return response()->json(['message' => 'Código de autorização inválido ou expirado.'], 401);
        }

        if (($payload['tenant_id'] ?? null) !== $tenant->id) {
            return response()->json(['message' => 'Tenant incompatível.'], 403);
        }

        unset($payload['tenant_id']);

        return response()->json($payload);
    }
}
