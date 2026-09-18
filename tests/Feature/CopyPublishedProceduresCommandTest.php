<?php

namespace Tests\Feature;

use App\Models\Offering;
use App\Models\Procedure;
use App\Models\ServicePoint;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CopyPublishedProceduresCommandTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $fromTenant;
    private Tenant $toTenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fromTenant = Tenant::create([
            'name' => 'Tenant Origem',
            'slug' => 'tenant-origem-' . uniqid(),
            'status' => 'active',
        ]);

        $this->toTenant = Tenant::create([
            'name' => 'Tenant Destino',
            'slug' => 'tenant-destino-' . uniqid(),
            'status' => 'active',
        ]);

        $this->user = User::create([
            'tenant_id' => $this->fromTenant->id,
            'name' => 'Profissional Teste',
            'email' => 'user.' . uniqid() . '@teste.com',
            'password' => Hash::make('Senha@123456'),
            'user_type' => 'professional',
        ]);
    }

    /**
     * Teste: copia procedures publicadas sem duplicar slug quando já existe no destino.
     */
    public function test_copy_published_procedures_does_not_duplicate_slug(): void
    {
        // Procedimento 1 na origem (publicado)
        Procedure::create([
            'tenant_id' => $this->fromTenant->id,
            'title' => 'Curativo Cirúrgico',
            'slug' => 'curativo-cirurgico',
            'category' => Procedure::CATEGORY_CURATIVOS_FERIDAS,
            'short_description' => 'Resumo do curativo na origem',
            'content' => '<p>Instruções detalhadas com <b>HTML</b></p>',
            'status' => Procedure::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        // Procedimento 2 na origem (publicado)
        Procedure::create([
            'tenant_id' => $this->fromTenant->id,
            'title' => 'Sondagem Nasogástrica',
            'slug' => 'sondagem-nasogastrica',
            'category' => Procedure::CATEGORY_SONDAS_ALIMENTARES,
            'short_description' => 'Resumo da sondagem',
            'content' => '<p>Conteúdo da sondagem</p>',
            'status' => Procedure::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        // Procedimento 3 na origem (rascunho - NÃO deve ser copiado)
        Procedure::create([
            'tenant_id' => $this->fromTenant->id,
            'title' => 'Procedimento em Rascunho',
            'slug' => 'procedimento-rascunho',
            'category' => Procedure::CATEGORY_OUTROS,
            'short_description' => 'Apenas rascunho',
            'content' => '<p>Não publicado</p>',
            'status' => Procedure::STATUS_DRAFT,
        ]);

        // Destino JÁ possui um procedimento com slug 'curativo-cirurgico' (não deve sobrescrever nem duplicar para slug-2)
        $existingDestProc = Procedure::create([
            'tenant_id' => $this->toTenant->id,
            'title' => 'Curativo Cirúrgico Próprio do Destino',
            'slug' => 'curativo-cirurgico',
            'category' => Procedure::CATEGORY_CURATIVOS_FERIDAS,
            'short_description' => 'Resumo original do destino',
            'content' => '<p>Conteúdo original do destino mantido</p>',
            'status' => Procedure::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        // Executa o comando
        $this->artisan('procedures:copy-published', [
            'fromTenantId' => $this->fromTenant->id,
            'toTenantId' => $this->toTenant->id,
        ])
            ->expectsOutput('1') // Apenas 1 procedimento deve ser copiado ('sondagem-nasogastrica')
            ->assertSuccessful();

        // 1. Slug existente não foi duplicado no destino (não foi criado 'curativo-cirurgico-2')
        $this->assertFalse(
            Procedure::withoutGlobalScope('tenant')
                ->where('tenant_id', $this->toTenant->id)
                ->where('slug', 'curativo-cirurgico-2')
                ->exists()
        );

        // 2. Destino possui exatamente UM registro com slug 'curativo-cirurgico'
        $curativoCount = Procedure::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->toTenant->id)
            ->where('slug', 'curativo-cirurgico')
            ->count();
        $this->assertSame(1, $curativoCount);

        // 3. Não sobrescreveu o procedimento já existente no destino
        $destCurativo = Procedure::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->toTenant->id)
            ->where('slug', 'curativo-cirurgico')
            ->first();
        $this->assertSame('Curativo Cirúrgico Próprio do Destino', $destCurativo->title);
        $this->assertSame($existingDestProc->id, $destCurativo->id);

        // 4. Procedimento novo ('sondagem-nasogastrica') foi copiado com o mesmo slug
        $copiedSondagem = Procedure::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->toTenant->id)
            ->where('slug', 'sondagem-nasogastrica')
            ->first();
        $this->assertNotNull($copiedSondagem);
        $this->assertSame('Sondagem Nasogástrica', $copiedSondagem->title);
        $this->assertSame(Procedure::STATUS_PUBLISHED, $copiedSondagem->status);

        // 5. Procedimento em rascunho não foi copiado
        $this->assertFalse(
            Procedure::withoutGlobalScope('tenant')
                ->where('tenant_id', $this->toTenant->id)
                ->where('slug', 'procedimento-rascunho')
                ->exists()
        );
    }

    /**
     * Teste: não copia service_points nem offerings.
     */
    public function test_copy_published_procedures_does_not_copy_service_points_or_offerings(): void
    {
        $point = ServicePoint::create([
            'tenant_id' => $this->fromTenant->id,
            'user_id' => $this->user->id,
            'name' => 'Ponto Origem',
            'cep' => '01310-100',
            'coverage_km' => 10.0,
            'active' => true,
        ]);

        $proc = Procedure::create([
            'tenant_id' => $this->fromTenant->id,
            'title' => 'Aplicação de Vacina',
            'slug' => 'aplicacao-de-vacina',
            'category' => Procedure::CATEGORY_APLICACAO_MEDICAMENTOS,
            'content' => '<p>Vacinas</p>',
            'status' => Procedure::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        Offering::create([
            'tenant_id' => $this->fromTenant->id,
            'service_point_id' => $point->id,
            'procedure_id' => $proc->id,
            'price' => 150.00,
            'active' => true,
        ]);

        $this->artisan('procedures:copy-published', [
            'fromTenantId' => $this->fromTenant->id,
            'toTenantId' => $this->toTenant->id,
        ])
            ->expectsOutput('1')
            ->assertSuccessful();

        // Destino não possui nenhum service_point nem offering criado
        $this->assertSame(
            0,
            ServicePoint::withoutGlobalScope('tenant')->where('tenant_id', $this->toTenant->id)->count()
        );
        $this->assertSame(
            0,
            Offering::withoutGlobalScope('tenant')->where('tenant_id', $this->toTenant->id)->count()
        );
    }
}
