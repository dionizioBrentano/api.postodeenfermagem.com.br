<?php

namespace Tests\Feature;

use App\Http\Controllers\AuthController;
use App\Models\Offering;
use App\Models\Procedure;
use App\Models\ServicePoint;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ServiceRequestsAndOfferingsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Hospital Vida',
            'slug' => 'hospital-vida-' . uniqid(),
            'status' => 'active',
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Enfaci',
            'slug' => 'enfaci-' . uniqid(),
            'status' => 'active',
        ]);
    }

    private function headers(Tenant $tenant, ?string $token = null): array
    {
        $headers = [
            'X-Tenant-ID' => $tenant->id,
            'Accept' => 'application/json',
        ];

        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }

    private function createMfaVerifiedUser(Tenant $tenant, string $userType, array $overrides = []): array
    {
        $user = User::create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Usuário ' . ucfirst($userType),
            'email' => strtolower($userType) . '.' . uniqid() . '@teste.com',
            'password' => Hash::make('Senha@123456'),
            'user_type' => $userType,
            'mfa_enabled' => true,
            'mfa_secret' => 'TESTSECRETKEY123',
        ], $overrides));

        $abilities = AuthController::getAbilitiesForUserType($userType);
        $token = $user->createToken('test-mfa-token', $abilities)->plainTextToken;

        return [$user, $token];
    }

    /**
     * Teste 1: search offerings no tenant A não lista ponto do B.
     */
    public function test_search_offerings_in_tenant_a_does_not_list_point_of_tenant_b(): void
    {
        [$profA] = $this->createMfaVerifiedUser($this->tenantA, 'professional');
        [$profB] = $this->createMfaVerifiedUser($this->tenantB, 'professional', [
            'cpf' => '12345678909',
            'phone' => '11999998888',
        ]);

        $procedureA = Procedure::create([
            'tenant_id' => $this->tenantA->id,
            'title' => 'Curativo Simples',
            'slug' => 'curativo-simples',
            'category' => Procedure::CATEGORY_CURATIVOS_FERIDAS,
            'content' => '<p>Instrução</p>',
            'status' => Procedure::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $procedureB = Procedure::create([
            'tenant_id' => $this->tenantB->id,
            'title' => 'Curativo Simples',
            'slug' => 'curativo-simples',
            'category' => Procedure::CATEGORY_CURATIVOS_FERIDAS,
            'content' => '<p>Instrução B</p>',
            'status' => Procedure::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $pointA = ServicePoint::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $profA->id,
            'name' => 'Ponto Tenant A',
            'cep' => '01310-100',
            'latitude' => -23.5616840,
            'longitude' => -46.6559810,
            'coverage_km' => 15.00,
            'quality_score' => 4.90,
            'active' => true,
        ]);

        $pointB = ServicePoint::create([
            'tenant_id' => $this->tenantB->id,
            'user_id' => $profB->id,
            'name' => 'Ponto Tenant B',
            'cep' => '04001-000',
            'latitude' => -23.5780000,
            'longitude' => -46.6450000,
            'coverage_km' => 10.00,
            'quality_score' => 4.80,
            'active' => true,
        ]);

        Offering::create([
            'tenant_id' => $this->tenantA->id,
            'service_point_id' => $pointA->id,
            'procedure_id' => $procedureA->id,
            'active' => true,
        ]);

        Offering::create([
            'tenant_id' => $this->tenantB->id,
            'service_point_id' => $pointB->id,
            'procedure_id' => $procedureB->id,
            'active' => true,
        ]);

        // Busca no tenant A
        $response = $this->getJson(
            '/api/v1/public/offerings/search?procedure_slug=curativo-simples&cep=01310-100',
            $this->headers($this->tenantA)
        );

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Ponto Tenant A']);
        $response->assertJsonMissing(['name' => 'Ponto Tenant B']);

        // Garante que não vazou dados do profissional
        $response->assertJsonMissing([
            'email' => $profA->email,
            'cpf' => $profA->cpf,
            'phone' => $profA->phone,
        ]);
    }

    /**
     * Teste 2: POST request sem MFA retorna 403 mfa_required.
     */
    public function test_post_service_request_without_mfa_returns_403_mfa_required(): void
    {
        $userSemMfa = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Cliente Sem MFA',
            'email' => 'cliente.sem.mfa@hospital.test',
            'password' => Hash::make('Senha@123456'),
            'user_type' => 'patient',
            'mfa_enabled' => false,
        ]);

        // Login gera token apenas com profile:read
        $token = $userSemMfa->createToken('access-token', ['profile:read'])->plainTextToken;

        $response = $this->postJson('/api/v1/service-requests', [
            'procedure_slug' => 'curativo-simples',
            'cep_servico' => '01310-100',
            'slot_date' => now()->addDay()->format('Y-m-d'),
            'slot_window' => 'manha',
        ], $this->headers($this->tenantA, $token));

        $response->assertStatus(403);
        $response->assertJson(['code' => 'mfa_required']);
    }

    /**
     * Teste 3: user professional cria request como cliente.
     */
    public function test_professional_user_can_create_service_request_as_client(): void
    {
        [$profUser, $profToken] = $this->createMfaVerifiedUser($this->tenantA, 'professional');

        $procedure = Procedure::create([
            'tenant_id' => $this->tenantA->id,
            'title' => 'Aplicação de Injetáveis',
            'slug' => 'aplicacao-injetaveis',
            'category' => Procedure::CATEGORY_APLICACAO_MEDICAMENTOS,
            'content' => '<p>Conteúdo</p>',
            'status' => Procedure::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/service-requests', [
            'procedure_slug' => 'aplicacao-injetaveis',
            'cep_servico' => '01310-200',
            'slot_date' => now()->addDays(2)->format('Y-m-d'),
            'slot_window' => 'tarde',
            'notes_cliente' => 'Favor tocar o interfone 42.',
        ], $this->headers($this->tenantA, $profToken));

        $response->assertStatus(201);
        $response->assertJsonPath('data.client_user_id', $profUser->id);
        $response->assertJsonPath('data.status', 'requested');
        $response->assertJsonPath('data.notes_cliente', 'Favor tocar o interfone 42.');

        $this->assertDatabaseHas('service_requests', [
            'tenant_id' => $this->tenantA->id,
            'client_user_id' => $profUser->id,
            'procedure_id' => $procedure->id,
            'cep_servico' => '01310-200',
            'status' => 'requested',
        ]);
    }

    /**
     * Teste 4: GET requests de outro cliente retorna 403.
     */
    public function test_client_cannot_get_service_requests_of_another_client_returns_403(): void
    {
        [$clientA, $tokenA] = $this->createMfaVerifiedUser($this->tenantA, 'patient');
        [$clientB, $tokenB] = $this->createMfaVerifiedUser($this->tenantA, 'patient');

        $procedure = Procedure::create([
            'tenant_id' => $this->tenantA->id,
            'title' => 'Curativo Simples',
            'slug' => 'curativo-simples',
            'category' => Procedure::CATEGORY_CURATIVOS_FERIDAS,
            'content' => '<p>Instrução</p>',
            'status' => Procedure::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        // Pedido criado pelo cliente B
        $requestB = ServiceRequest::create([
            'tenant_id' => $this->tenantA->id,
            'client_user_id' => $clientB->id,
            'procedure_id' => $procedure->id,
            'cep_servico' => '01310-300',
            'slot_date' => now()->addDay()->format('Y-m-d'),
            'slot_window' => 'manha',
            'status' => 'requested',
            'notes_cliente' => 'Nota confidencial do cliente B',
        ]);

        // Cliente A tenta acessar o pedido específico do Cliente B -> 403
        $responseShow = $this->getJson(
            "/api/v1/service-requests/{$requestB->id}",
            $this->headers($this->tenantA, $tokenA)
        );
        $responseShow->assertStatus(403);
        $responseShow->assertJsonPath('code', 'forbidden');

        // Cliente A tenta filtrar os pedidos do Cliente B na listagem -> 403
        $responseIndex = $this->getJson(
            "/api/v1/service-requests?client_user_id={$clientB->id}",
            $this->headers($this->tenantA, $tokenA)
        );
        $responseIndex->assertStatus(403);
        $responseIndex->assertJsonPath('code', 'forbidden');

        // Na listagem geral de Cliente A, o pedido de Cliente B não deve aparecer
        $responseOwnList = $this->getJson(
            "/api/v1/service-requests",
            $this->headers($this->tenantA, $tokenA)
        );
        $responseOwnList->assertStatus(200);
        $responseOwnList->assertJsonMissing([
            'id' => $requestB->id,
            'notes_cliente' => 'Nota confidencial do cliente B',
        ]);
    }
}
