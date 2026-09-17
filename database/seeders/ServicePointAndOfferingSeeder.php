<?php

namespace Database\Seeders;

use App\Models\Offering;
use App\Models\Procedure;
use App\Models\ServicePoint;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class ServicePointAndOfferingSeeder extends Seeder
{
    /**
     * Seed de pontos de atendimento e ofertas de procedimentos.
     * Escrito para demonstração / testes — não rodar automaticamente no ambiente local.
     */
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'hospital-vida')->first() ?? Tenant::first();

        if (! $tenant) {
            return;
        }

        $professional = User::where('tenant_id', $tenant->id)
            ->where('user_type', 'professional')
            ->first();

        if (! $professional) {
            return;
        }

        $procedure = Procedure::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('status', Procedure::STATUS_PUBLISHED)
            ->first();

        if (! $procedure) {
            return;
        }

        // Ponto de Atendimento 1
        $point1 = ServicePoint::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'user_id' => $professional->id,
                'cep' => '01310-100',
            ],
            [
                'name' => 'Ponto de Atendimento Centro - Paulista',
                'latitude' => -23.5616840,
                'longitude' => -46.6559810,
                'coverage_km' => 15.00,
                'quality_score' => 4.80,
                'active' => true,
            ]
        );

        // Ponto de Atendimento 2
        $point2 = ServicePoint::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'user_id' => $professional->id,
                'cep' => '04001-000',
            ],
            [
                'name' => 'Ponto de Atendimento Zona Sul - Paraíso',
                'latitude' => -23.5780000,
                'longitude' => -46.6450000,
                'coverage_km' => 10.00,
                'quality_score' => 4.50,
                'active' => true,
            ]
        );

        // 1 procedure ligada em offerings
        Offering::firstOrCreate(
            [
                'service_point_id' => $point1->id,
                'procedure_id' => $procedure->id,
            ],
            [
                'tenant_id' => $tenant->id,
                'active' => true,
            ]
        );
    }
}
