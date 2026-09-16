<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class OAuthIdentitiesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Posto Central Teste',
            'slug' => 'posto-central-teste-' . uniqid(),
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function headers(?Tenant $tenant = null): array
    {
        return [
            'X-Tenant-ID' => ($tenant ?? $this->tenant)->id,
            'Accept' => 'application/json',
        ];
    }

    private function mockSocialiteUser(string $provider, string $sub, string $email, string $name = 'Usuario Teste'): void
    {
        $socialiteUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
        $socialiteUser->shouldReceive('getId')->andReturn($sub);
        $socialiteUser->shouldReceive('getEmail')->andReturn($email);
        $socialiteUser->shouldReceive('getName')->andReturn($name);
        $socialiteUser->shouldReceive('getNickname')->andReturn(null);

        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with($provider)->andReturn($driver);
    }

    public function test_register_creates_local_identity_and_local_login_is_idempotent(): void
    {
        // 1. Registro cria usuário e identidade local vinculada
        $registerPayload = [
            'name' => 'Dra. Ana Silva',
            'email' => 'ana.silva@postodeenfermagem.test',
            'password' => 'SenhaSegura@123',
            'password_confirmation' => 'SenhaSegura@123',
            'user_type' => 'professional',
        ];

        $response = $this->postJson('/api/v1/auth/register', $registerPayload, $this->headers());
        $response->assertStatus(201);

        $user = User::where('email', 'ana.silva@postodeenfermagem.test')->first();
        $this->assertNotNull($user);

        $this->assertDatabaseHas('user_identities', [
            'user_id' => $user->id,
            'provider' => 'local',
            'provider_user_id' => (string) $user->id,
            'email_at_provider' => 'ana.silva@postodeenfermagem.test',
        ]);

        $this->assertSame(1, $user->identities()->count());

        // 2. Login local bem-sucedido: mantém identidade local idempotente
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'ana.silva@postodeenfermagem.test',
            'password' => 'SenhaSegura@123',
        ], $this->headers());

        $loginResponse->assertStatus(200);
        $loginResponse->assertJsonStructure(['access_token', 'user', 'abilities']);
        $this->assertSame(1, $user->fresh()->identities()->count());

        // 3. Usuário com password null não autentica por senha (401 genérico)
        $oauthOnlyUser = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Dr. Sem Senha',
            'email' => 'sem.senha@postodeenfermagem.test',
            'password' => null,
            'user_type' => 'professional',
        ]);

        $failedLogin = $this->postJson('/api/v1/auth/login', [
            'email' => 'sem.senha@postodeenfermagem.test',
            'password' => 'QualquerSenha123',
        ], $this->headers());

        $failedLogin->assertStatus(401);
        $failedLogin->assertJson(['message' => 'Credenciais inválidas.']);
    }

    public function test_google_new_sub_creates_user_and_identity_with_password_null_and_council_empty(): void
    {
        $sub = 'google-sub-novo-12345';
        $email = 'novo.profissional@google.test';
        $name = 'Carlos Google';

        $this->mockSocialiteUser('google', $sub, $email, $name);

        $state = 'valid-test-state-abc';
        Cache::put("oauth:state:{$state}", [
            'tenant_id' => $this->tenant->id,
            'intent' => 'login',
            'user_id' => null,
            'return_to' => 'http://localhost:5173',
            'provider' => 'google',
        ], 600);

        $response = $this->getJson("/api/v1/auth/oauth/google/callback?state={$state}&code=valid-code", $this->headers());
        $response->assertStatus(200);
        $response->assertJsonStructure(['access_token', 'user', 'abilities', 'one_time_code']);

        $user = User::where('email', $email)->where('tenant_id', $this->tenant->id)->first();
        $this->assertNotNull($user);
        $this->assertSame($name, $user->name);
        $this->assertNull($user->password);
        $this->assertNull($user->council_type);
        $this->assertNull($user->council_number);
        $this->assertSame('professional', $user->user_type);
        $this->assertFalse($user->mfa_enabled);

        $identity = $user->identities()->where('provider', 'google')->first();
        $this->assertNotNull($identity);
        $this->assertSame($sub, $identity->provider_user_id);
        $this->assertSame($email, $identity->email_at_provider);

        // Testa troca do one_time_code na rota exchange
        $oneTimeCode = $response->json('one_time_code');
        $exchangeResponse = $this->postJson('/api/v1/auth/oauth/google/exchange', [
            'one_time_code' => $oneTimeCode,
        ], $this->headers());

        $exchangeResponse->assertStatus(200);
        $exchangeResponse->assertJsonStructure(['access_token', 'user', 'abilities']);
    }

    public function test_same_sub_logs_into_same_user(): void
    {
        $sub = 'google-sub-recorrente-999';
        $email = 'recorrente@google.test';

        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Usuário Existente',
            'email' => $email,
            'password' => null,
            'user_type' => 'professional',
        ]);

        $user->identities()->create([
            'provider' => 'google',
            'provider_user_id' => $sub,
            'email_at_provider' => $email,
        ]);

        $initialUserCount = User::count();

        $this->mockSocialiteUser('google', $sub, $email, 'Usuário Existente');

        $state = 'state-recorrente-456';
        Cache::put("oauth:state:{$state}", [
            'tenant_id' => $this->tenant->id,
            'intent' => 'login',
            'user_id' => null,
            'return_to' => 'http://localhost:5173',
            'provider' => 'google',
        ], 600);

        $response = $this->getJson("/api/v1/auth/oauth/google/callback?state={$state}&code=valid-code", $this->headers());
        $response->assertStatus(200);
        $response->assertJsonPath('user.id', $user->id);

        $this->assertSame($initialUserCount, User::count());
    }

    public function test_same_sub_from_different_tenant_returns_403_without_merging(): void
    {
        $tenantB = Tenant::create([
            'name' => 'Clínica Outro Tenant',
            'slug' => 'clinica-outro-tenant-' . uniqid(),
            'status' => 'active',
        ]);

        $sub = 'google-sub-tenant-b-777';
        $email = 'outrotenant@google.test';

        $userTenantB = User::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Usuário Tenant B',
            'email' => $email,
            'password' => null,
            'user_type' => 'professional',
        ]);

        $userTenantB->identities()->create([
            'provider' => 'google',
            'provider_user_id' => $sub,
            'email_at_provider' => $email,
        ]);

        $this->mockSocialiteUser('google', $sub, $email);

        // Requisição feita no tenant A, mas sub pertence ao tenant B
        $state = 'state-mismatch-tenant';
        Cache::put("oauth:state:{$state}", [
            'tenant_id' => $this->tenant->id,
            'intent' => 'login',
            'user_id' => null,
            'return_to' => 'http://localhost:5173',
            'provider' => 'google',
        ], 600);

        $response = $this->getJson("/api/v1/auth/oauth/google/callback?state={$state}&code=code", $this->headers());
        $response->assertStatus(403);
        $response->assertJsonPath('code', 'tenant_forbidden');
    }

    public function test_idp_email_equal_to_local_user_in_same_tenant_returns_409_identity_email_conflict(): void
    {
        $conflictEmail = 'conflito.local@postodeenfermagem.test';

        $localUser = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Enfermeiro Local',
            'email' => $conflictEmail,
            'password' => Hash::make('SenhaForte@123'),
            'user_type' => 'professional',
        ]);

        $localUser->identities()->create([
            'provider' => 'local',
            'provider_user_id' => (string) $localUser->id,
            'email_at_provider' => $conflictEmail,
        ]);

        $newSub = 'google-sub-diferente-888';
        $this->mockSocialiteUser('google', $newSub, $conflictEmail, 'Tentativa Conflitante');

        $state = 'state-conflito-email';
        Cache::put("oauth:state:{$state}", [
            'tenant_id' => $this->tenant->id,
            'intent' => 'login',
            'user_id' => null,
            'return_to' => 'http://localhost:5173',
            'provider' => 'google',
        ], 600);

        $response = $this->getJson("/api/v1/auth/oauth/google/callback?state={$state}&code=code", $this->headers());
        $response->assertStatus(409);
        $response->assertJsonPath('code', 'identity_email_conflict');

        // Garante que não fundiu e não criou nova identidade para o sub conflitante
        $this->assertDatabaseMissing('user_identities', [
            'provider' => 'google',
            'provider_user_id' => $newSub,
        ]);
        $this->assertSame(1, $localUser->identities()->count());
    }

    public function test_authenticated_user_links_google_with_new_sub(): void
    {
        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Dr. Roberto Santos',
            'email' => 'roberto@postodeenfermagem.test',
            'password' => Hash::make('MinhaSenha@456'),
            'user_type' => 'professional',
        ]);

        $user->identities()->create([
            'provider' => 'local',
            'provider_user_id' => (string) $user->id,
            'email_at_provider' => $user->email,
        ]);

        Sanctum::actingAs($user, ['patient:read', 'patient:write']);

        $newSub = 'google-sub-vincular-555';
        $this->mockSocialiteUser('google', $newSub, 'roberto.google@gmail.com', 'Roberto Google');

        $state = 'state-link-fluxo';
        Cache::put("oauth:state:{$state}", [
            'tenant_id' => $this->tenant->id,
            'intent' => 'link',
            'user_id' => $user->id,
            'return_to' => 'http://localhost:5173',
            'provider' => 'google',
        ], 600);

        $response = $this->getJson("/api/v1/auth/oauth/google/callback?state={$state}&code=code", $this->headers());
        $response->assertStatus(200);

        $this->assertDatabaseHas('user_identities', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => $newSub,
            'email_at_provider' => 'roberto.google@gmail.com',
        ]);

        $this->assertSame(2, $user->identities()->count());
    }

    public function test_sub_already_linked_to_another_user_returns_409_identity_already_linked(): void
    {
        $userA = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Usuário A',
            'email' => 'usera@postodeenfermagem.test',
            'password' => Hash::make('Senha@123'),
            'user_type' => 'professional',
        ]);

        $sharedSub = 'google-sub-ja-vinculado-333';

        $userA->identities()->create([
            'provider' => 'google',
            'provider_user_id' => $sharedSub,
            'email_at_provider' => 'usera@gmail.com',
        ]);

        $userB = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Usuário B',
            'email' => 'userb@postodeenfermagem.test',
            'password' => Hash::make('Senha@123'),
            'user_type' => 'professional',
        ]);

        Sanctum::actingAs($userB, ['patient:read', 'patient:write']);

        $this->mockSocialiteUser('google', $sharedSub, 'usera@gmail.com');

        $state = 'state-link-duplicado';
        Cache::put("oauth:state:{$state}", [
            'tenant_id' => $this->tenant->id,
            'intent' => 'link',
            'user_id' => $userB->id,
            'return_to' => 'http://localhost:5173',
            'provider' => 'google',
        ], 600);

        $response = $this->getJson("/api/v1/auth/oauth/google/callback?state={$state}&code=code", $this->headers());
        $response->assertStatus(409);
        $response->assertJsonPath('code', 'identity_already_linked');

        $this->assertSame(0, $userB->identities()->count());
    }

    public function test_get_user_lists_identities_without_secrets(): void
    {
        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Enfermeira Chefe',
            'email' => 'chefe@postodeenfermagem.test',
            'password' => Hash::make('Segredo@123'),
            'user_type' => 'professional',
            'mfa_secret' => 'JBSWY3DPEHPK3PXP',
            'mfa_enabled' => true,
        ]);

        $user->identities()->create([
            'provider' => 'local',
            'provider_user_id' => (string) $user->id,
            'email_at_provider' => 'chefe@postodeenfermagem.test',
        ]);

        $user->identities()->create([
            'provider' => 'google',
            'provider_user_id' => 'google-sub-chefe',
            'email_at_provider' => 'chefe@gmail.com',
        ]);

        Sanctum::actingAs($user, ['patient:read']);

        $response = $this->getJson('/api/v1/user', $this->headers());
        $response->assertStatus(200);

        $data = $response->json();

        // Segredos não devem estar presentes
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('mfa_secret', $data);
        $this->assertArrayNotHasKey('council_number_token', $data);

        // Identidades devem estar listadas com campos esperados
        $this->assertArrayHasKey('identities', $data);
        $this->assertCount(2, $data['identities']);

        $providers = array_column($data['identities'], 'provider');
        $this->assertContains('local', $providers);
        $this->assertContains('google', $providers);

        foreach ($data['identities'] as $idItem) {
            $this->assertArrayHasKey('id', $idItem);
            $this->assertArrayHasKey('provider', $idItem);
            $this->assertArrayHasKey('provider_user_id', $idItem);
            $this->assertArrayHasKey('email_at_provider', $idItem);
            $this->assertArrayHasKey('created_at', $idItem);
        }
    }

    public function test_delete_identity_rules(): void
    {
        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Usuario Teste Delete',
            'email' => 'delete.teste@postodeenfermagem.test',
            'password' => null, // Sem senha
            'user_type' => 'professional',
        ]);

        $localId = $user->identities()->create([
            'provider' => 'local',
            'provider_user_id' => (string) $user->id,
            'email_at_provider' => $user->email,
        ]);

        $googleId = $user->identities()->create([
            'provider' => 'google',
            'provider_user_id' => 'google-sub-to-delete',
            'email_at_provider' => 'delete.teste@gmail.com',
        ]);

        Sanctum::actingAs($user, ['patient:read']);

        // Se só resta local e password é null: 409
        $deleteGoogle = $this->deleteJson("/api/v1/auth/identities/{$googleId->id}", [], $this->headers());
        $deleteGoogle->assertStatus(409);
        $deleteGoogle->assertJsonPath('code', 'identity_password_required');

        // Adiciona senha ao usuário e tenta deletar google novamente
        $user->password = Hash::make('NovaSenha@123');
        $user->save();

        $deleteGoogleSuccess = $this->deleteJson("/api/v1/auth/identities/{$googleId->id}", [], $this->headers());
        $deleteGoogleSuccess->assertStatus(200);

        // Proibido remover a última identidade: 400
        $deleteLast = $this->deleteJson("/api/v1/auth/identities/{$localId->id}", [], $this->headers());
        $deleteLast->assertStatus(400);
        $deleteLast->assertJsonPath('code', 'last_identity_cannot_be_removed');
    }
}
