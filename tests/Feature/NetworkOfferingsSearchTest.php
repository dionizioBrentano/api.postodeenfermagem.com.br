<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Offering;
use App\Models\Procedure;
use App\Models\ServicePoint;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NetworkOfferingsSearchTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $profA;
    private User $profB;
    private ServicePoint $pointA;
    private ServicePoint $pointB;
    private Procedure $procedureA;
    private Procedure $procedureB;
    private Offering $offeringA;
    private Offering $offeringB;

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

        $this->profA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Enfermeiro A',
            'email' => 'enfermeiro.a@hospitalvida.test',
            'cpf' => '11122233344',
            'phone' => '11988887777',
            'password' => Hash::make('Senha@123456'),
            'user_type' => 'professional',
        ]);

        $this->profB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Enfermeira B',
            'email' => 'enfermeira.b@enfaci.test',
            'cpf' => '55566677788',
            'phone' => '11977776666',
            'password' => Hash::make('Senha@123456'),
            'user_type' => 'professional',
        ]);

        $this->procedureA = Procedure::create([
            'tenant_id' => $this->tenantA->id,
            'title' => 'Curativo Simples Vida',
            'slug' => 'curativo-simples',
            'category' => Procedure::CATEGORY_CURATIVOS_FERIDAS,
            'content' => '<p>Instruções do curativo no Hospital Vida</p>',
            'status' => Procedure::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);

        $this->procedureB = Procedure::create([
            'tenant_id' => $this->tenantB->id,
            'title' => 'Curativo Simples Enfaci',
            'slug' => 'curativo-simples',
            'category' => Procedure::CATEGORY_CURATIVOS_FERIDAS,
            'content' => '<p>Instruções do curativo na Enfaci</p>',
            'status' => Procedure::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);

        $this->pointA = ServicePoint::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->profA->id,
            'name' => 'Ponto Paulista A',
            'cep' => '01310-100',
            'latitude' => -23.5616840,
            'longitude' => -46.6559810,
            'coverage_km' => 15.00,
            'quality_score' => 4.90,
            'active' => true,
        ]);

        $this->pointB = ServicePoint::create([
            'tenant_id' => $this->tenantB->id,
            'user_id' => $this->profB->id,
            'name' => 'Ponto Paraíso B',
            'cep' => '04001-000',
            'latitude' => -23.5780000,
            'longitude' => -46.6450000,
            'coverage_km' => 10.00,
            'quality_score' => 4.70,
            'active' => true,
        ]);

        $this->offeringA = Offering::create([
            'tenant_id' => $this->tenantA->id,
            'service_point_id' => $this->pointA->id,
            'procedure_id' => $this->procedureA->id,
            'active' => true,
        ]);

        $this->offeringB = Offering::create([
            'tenant_id' => $this->tenantB->id,
            'service_point_id' => $this->pointB->id,
            'procedure_id' => $this->procedureB->id,
            'active' => true,
        ]);
    }

    /**
     * Ponto do tenant A aparece na busca mesmo quando o header X-Tenant-ID for do tenant B.
     */
    public function test_point_of_tenant_a_appears_in_search_without_header_being_a(): void
    {
        $response = $this->getJson(
            '/api/v1/public/network/offerings/search?procedure_slug=curativo-simples',
            ['X-Tenant-ID' => $this->tenantB->id]
        );

        $response->assertStatus(200);

        // Ponto do Tenant A aparece mesmo com o header sendo Tenant B
        $response->assertJsonFragment([
            'service_point_id' => $this->pointA->id,
            'name' => 'Ponto Paulista A',
            'tenant_id' => $this->tenantA->id,
            'tenant_name' => $this->tenantA->name,
            'tenant_slug' => $this->tenantA->slug,
        ]);

        // Ponto do Tenant B também aparece
        $response->assertJsonFragment([
            'service_point_id' => $this->pointB->id,
            'name' => 'Ponto Paraíso B',
            'tenant_id' => $this->tenantB->id,
            'tenant_name' => $this->tenantB->name,
            'tenant_slug' => $this->tenantB->slug,
        ]);
    }

    /**
     * Busca na rede funciona normalmente sem nenhum header X-Tenant-ID.
     */
    public function test_network_search_works_without_tenant_header(): void
    {
        $response = $this->getJson('/api/v1/public/network/offerings/search?procedure_slug=curativo-simples');

        $response->assertStatus(200);

        $response->assertJsonFragment([
            'service_point_id' => $this->pointA->id,
            'tenant_id' => $this->tenantA->id,
        ]);

        $response->assertJsonFragment([
            'service_point_id' => $this->pointB->id,
            'tenant_id' => $this->tenantB->id,
        ]);
    }

    /**
     * Dados sensíveis (PII: e-mail, CPF, telefone) de profissionais NÃO vazam no payload.
     */
    public function test_network_search_does_not_leak_pii(): void
    {
        $response = $this->getJson('/api/v1/public/network/offerings/search?procedure_slug=curativo-simples');

        $response->assertStatus(200);

        // Verifica que PII dos usuários não vazam em nenhum nível do JSON
        $response->assertJsonMissing(['email' => $this->profA->email]);
        $response->assertJsonMissing(['email' => $this->profB->email]);
        $response->assertJsonMissing(['cpf' => $this->profA->cpf]);
        $response->assertJsonMissing(['cpf' => $this->profB->cpf]);
        $response->assertJsonMissing(['phone' => $this->profA->phone]);
        $response->assertJsonMissing(['phone' => $this->profB->phone]);

        // Valida que as chaves retornadas por item batem exatamente com o esperado
        $data = $response->json('data');
        $this->assertNotEmpty($data);

        foreach ($data as $item) {
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('offering_id', $item);
            $this->assertArrayHasKey('service_point_id', $item);
            $this->assertArrayHasKey('name', $item);
            $this->assertArrayHasKey('quality_score', $item);
            $this->assertArrayHasKey('cep', $item);
            $this->assertArrayHasKey('coverage_km', $item);
            $this->assertArrayHasKey('procedure_slug', $item);
            $this->assertArrayHasKey('procedure_title', $item);
            $this->assertArrayHasKey('latitude', $item);
            $this->assertArrayHasKey('longitude', $item);
            $this->assertArrayHasKey('tenant_id', $item);
            $this->assertArrayHasKey('tenant_name', $item);
            $this->assertArrayHasKey('tenant_slug', $item);

            $this->assertArrayNotHasKey('email', $item);
            $this->assertArrayNotHasKey('cpf', $item);
            $this->assertArrayNotHasKey('phone', $item);
            $this->assertArrayNotHasKey('user_id', $item);
        }
    }

    /**
     * Apenas pontos active, offerings active e procedures published são retornados.
     */
    public function test_network_search_excludes_inactive_or_unpublished(): void
    {
        // 1. Offering inativa
        $offeringInativa = Offering::create([
            'tenant_id' => $this->tenantA->id,
            'service_point_id' => $this->pointA->id,
            'procedure_id' => $this->procedureA->id,
            'active' => false,
        ]);

        // 2. Ponto de atendimento inativo
        $pointInativo = ServicePoint::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->profA->id,
            'name' => 'Ponto Inativo',
            'cep' => '01310-200',
            'active' => false,
        ]);
        $procOutroA = Procedure::create([
            'tenant_id' => $this->tenantA->id,
            'title' => 'Outro Procedimento',
            'slug' => 'outro-procedimento',
            'category' => Procedure::CATEGORY_OUTROS,
            'status' => Procedure::STATUS_PUBLISHED,
        ]);
        Offering::create([
            'tenant_id' => $this->tenantA->id,
            'service_point_id' => $pointInativo->id,
            'procedure_id' => $procOutroA->id,
            'active' => true,
        ]);

        // 3. Procedimento não publicado (draft)
        $procDraft = Procedure::create([
            'tenant_id' => $this->tenantA->id,
            'title' => 'Procedimento Rascunho',
            'slug' => 'procedimento-rascunho',
            'category' => Procedure::CATEGORY_OUTROS,
            'status' => Procedure::STATUS_DRAFT,
        ]);
        Offering::create([
            'tenant_id' => $this->tenantA->id,
            'service_point_id' => $this->pointA->id,
            'procedure_id' => $procDraft->id,
            'active' => true,
        ]);

        // Busca por procedimento com ponto inativo
        $responseInativo = $this->getJson('/api/v1/public/network/offerings/search?procedure_slug=outro-procedimento');
        $responseInativo->assertStatus(200);
        $responseInativo->assertJsonCount(0, 'data');

        // Busca por procedimento em rascunho
        $responseDraft = $this->getJson('/api/v1/public/network/offerings/search?procedure_slug=procedimento-rascunho');
        $responseDraft->assertStatus(200);
        $responseDraft->assertJsonCount(0, 'data');
    }

    /**
     * Ordenação por proximidade quando lat/lng são informados.
     */
    public function test_network_search_orders_by_proximity_when_coordinates_given(): void
    {
        // Ponto A está em -23.5616840, -46.6559810
        // Ponto B está em -23.5780000, -46.6450000
        // Posição de busca próxima ao Ponto B (-23.577, -46.646)
        $response = $this->getJson(
            '/api/v1/public/network/offerings/search?procedure_slug=curativo-simples&lat=-23.5770000&lng=-46.6460000'
        );

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(2, $data);

        // Ponto B deve vir em primeiro lugar por estar mais próximo
        $this->assertSame($this->pointB->id, $data[0]['service_point_id']);
        $this->assertSame($this->pointA->id, $data[1]['service_point_id']);
    }

    /**
     * Ordenação por quality_score desc e nome asc quando não houver coordenadas.
     */
    public function test_network_search_orders_by_quality_score_desc_and_name_without_coordinates(): void
    {
        // Ponto A: quality_score 4.90, nome 'Ponto Paulista A'
        // Ponto B: quality_score 4.70, nome 'Ponto Paraíso B'
        $response = $this->getJson('/api/v1/public/network/offerings/search?procedure_slug=curativo-simples');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(2, $data);

        // Ponto A tem quality_score maior (4.90 vs 4.70), logo vem primeiro
        $this->assertSame($this->pointA->id, $data[0]['service_point_id']);
        $this->assertSame($this->pointB->id, $data[1]['service_point_id']);
    }

    /**
     * Parâmetro procedure_slug é obrigatório.
     */
    public function test_network_search_requires_procedure_slug(): void
    {
        $response = $this->getJson('/api/v1/public/network/offerings/search');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['procedure_slug']);
    }

    /**
     * Tenant inativo ou não existente no header X-Tenant-ID retorna 403.
     */
    public function test_network_search_with_invalid_or_inactive_tenant_header_returns_403(): void
    {
        $responseNaoExiste = $this->getJson(
            '/api/v1/public/network/offerings/search?procedure_slug=curativo-simples',
            ['X-Tenant-ID' => '00000000-0000-0000-0000-000000000000']
        );
        $responseNaoExiste->assertStatus(403);

        $tenantSuspenso = Tenant::create([
            'name' => 'Suspenso',
            'slug' => 'suspenso-' . uniqid(),
            'status' => 'suspended',
        ]);

        $responseSuspenso = $this->getJson(
            '/api/v1/public/network/offerings/search?procedure_slug=curativo-simples',
            ['X-Tenant-ID' => $tenantSuspenso->id]
        );
        $responseSuspenso->assertStatus(403);
    }

    /**
     * Header X-Tenant-ID ativo gera registro de auditoria.
     */
    public function test_network_search_with_active_tenant_header_logs_audit(): void
    {
        $initialAuditCount = AuditLog::count();

        $response = $this->getJson(
            '/api/v1/public/network/offerings/search?procedure_slug=curativo-simples',
            ['X-Tenant-ID' => $this->tenantB->id]
        );

        $response->assertStatus(200);

        $this->assertSame($initialAuditCount + 1, AuditLog::count());

        $log = AuditLog::where('action', 'network_offerings_search')
            ->where('tenant_id', $this->tenantB->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(Tenant::class, $log->auditable_type);
        $this->assertSame($this->tenantB->id, $log->auditable_id);
    }
}
