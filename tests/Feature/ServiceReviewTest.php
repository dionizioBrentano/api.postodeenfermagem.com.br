<?php

namespace Tests\Feature;

use App\Http\Controllers\AuthController;
use App\Models\Procedure;
use App\Models\ServicePoint;
use App\Models\ServiceRequest;
use App\Models\ServiceReview;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ServiceReviewTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Procedure $procedure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Posto Central Saúde',
            'slug' => 'posto-central-' . uniqid(),
            'status' => 'active',
        ]);

        $this->procedure = Procedure::create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Curativo Cirúrgico',
            'slug' => 'curativo-cirurgico',
            'category' => Procedure::CATEGORY_CURATIVOS_FERIDAS,
            'content' => '<p>Instruções de curativo</p>',
            'status' => Procedure::STATUS_PUBLISHED,
            'published_at' => now(),
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

    private function createServiceRequest(User $client, string $status = ServiceRequest::STATUS_DONE, array $overrides = []): ServiceRequest
    {
        return ServiceRequest::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'client_user_id' => $client->id,
            'procedure_id' => $this->procedure->id,
            'cep_servico' => '01310-100',
            'slot_date' => now()->subDay()->format('Y-m-d'),
            'slot_window' => 'manha',
            'status' => $status,
            'notes_cliente' => 'Nota do pedido',
        ], $overrides));
    }

    /**
     * Teste Obrigatório 1: review sem status done retorna 422.
     */
    public function test_review_without_done_status_returns_422(): void
    {
        [$client, $token] = $this->createMfaVerifiedUser($this->tenant, 'patient');

        // Pedido com status 'requested' (não concluído)
        $request = $this->createServiceRequest($client, ServiceRequest::STATUS_REQUESTED);

        $response = $this->postJson(
            "/api/v1/service-requests/{$request->id}/review",
            [
                'stars' => 5,
                'body' => 'Atendimento muito bom.',
                'anonymous' => true,
            ],
            $this->headers($this->tenant, $token)
        );

        $response->assertStatus(422);
        $response->assertJsonPath('code', 'service_request_not_done');
    }

    /**
     * Teste Obrigatório 2: outro cliente tentando avaliar pedido alheio retorna 403.
     */
    public function test_another_client_cannot_review_service_request_returns_403(): void
    {
        [$clientDono, $tokenDono] = $this->createMfaVerifiedUser($this->tenant, 'patient');
        [$outroCliente, $tokenOutro] = $this->createMfaVerifiedUser($this->tenant, 'patient');

        $request = $this->createServiceRequest($clientDono, ServiceRequest::STATUS_DONE);

        // Outro cliente tenta enviar review para o pedido do cliente dono -> 403
        $responsePost = $this->postJson(
            "/api/v1/service-requests/{$request->id}/review",
            [
                'stars' => 4,
                'body' => 'Tentativa não autorizada.',
                'anonymous' => true,
            ],
            $this->headers($this->tenant, $tokenOutro)
        );

        $responsePost->assertStatus(403);
        $responsePost->assertJsonPath('code', 'forbidden');

        // Cria a avaliação legítima pelo cliente dono
        $review = ServiceReview::create([
            'tenant_id' => $this->tenant->id,
            'service_request_id' => $request->id,
            'client_user_id' => $clientDono->id,
            'stars' => 5,
            'body' => 'Avaliação legítima do dono.',
            'anonymous' => true,
            'publish_requested' => false,
        ]);

        // Outro cliente tenta consultar o review do pedido -> 403
        $responseGetByRequest = $this->getJson(
            "/api/v1/service-requests/{$request->id}/review",
            $this->headers($this->tenant, $tokenOutro)
        );
        $responseGetByRequest->assertStatus(403);
        $responseGetByRequest->assertJsonPath('code', 'forbidden');

        // Outro cliente tenta consultar o review por ID -> 403
        $responseGetById = $this->getJson(
            "/api/v1/service-reviews/{$review->id}",
            $this->headers($this->tenant, $tokenOutro)
        );
        $responseGetById->assertStatus(403);
        $responseGetById->assertJsonPath('code', 'forbidden');
    }

    /**
     * Teste Obrigatório 3: vitrine pública não lista reviews não publicados.
     */
    public function test_public_reviews_does_not_list_unpublished_reviews(): void
    {
        [$clientA] = $this->createMfaVerifiedUser($this->tenant, 'patient');
        [$clientB] = $this->createMfaVerifiedUser($this->tenant, 'patient');

        $requestA = $this->createServiceRequest($clientA, ServiceRequest::STATUS_DONE);
        $requestB = $this->createServiceRequest($clientB, ServiceRequest::STATUS_DONE);

        // Review A: Publicado
        $publishedReview = ServiceReview::create([
            'tenant_id' => $this->tenant->id,
            'service_request_id' => $requestA->id,
            'client_user_id' => $clientA->id,
            'stars' => 5,
            'body' => 'Depoimento publicado excelente.',
            'anonymous' => true,
            'publish_requested' => true,
            'published_at' => now(),
        ]);

        // Review B: Não publicado (published_at null)
        $unpublishedReview = ServiceReview::create([
            'tenant_id' => $this->tenant->id,
            'service_request_id' => $requestB->id,
            'client_user_id' => $clientB->id,
            'stars' => 4,
            'body' => 'Avaliação privada ainda não publicada.',
            'anonymous' => true,
            'publish_requested' => true,
            'published_at' => null,
        ]);

        $response = $this->getJson(
            "/api/v1/public/reviews?procedure_slug={$this->procedure->slug}",
            $this->headers($this->tenant)
        );

        $response->assertStatus(200);

        // Deve conter o review publicado
        $response->assertJsonFragment([
            'id' => $publishedReview->id,
            'body' => 'Depoimento publicado excelente.',
        ]);

        // NÃO deve conter o review não publicado
        $response->assertJsonMissing([
            'id' => $unpublishedReview->id,
            'body' => 'Avaliação privada ainda não publicada.',
        ]);
    }

    /**
     * Teste: prazo de 14 dias expirado retorna 422.
     */
    public function test_client_cannot_review_after_14_days_returns_422(): void
    {
        [$client, $token] = $this->createMfaVerifiedUser($this->tenant, 'patient');

        $request = $this->createServiceRequest($client, ServiceRequest::STATUS_DONE);

        // Simula pedido concluído há 15 dias
        $request->updated_at = now()->subDays(15);
        $request->save();

        $response = $this->postJson(
            "/api/v1/service-requests/{$request->id}/review",
            [
                'stars' => 5,
                'body' => 'Avaliação fora do prazo.',
            ],
            $this->headers($this->tenant, $token)
        );

        $response->assertStatus(422);
        $response->assertJsonPath('code', 'review_deadline_expired');
    }

    /**
     * Teste: não pode avaliar o mesmo pedido duas vezes (única por pedido).
     */
    public function test_client_cannot_review_same_request_twice_returns_422(): void
    {
        [$client, $token] = $this->createMfaVerifiedUser($this->tenant, 'patient');
        $request = $this->createServiceRequest($client, ServiceRequest::STATUS_DONE);

        // Primeira avaliação
        $firstResponse = $this->postJson(
            "/api/v1/service-requests/{$request->id}/review",
            [
                'stars' => 5,
                'body' => 'Primeira avaliação.',
                'anonymous' => true,
            ],
            $this->headers($this->tenant, $token)
        );
        $firstResponse->assertStatus(201);

        // Segunda tentativa para o mesmo pedido -> 422
        $secondResponse = $this->postJson(
            "/api/v1/service-requests/{$request->id}/review",
            [
                'stars' => 4,
                'body' => 'Segunda avaliação não permitida.',
            ],
            $this->headers($this->tenant, $token)
        );
        $secondResponse->assertStatus(422);
        $secondResponse->assertJsonPath('code', 'review_already_exists');
    }

    /**
     * Teste: cliente dono e equipe do tenant conseguem visualizar a avaliação do pedido.
     */
    public function test_client_and_tenant_staff_can_view_review(): void
    {
        [$client, $clientToken] = $this->createMfaVerifiedUser($this->tenant, 'patient');
        [$professional, $profToken] = $this->createMfaVerifiedUser($this->tenant, 'professional');
        $request = $this->createServiceRequest($client, ServiceRequest::STATUS_DONE);

        $review = ServiceReview::create([
            'tenant_id' => $this->tenant->id,
            'service_request_id' => $request->id,
            'client_user_id' => $client->id,
            'stars' => 5,
            'body' => 'Enfermeira atenciosa e pontual.',
            'anonymous' => false,
            'publish_requested' => true,
        ]);

        // Cliente dono consulta via /service-requests/{id}/review
        $resOwner = $this->getJson(
            "/api/v1/service-requests/{$request->id}/review",
            $this->headers($this->tenant, $clientToken)
        );
        $resOwner->assertStatus(200);
        $resOwner->assertJsonPath('data.id', $review->id);

        // Profissional consulta via /service-requests/{id}/review
        $resStaff = $this->getJson(
            "/api/v1/service-requests/{$request->id}/review",
            $this->headers($this->tenant, $profToken)
        );
        $resStaff->assertStatus(200);
        $resStaff->assertJsonPath('data.id', $review->id);
    }

    /**
     * Teste: profissional pode publicar se publish_requested for true.
     */
    public function test_professional_can_publish_review_when_publish_requested_is_true(): void
    {
        [$client] = $this->createMfaVerifiedUser($this->tenant, 'patient');
        [$prof, $profToken] = $this->createMfaVerifiedUser($this->tenant, 'professional');
        $request = $this->createServiceRequest($client, ServiceRequest::STATUS_DONE);

        $review = ServiceReview::create([
            'tenant_id' => $this->tenant->id,
            'service_request_id' => $request->id,
            'client_user_id' => $client->id,
            'stars' => 5,
            'body' => 'Serviço nota 10.',
            'anonymous' => false,
            'publish_requested' => true,
            'published_at' => null,
        ]);

        $response = $this->patchJson(
            "/api/v1/service-reviews/{$review->id}/publish",
            [],
            $this->headers($this->tenant, $profToken)
        );

        $response->assertStatus(200);
        $response->assertJsonPath('data.published_by', $prof->id);
        $this->assertNotNull($response->json('data.published_at'));

        $this->assertDatabaseHas('service_reviews', [
            'id' => $review->id,
            'published_by' => $prof->id,
        ]);
    }

    /**
     * Teste: publicação bloqueada se publish_requested for false.
     */
    public function test_professional_cannot_publish_review_when_publish_requested_is_false_returns_422(): void
    {
        [$client] = $this->createMfaVerifiedUser($this->tenant, 'patient');
        [$prof, $profToken] = $this->createMfaVerifiedUser($this->tenant, 'professional');
        $request = $this->createServiceRequest($client, ServiceRequest::STATUS_DONE);

        $review = ServiceReview::create([
            'tenant_id' => $this->tenant->id,
            'service_request_id' => $request->id,
            'client_user_id' => $client->id,
            'stars' => 5,
            'body' => 'Depoimento privado.',
            'anonymous' => false,
            'publish_requested' => false,
            'published_at' => null,
        ]);

        $response = $this->patchJson(
            "/api/v1/service-reviews/{$review->id}/publish",
            [],
            $this->headers($this->tenant, $profToken)
        );

        $response->assertStatus(422);
        $response->assertJsonPath('code', 'publish_not_requested');
    }

    /**
     * Teste: paciente comum não tem permissão para publicar review (403).
     */
    public function test_patient_cannot_publish_review_returns_403(): void
    {
        [$client, $clientToken] = $this->createMfaVerifiedUser($this->tenant, 'patient');
        $request = $this->createServiceRequest($client, ServiceRequest::STATUS_DONE);

        $review = ServiceReview::create([
            'tenant_id' => $this->tenant->id,
            'service_request_id' => $request->id,
            'client_user_id' => $client->id,
            'stars' => 5,
            'body' => 'Texto da avaliação.',
            'anonymous' => false,
            'publish_requested' => true,
        ]);

        $response = $this->patchJson(
            "/api/v1/service-reviews/{$review->id}/publish",
            [],
            $this->headers($this->tenant, $clientToken)
        );

        $response->assertStatus(403);
        $response->assertJsonPath('code', 'forbidden');
    }

    /**
     * Teste: vitrine pública respeita anonimato e nunca expõe PII (e-mail, CPF, telefone).
     */
    public function test_public_reviews_privacy_and_anonymity(): void
    {
        [$clientAnon] = $this->createMfaVerifiedUser($this->tenant, 'patient', [
            'name' => 'Carlos Eduardo Pereira',
            'email' => 'carlos.pereira@exemplo.com',
            'cpf' => '12345678901',
            'phone' => '11988887777',
        ]);

        [$clientNome] = $this->createMfaVerifiedUser($this->tenant, 'patient', [
            'name' => 'Juliana Silva Souza',
            'email' => 'juliana.souza@exemplo.com',
            'cpf' => '98765432100',
            'phone' => '11977776666',
        ]);

        $requestAnon = $this->createServiceRequest($clientAnon, ServiceRequest::STATUS_DONE);
        $requestNome = $this->createServiceRequest($clientNome, ServiceRequest::STATUS_DONE);

        // Review Anônimo Publicado
        $reviewAnon = ServiceReview::create([
            'tenant_id' => $this->tenant->id,
            'service_request_id' => $requestAnon->id,
            'client_user_id' => $clientAnon->id,
            'stars' => 5,
            'body' => 'Ótimo atendimento domiciliar.',
            'anonymous' => true,
            'publish_requested' => true,
            'published_at' => now(),
        ]);

        // Review com Nome Publicado
        $reviewNome = ServiceReview::create([
            'tenant_id' => $this->tenant->id,
            'service_request_id' => $requestNome->id,
            'client_user_id' => $clientNome->id,
            'stars' => 5,
            'body' => 'Super recomendo a equipe.',
            'anonymous' => false,
            'publish_requested' => true,
            'published_at' => now(),
        ]);

        $response = $this->getJson(
            "/api/v1/public/reviews?procedure_slug={$this->procedure->slug}",
            $this->headers($this->tenant)
        );

        $response->assertStatus(200);

        // Review Anônimo não deve ter nome
        $response->assertJsonFragment([
            'id' => $reviewAnon->id,
            'anonymous' => true,
            'client_name' => null,
        ]);

        // Review não-anônimo deve conter apenas o primeiro nome
        $response->assertJsonFragment([
            'id' => $reviewNome->id,
            'anonymous' => false,
            'client_name' => 'Juliana',
        ]);

        // Garante que NUNCA vazou sobrenome completo nem dados confidenciais
        $response->assertJsonMissing([
            'client_name' => 'Juliana Silva Souza',
            'email' => 'juliana.souza@exemplo.com',
            'cpf' => '98765432100',
            'phone' => '11977776666',
            'client_user_id' => $clientNome->id,
        ]);
        $response->assertJsonMissing([
            'email' => 'carlos.pereira@exemplo.com',
            'cpf' => '12345678901',
            'phone' => '11988887777',
            'client_user_id' => $clientAnon->id,
        ]);
    }
}
