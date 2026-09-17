<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AuthIdentifierAndStepUpTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Hospital Geral Teste',
            'slug' => 'hospital-geral-teste-' . uniqid(),
            'status' => 'active',
        ]);
    }

    private function headers(?Tenant $tenant = null, ?string $token = null): array
    {
        $headers = [
            'X-Tenant-ID' => ($tenant ?? $this->tenant)->id,
            'Accept' => 'application/json',
        ];

        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }

    private function generateValidCpf(int $seed = 1): string
    {
        $base = str_pad((string) (100000000 + $seed), 9, '0', STR_PAD_LEFT);
        $digits = array_map('intval', str_split($base));

        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += $digits[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            $digits[$t] = $d;
        }

        return implode('', $digits);
    }

    private function formatCpf(string $cpf): string
    {
        return sprintf('%s.%s.%s-%s', substr($cpf, 0, 3), substr($cpf, 3, 3), substr($cpf, 6, 3), substr($cpf, 9, 2));
    }

    public function test_login_with_email_returns_profile_read_token_when_mfa_disabled_and_creates_local_identity(): void
    {
        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Enfermeira Clara',
            'email' => 'clara@hospital.test',
            'password' => Hash::make('Senha@123456'),
            'user_type' => 'professional',
            'mfa_enabled' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'clara@hospital.test',
            'password' => 'Senha@123456',
        ], $this->headers());

        $response->assertStatus(200);
        $response->assertJsonStructure(['access_token', 'user', 'abilities']);
        $this->assertSame(['profile:read'], $response->json('abilities'));

        $this->assertDatabaseHas('user_identities', [
            'user_id' => $user->id,
            'provider' => 'local',
            'provider_user_id' => (string) $user->id,
            'email_at_provider' => 'clara@hospital.test',
        ]);
    }

    public function test_login_with_cpf_both_raw_and_formatted(): void
    {
        $cpf = $this->generateValidCpf(1234);
        $formattedCpf = $this->formatCpf($cpf);

        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Dr. Paulo',
            'email' => 'paulo@hospital.test',
            'password' => Hash::make('Senha@123456'),
            'user_type' => 'professional',
            'cpf' => $cpf,
            'mfa_enabled' => false,
        ]);

        // 1. Login com dígitos puros do CPF
        $resRaw = $this->postJson('/api/v1/auth/login', [
            'identifier' => $cpf,
            'password' => 'Senha@123456',
        ], $this->headers());

        $resRaw->assertStatus(200);
        $this->assertSame($user->id, $resRaw->json('user.id'));

        // 2. Login com CPF formatado (com pontos e traço)
        $resFormatted = $this->postJson('/api/v1/auth/login', [
            'identifier' => $formattedCpf,
            'password' => 'Senha@123456',
        ], $this->headers());

        $resFormatted->assertStatus(200);
        $this->assertSame($user->id, $resFormatted->json('user.id'));
    }

    public function test_login_with_phone_digits(): void
    {
        $phone = '11987654321';

        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Tec. Marcos',
            'email' => 'marcos@hospital.test',
            'password' => Hash::make('Senha@123456'),
            'user_type' => 'professional',
            'phone' => $phone,
            'mfa_enabled' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => $phone,
            'password' => 'Senha@123456',
        ], $this->headers());

        $response->assertStatus(200);
        $this->assertSame($user->id, $response->json('user.id'));
    }

    public function test_login_fails_with_generic_401(): void
    {
        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Usuario Teste',
            'email' => 'usuario@hospital.test',
            'password' => Hash::make('SenhaCorreta@123'),
            'user_type' => 'professional',
        ]);

        // 1. Senha errada
        $res1 = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'usuario@hospital.test',
            'password' => 'SenhaIncorreta',
        ], $this->headers());
        $res1->assertStatus(401);
        $res1->assertJson(['message' => 'Credenciais inválidas.']);

        // 2. Identificador inexistente
        $res2 = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'naoexiste@hospital.test',
            'password' => 'SenhaCorreta@123',
        ], $this->headers());
        $res2->assertStatus(401);
        $res2->assertJson(['message' => 'Credenciais inválidas.']);

        // 3. Campos vazios
        $res3 = $this->postJson('/api/v1/auth/login', [
            'identifier' => '',
            'password' => '',
        ], $this->headers());
        $res3->assertStatus(401);
        $res3->assertJson(['message' => 'Credenciais inválidas.']);
    }

    public function test_register_with_duplicate_cpf_in_same_tenant_fails(): void
    {
        $cpf = $this->generateValidCpf(5555);

        // Primeiro registro com CPF
        $res1 = $this->postJson('/api/v1/auth/register', [
            'name' => 'Primeiro Usuário',
            'email' => 'user1@hospital.test',
            'password' => 'SenhaForte@123',
            'password_confirmation' => 'SenhaForte@123',
            'user_type' => 'professional',
            'cpf' => $cpf,
            'phone' => '11988887777',
        ], $this->headers());
        $res1->assertStatus(201);

        // Tentativa de segundo registro no mesmo tenant com mesmo CPF deve retornar 422
        $res2 = $this->postJson('/api/v1/auth/register', [
            'name' => 'Segundo Usuário',
            'email' => 'user2@hospital.test',
            'password' => 'SenhaForte@123',
            'password_confirmation' => 'SenhaForte@123',
            'user_type' => 'professional',
            'cpf' => $cpf,
        ], $this->headers());

        $res2->assertStatus(422);
        $res2->assertJsonValidationErrors(['cpf']);

        // No entanto, outro tenant pode registrar com esse mesmo CPF (isolamento multi-tenant)
        $otherTenant = Tenant::create([
            'name' => 'Outro Hospital',
            'slug' => 'outro-hospital-' . uniqid(),
            'status' => 'active',
        ]);

        $resOther = $this->postJson('/api/v1/auth/register', [
            'name' => 'Usuário Outro Tenant',
            'email' => 'user.outro@hospital.test',
            'password' => 'SenhaForte@123',
            'password_confirmation' => 'SenhaForte@123',
            'user_type' => 'professional',
            'cpf' => $cpf,
        ], $this->headers($otherTenant));

        $resOther->assertStatus(201);
    }

    public function test_user_without_mfa_can_get_user_profile_but_cannot_access_patients(): void
    {
        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Enfermeira Sem MFA',
            'email' => 'sem.mfa@hospital.test',
            'password' => Hash::make('Senha@123456'),
            'user_type' => 'professional',
            'mfa_enabled' => false,
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'sem.mfa@hospital.test',
            'password' => 'Senha@123456',
        ], $this->headers());

        $loginResponse->assertStatus(200);
        $token = $loginResponse->json('access_token');
        $this->assertNotEmpty($token);

        // GET /user deve funcionar normalmente (200 OK)
        $userResponse = $this->getJson('/api/v1/user', $this->headers(null, $token));
        $userResponse->assertStatus(200);
        $userResponse->assertJson(['email' => 'sem.mfa@hospital.test']);

        // GET /patients deve retornar 403 mfa_required
        $patientsResponse = $this->getJson('/api/v1/patients', $this->headers(null, $token));
        $patientsResponse->assertStatus(403);
        $patientsResponse->assertJson(['code' => 'mfa_required']);
    }

    public function test_patch_profile_without_mfa_returns_403(): void
    {
        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Enfermeira Perfil',
            'email' => 'perfil@hospital.test',
            'password' => Hash::make('Senha@123456'),
            'user_type' => 'professional',
            'mfa_enabled' => false,
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'perfil@hospital.test',
            'password' => 'Senha@123456',
        ], $this->headers());

        $token = $loginResponse->json('access_token');

        // PATCH /auth/profile sem MFA verificado deve retornar 403 mfa_required
        $patchResponse = $this->patchJson('/api/v1/auth/profile', [
            'name' => 'Novo Nome Enfermeira',
        ], $this->headers(null, $token));

        $patchResponse->assertStatus(403);
        $patchResponse->assertJson(['code' => 'mfa_required']);
    }

    public function test_step_up_lifecycle_mfa_setup_verify_and_patch_profile(): void
    {
        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Enfermeiro MFA Flow',
            'email' => 'mfa.flow@hospital.test',
            'password' => Hash::make('Senha@123456'),
            'user_type' => 'professional',
            'mfa_enabled' => false,
        ]);

        // 1. Login inicial sem MFA: token profile:read
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'mfa.flow@hospital.test',
            'password' => 'Senha@123456',
        ], $this->headers());
        $token1 = $loginResponse->json('access_token');

        // 2. Setup de MFA
        $setupRes = $this->postJson('/api/v1/auth/mfa/setup', [], $this->headers(null, $token1));
        $setupRes->assertStatus(200);
        $secret = $setupRes->json('secret');
        $this->assertNotEmpty($secret);

        // 3. Verificação de MFA
        $google2fa = new Google2FA();
        $code = $google2fa->getCurrentOtp($secret);

        $verifyRes = $this->postJson('/api/v1/auth/mfa/verify', [
            'totp_code' => $code,
        ], $this->headers(null, $token1));

        $verifyRes->assertStatus(200);
        $upgradedToken = $verifyRes->json('access_token');
        $this->assertNotEmpty($upgradedToken);

        // O usuário agora tem mfa_enabled = true
        $this->assertTrue($user->fresh()->mfa_enabled);

        // 4. Agora PATCH /auth/profile tem permissão e atualiza dados
        $newCpf = $this->generateValidCpf(8888);
        $patchRes = $this->patchJson('/api/v1/auth/profile', [
            'name' => 'Enfermeiro MFA Flow Atualizado',
            'phone' => '11977776666',
            'cpf' => $newCpf,
        ], $this->headers(null, $upgradedToken));

        $patchRes->assertStatus(200);
        $patchRes->assertJson([
            'message' => 'Perfil atualizado com sucesso.',
            'user' => [
                'name' => 'Enfermeiro MFA Flow Atualizado',
                'phone' => '11977776666',
                'cpf' => $newCpf,
            ],
        ]);
    }
}
