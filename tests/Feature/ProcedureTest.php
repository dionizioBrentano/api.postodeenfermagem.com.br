<?php

namespace Tests\Feature;

use App\Models\Procedure;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Módulo de Procedimentos de Enfermagem.
 *
 * Cobre: CRUD administrativo, publicação/arquivamento, restrição de papel,
 * isolamento multi-tenant, unicidade de slug por tenant, soft delete,
 * filtros de listagem, vitrine pública e sanitização do conteúdo.
 *
 * Rode com: php artisan test --filter=ProcedureTest
 */
class ProcedureTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $adminA;
    private User $nurseA;
    private User $adminB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Hospital Vida',
            'slug' => 'hospital-vida-test',
            'status' => 'active',
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Clínica Bem Estar',
            'slug' => 'clinica-bem-estar-test',
            'status' => 'active',
        ]);

        $this->adminA = $this->makeUser($this->tenantA, 'admin-a@vida.test', 'admin');
        $this->nurseA = $this->makeUser($this->tenantA, 'enf-a@vida.test', 'professional');
        $this->adminB = $this->makeUser($this->tenantB, 'admin-b@bemestar.test', 'admin');
    }

    private function makeUser(Tenant $tenant, string $email, string $type): User
    {
        return User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Usuário '.$email,
            'email' => $email,
            'password' => Hash::make('password'),
            'user_type' => $type,
            'mfa_enabled' => false,
        ]);
    }

    /**
     * Headers exigidos pelo middleware "tenant" (isolamento multi-tenant).
     *
     * @return array<string, string>
     */
    private function headers(?Tenant $tenant = null): array
    {
        return ['X-Tenant-ID' => ($tenant ?? $this->tenantA)->id];
    }

    /**
     * Abilities emitidas no login de um administrador (ver AuthController).
     *
     * @return array<int, string>
     */
    private function adminAbilities(): array
    {
        return ['tenant:admin', 'audit:read', 'patient:read', 'patient:write'];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeProcedure(Tenant $tenant, array $overrides = []): Procedure
    {
        return Procedure::create(array_merge([
            'tenant_id' => $tenant->id,
            'title' => 'Curativo Simples',
            'category' => Procedure::CATEGORY_CURATIVOS_FERIDAS,
            'short_description' => 'Limpeza e cobertura de feridas limpas.',
            'content' => '<h2>Objetivo</h2><p>Proteger a ferida.</p>',
            'status' => Procedure::STATUS_DRAFT,
        ], $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Sondagem Vesical de Alívio',
            'category' => Procedure::CATEGORY_ELIMINACOES,
            'short_description' => 'Cateterismo vesical intermitente.',
            'content' => '<h2>Objetivo</h2><p>Esvaziar a bexiga.</p>',
            'order' => 3,
        ], $overrides);
    }

    // ==========================================
    // CRIAÇÃO (ADMIN)
    // ==========================================

    public function test_admin_can_create_procedure(): void
    {
        Sanctum::actingAs($this->adminA, $this->adminAbilities());

        $response = $this->withHeaders($this->headers())
            ->postJson('/api/v1/procedures', $this->validPayload());

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Sondagem Vesical de Alívio')
            ->assertJsonPath('data.slug', 'sondagem-vesical-de-alivio')
            ->assertJsonPath('data.status', Procedure::STATUS_DRAFT)
            ->assertJsonPath('data.category', Procedure::CATEGORY_ELIMINACOES);

        $this->assertDatabaseHas('procedures', [
            'tenant_id' => $this->tenantA->id,
            'slug' => 'sondagem-vesical-de-alivio',
            'created_by' => $this->adminA->id,
        ]);
    }

    public function test_created_procedure_is_audited(): void
    {
        Sanctum::actingAs($this->adminA, $this->adminAbilities());

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/procedures', $this->validPayload())
            ->assertStatus(201);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Procedure::class,
            'action' => 'created',
            'user_id' => $this->adminA->id,
        ]);
    }

    public function test_store_validates_required_fields_and_category(): void
    {
        Sanctum::actingAs($this->adminA, $this->adminAbilities());

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/procedures', ['category' => 'categoria_inexistente'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'content', 'category']);
    }

    // ==========================================
    // RESTRIÇÃO DE PAPEL
    // ==========================================

    public function test_regular_user_cannot_create_procedure(): void
    {
        // Token real de profissional: não carrega a ability tenant:admin.
        Sanctum::actingAs($this->nurseA, ['patient:read', 'patient:write', 'clinical:read']);

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/procedures', $this->validPayload())
            ->assertStatus(403);
    }

    public function test_regular_user_is_blocked_by_policy_even_with_full_abilities(): void
    {
        // Mesmo com token irrestrito, a ProcedurePolicy barra quem não é admin.
        Sanctum::actingAs($this->nurseA, ['*']);

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/procedures', $this->validPayload())
            ->assertStatus(403);
    }

    public function test_regular_user_cannot_update_or_delete_procedure(): void
    {
        $procedure = $this->makeProcedure($this->tenantA);

        Sanctum::actingAs($this->nurseA, ['*']);

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/procedures/{$procedure->id}", ['title' => 'Alterado'])
            ->assertStatus(403);

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/procedures/{$procedure->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('procedures', [
            'id' => $procedure->id,
            'title' => 'Curativo Simples',
            'deleted_at' => null,
        ]);
    }

    public function test_regular_user_can_list_and_view_procedures(): void
    {
        $procedure = $this->makeProcedure($this->tenantA, ['status' => Procedure::STATUS_PUBLISHED, 'published_at' => now()]);

        Sanctum::actingAs($this->nurseA, ['patient:read']);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/procedures')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->withHeaders($this->headers())
            ->getJson("/api/v1/procedures/{$procedure->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $procedure->id);
    }

    public function test_unauthenticated_user_cannot_access_admin_routes(): void
    {
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/procedures')
            ->assertStatus(401);
    }

    // ==========================================
    // ATUALIZAÇÃO, PUBLICAÇÃO E ARQUIVAMENTO
    // ==========================================

    public function test_admin_can_update_procedure(): void
    {
        $procedure = $this->makeProcedure($this->tenantA);

        Sanctum::actingAs($this->adminA, $this->adminAbilities());

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/procedures/{$procedure->id}", [
                'title' => 'Curativo Simples com Técnica Asséptica',
                'order' => 7,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.title', 'Curativo Simples com Técnica Asséptica')
            ->assertJsonPath('data.order', 7);

        $this->assertDatabaseHas('procedures', [
            'id' => $procedure->id,
            'order' => 7,
            'updated_by' => $this->adminA->id,
            // O slug não acompanha a mudança de título: é URL pública.
            'slug' => $procedure->slug,
        ]);
    }

    public function test_admin_can_publish_and_unpublish_procedure(): void
    {
        $procedure = $this->makeProcedure($this->tenantA);

        Sanctum::actingAs($this->adminA, $this->adminAbilities());

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/procedures/{$procedure->id}/publish")
            ->assertStatus(200)
            ->assertJsonPath('data.status', Procedure::STATUS_PUBLISHED)
            ->assertJsonPath('data.is_published', true);

        $this->assertNotNull($procedure->fresh()->published_at);

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/procedures/{$procedure->id}/unpublish")
            ->assertStatus(200)
            ->assertJsonPath('data.status', Procedure::STATUS_DRAFT)
            ->assertJsonPath('data.is_published', false);

        $this->assertNull($procedure->fresh()->published_at);
    }

    public function test_admin_can_archive_procedure(): void
    {
        $procedure = $this->makeProcedure($this->tenantA, [
            'status' => Procedure::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($this->adminA, $this->adminAbilities());

        $this->withHeaders($this->headers())
            ->patchJson("/api/v1/procedures/{$procedure->id}", ['status' => Procedure::STATUS_ARCHIVED])
            ->assertStatus(200)
            ->assertJsonPath('data.status', Procedure::STATUS_ARCHIVED);

        // Arquivado sai imediatamente da vitrine pública.
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/public/procedures')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    // ==========================================
    // SOFT DELETE E RESTORE
    // ==========================================

    public function test_admin_can_soft_delete_and_restore_procedure(): void
    {
        $procedure = $this->makeProcedure($this->tenantA, [
            'status' => Procedure::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($this->adminA, $this->adminAbilities());

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/procedures/{$procedure->id}")
            ->assertStatus(200);

        // Soft delete: a linha continua no banco, com deleted_at preenchido.
        $this->assertSoftDeleted('procedures', ['id' => $procedure->id]);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/procedures')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');

        $this->withHeaders($this->headers())
            ->getJson("/api/v1/procedures/{$procedure->id}")
            ->assertStatus(404);

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/procedures/{$procedure->id}/restore")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $procedure->id);

        $this->assertDatabaseHas('procedures', [
            'id' => $procedure->id,
            'deleted_at' => null,
        ]);
    }

    // ==========================================
    // SLUG
    // ==========================================

    public function test_slug_is_generated_from_title_and_is_unique_per_tenant(): void
    {
        Sanctum::actingAs($this->adminA, $this->adminAbilities());

        $first = $this->withHeaders($this->headers())
            ->postJson('/api/v1/procedures', $this->validPayload())
            ->assertStatus(201);

        $second = $this->withHeaders($this->headers())
            ->postJson('/api/v1/procedures', $this->validPayload())
            ->assertStatus(201);

        $this->assertSame('sondagem-vesical-de-alivio', $first->json('data.slug'));
        $this->assertSame('sondagem-vesical-de-alivio-2', $second->json('data.slug'));
    }

    public function test_explicit_duplicate_slug_is_rejected_within_the_same_tenant(): void
    {
        $this->makeProcedure($this->tenantA, ['slug' => 'curativo-simples']);

        Sanctum::actingAs($this->adminA, $this->adminAbilities());

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/procedures', $this->validPayload(['slug' => 'curativo-simples']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');
    }

    public function test_same_slug_is_allowed_in_different_tenants(): void
    {
        $this->makeProcedure($this->tenantA, ['slug' => 'curativo-simples']);

        Sanctum::actingAs($this->adminB, $this->adminAbilities());

        $this->withHeaders($this->headers($this->tenantB))
            ->postJson('/api/v1/procedures', $this->validPayload([
                'title' => 'Curativo Simples',
                'slug' => 'curativo-simples',
            ]))
            ->assertStatus(201)
            ->assertJsonPath('data.slug', 'curativo-simples');

        $this->assertSame(
            2,
            Procedure::withoutGlobalScope('tenant')->where('slug', 'curativo-simples')->count()
        );
    }

    // ==========================================
    // ISOLAMENTO MULTI-TENANT
    // ==========================================

    public function test_procedure_from_another_tenant_is_not_visible(): void
    {
        $procedureB = $this->makeProcedure($this->tenantB, [
            'title' => 'Procedimento do Tenant B',
            'slug' => 'procedimento-do-tenant-b',
            'status' => Procedure::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($this->adminA, $this->adminAbilities());

        $this->withHeaders($this->headers($this->tenantA))
            ->getJson('/api/v1/procedures')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');

        $this->withHeaders($this->headers($this->tenantA))
            ->getJson("/api/v1/procedures/{$procedureB->id}")
            ->assertStatus(404);
    }

    public function test_admin_cannot_update_procedure_of_another_tenant(): void
    {
        $procedureB = $this->makeProcedure($this->tenantB, ['slug' => 'procedimento-b']);

        Sanctum::actingAs($this->adminA, $this->adminAbilities());

        $this->withHeaders($this->headers($this->tenantA))
            ->putJson("/api/v1/procedures/{$procedureB->id}", ['title' => 'Invadido'])
            ->assertStatus(404);

        $this->assertDatabaseHas('procedures', [
            'id' => $procedureB->id,
            'title' => 'Curativo Simples',
        ]);
    }

    public function test_public_listing_is_isolated_by_tenant(): void
    {
        $this->makeProcedure($this->tenantA, [
            'title' => 'Publicado no Tenant A',
            'slug' => 'publicado-no-tenant-a',
            'status' => Procedure::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);

        $this->makeProcedure($this->tenantB, [
            'title' => 'Publicado no Tenant B',
            'slug' => 'publicado-no-tenant-b',
            'status' => Procedure::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);

        $this->withHeaders($this->headers($this->tenantA))
            ->getJson('/api/v1/public/procedures')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'publicado-no-tenant-a');

        $this->withHeaders($this->headers($this->tenantB))
            ->getJson('/api/v1/public/procedures')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'publicado-no-tenant-b');
    }

    public function test_public_routes_require_tenant_header(): void
    {
        $this->getJson('/api/v1/public/procedures')->assertStatus(400);
    }

    // ==========================================
    // VITRINE PÚBLICA
    // ==========================================

    public function test_public_listing_returns_only_published_procedures(): void
    {
        $this->makeProcedure($this->tenantA, [
            'title' => 'Publicado',
            'slug' => 'publicado',
            'status' => Procedure::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);
        $this->makeProcedure($this->tenantA, ['title' => 'Rascunho', 'slug' => 'rascunho']);
        $this->makeProcedure($this->tenantA, [
            'title' => 'Arquivado',
            'slug' => 'arquivado',
            'status' => Procedure::STATUS_ARCHIVED,
        ]);
        $this->makeProcedure($this->tenantA, [
            'title' => 'Agendado',
            'slug' => 'agendado',
            'status' => Procedure::STATUS_PUBLISHED,
            'published_at' => now()->addWeek(),
        ]);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/public/procedures')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'publicado');
    }

    public function test_public_detail_is_served_by_slug_and_hides_unpublished(): void
    {
        $this->makeProcedure($this->tenantA, [
            'title' => 'Sondagem Nasogástrica',
            'slug' => 'sondagem-nasogastrica',
            'status' => Procedure::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);
        $this->makeProcedure($this->tenantA, ['title' => 'Rascunho', 'slug' => 'rascunho']);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/public/procedures/sondagem-nasogastrica')
            ->assertStatus(200)
            ->assertJsonPath('data.slug', 'sondagem-nasogastrica')
            // A vitrine pública não expõe metadados internos.
            ->assertJsonMissingPath('data.created_by')
            ->assertJsonMissingPath('data.status');

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/public/procedures/rascunho')
            ->assertStatus(404);
    }

    public function test_public_listing_omits_the_rich_content_body(): void
    {
        $this->makeProcedure($this->tenantA, [
            'slug' => 'curativo-simples',
            'status' => Procedure::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/public/procedures')
            ->assertStatus(200)
            ->assertJsonMissingPath('data.0.content')
            ->assertJsonPath('data.0.short_description', 'Limpeza e cobertura de feridas limpas.');
    }

    public function test_public_categories_endpoint_counts_published_procedures(): void
    {
        $this->makeProcedure($this->tenantA, [
            'slug' => 'curativo-a',
            'category' => Procedure::CATEGORY_CURATIVOS_FERIDAS,
            'status' => Procedure::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);
        $this->makeProcedure($this->tenantA, [
            'slug' => 'curativo-b-rascunho',
            'category' => Procedure::CATEGORY_CURATIVOS_FERIDAS,
        ]);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/public/procedures/categories')
            ->assertStatus(200);

        $categories = collect($response->json('data'))->keyBy('value');

        $this->assertSame(1, $categories[Procedure::CATEGORY_CURATIVOS_FERIDAS]['total']);
        $this->assertSame(0, $categories[Procedure::CATEGORY_VIAS_AEREAS]['total']);
    }

    // ==========================================
    // FILTROS
    // ==========================================

    public function test_index_filters_by_category(): void
    {
        $this->makeProcedure($this->tenantA, [
            'slug' => 'curativo',
            'category' => Procedure::CATEGORY_CURATIVOS_FERIDAS,
        ]);
        $this->makeProcedure($this->tenantA, [
            'title' => 'Aspiração de Vias Aéreas',
            'slug' => 'aspiracao-vias-aereas',
            'category' => Procedure::CATEGORY_VIAS_AEREAS,
        ]);

        Sanctum::actingAs($this->adminA, $this->adminAbilities());

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/procedures?category='.Procedure::CATEGORY_VIAS_AEREAS)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'aspiracao-vias-aereas');
    }

    public function test_index_filters_by_status_and_search(): void
    {
        $this->makeProcedure($this->tenantA, [
            'title' => 'Sondagem Vesical de Demora',
            'slug' => 'sondagem-vesical-de-demora',
            'status' => Procedure::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);
        $this->makeProcedure($this->tenantA, [
            'title' => 'Curativo Simples',
            'slug' => 'curativo-simples',
        ]);

        Sanctum::actingAs($this->adminA, $this->adminAbilities());

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/procedures?status='.Procedure::STATUS_PUBLISHED)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'sondagem-vesical-de-demora');

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/procedures?search=vesical')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'sondagem-vesical-de-demora');
    }

    public function test_index_rejects_invalid_filters(): void
    {
        Sanctum::actingAs($this->adminA, $this->adminAbilities());

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/procedures?status=inexistente')
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    // ==========================================
    // SANITIZAÇÃO DO CONTEÚDO
    // ==========================================

    public function test_content_is_sanitized_before_being_stored(): void
    {
        Sanctum::actingAs($this->adminA, $this->adminAbilities());

        $payload = $this->validPayload([
            'content' => '<h2>Objetivo</h2><script>alert(1)</script>'
                .'<p onclick="roubar()">Texto seguro</p>'
                .'<a href="javascript:alert(2)">link</a>'
                .'<img src="x" onerror="alert(3)">',
        ]);

        $response = $this->withHeaders($this->headers())
            ->postJson('/api/v1/procedures', $payload)
            ->assertStatus(201);

        $content = $response->json('data.content');

        $this->assertStringNotContainsString('<script', $content);
        $this->assertStringNotContainsString('alert(1)', $content);
        $this->assertStringNotContainsString('onclick', $content);
        $this->assertStringNotContainsString('onerror', $content);
        $this->assertStringNotContainsString('javascript:', $content);
        $this->assertStringContainsString('<h2>Objetivo</h2>', $content);
        $this->assertStringContainsString('Texto seguro', $content);
    }

    public function test_short_description_is_stored_as_plain_text(): void
    {
        Sanctum::actingAs($this->adminA, $this->adminAbilities());

        $response = $this->withHeaders($this->headers())
            ->postJson('/api/v1/procedures', $this->validPayload([
                'short_description' => '<b>Resumo</b> com <script>alert(1)</script>marcação',
            ]))
            ->assertStatus(201);

        $this->assertSame('Resumo com marcação', $response->json('data.short_description'));
    }

    // ==========================================
    // SEEDER
    // ==========================================

    public function test_seeder_publishes_the_procedure_catalog_for_every_tenant(): void
    {
        $this->seed(\Database\Seeders\ProcedureSeeder::class);

        $catalogSize = Procedure::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantA->id)
            ->count();

        $this->assertGreaterThanOrEqual(12, $catalogSize);

        $this->assertSame(
            $catalogSize,
            Procedure::withoutGlobalScope('tenant')->where('tenant_id', $this->tenantB->id)->count()
        );

        // Todas as cinco categorias principais estão contempladas e publicadas.
        $categories = Procedure::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantA->id)
            ->where('status', Procedure::STATUS_PUBLISHED)
            ->distinct()
            ->pluck('category');

        foreach ([
            Procedure::CATEGORY_APLICACAO_MEDICAMENTOS,
            Procedure::CATEGORY_CURATIVOS_FERIDAS,
            Procedure::CATEGORY_ELIMINACOES,
            Procedure::CATEGORY_VIAS_AEREAS,
            Procedure::CATEGORY_SONDAS_ALIMENTARES,
        ] as $category) {
            $this->assertContains($category, $categories->all());
        }
    }
}
