<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // ==========================================
        // SUPER ADMIN (Global, sem Tenant)
        // ==========================================
        User::firstOrCreate(
            ['email' => 'admin@postodeenfermagem.com.br'],
            [
                'name' => 'Super Admin',
                'password' => 'password',
                'user_type' => 'admin',
                'tenant_id' => null,
                'mfa_enabled' => false,
            ]
        );

        // ==========================================
        // INSTITUIÇÃO 1: Hospital Vida
        // (client_id/secret mantidos iguais ao que já está no frontend)
        // ==========================================
        $this->seedInstitution(
            tenantName: 'Hospital Vida',
            tenantSlug: 'hospital-vida',
            cnpj: '11111111000111',
            appName: 'Frontend React Principal',
            clientId: 'frontend-app-vida',
            clientSecret: 'secret123',
            professionals: [
                ['name' => 'Dr. House', 'email' => 'house@hospitalvida.com.br', 'council_type' => 'CRM', 'council_number' => '12345'],
                ['name' => 'Enfermeira Jackie', 'email' => 'jackie@hospitalvida.com.br', 'council_type' => 'COREN', 'council_number' => '98765'],
                ['name' => 'Enfermeiro Marcos Vieira', 'email' => 'marcos@hospitalvida.com.br', 'council_type' => 'COREN', 'council_number' => '55555'],
            ],
            patientPortalUsers: [
                ['name' => 'Paciente Teste', 'email' => 'paciente@hospitalvida.com.br'],
            ],
            patients: [
                ['name' => 'João Pereira', 'cpf' => '222.333.444-55', 'cns' => '700000000000001'],
                ['name' => 'Ana Costa', 'cpf' => '333.444.555-66', 'cns' => null],
                ['name' => 'Carlos Souza', 'cpf' => '44455566677', 'cns' => '700000000000003'], // CPF sem pontuação, de propósito
                ['name' => 'Beatriz Lima', 'cpf' => '555.666.777-88', 'cns' => '700000000000005'],
            ],
        );

        // ==========================================
        // INSTITUIÇÃO 2: Clínica Bem Estar
        // ==========================================
        $this->seedInstitution(
            tenantName: 'Clínica Bem Estar',
            tenantSlug: 'clinica-bem-estar',
            cnpj: '22222222000122',
            appName: 'Frontend Clínica Bem Estar',
            clientId: 'frontend-app-bemestar',
            clientSecret: 'secret456',
            professionals: [
                ['name' => 'Dra. Ellie Sattler', 'email' => 'ellie@clinicabemestar.com.br', 'council_type' => 'CRM', 'council_number' => '22222'],
            ],
            patientPortalUsers: [],
            patients: [
                ['name' => 'Fernanda Alves', 'cpf' => '666.777.888-99', 'cns' => '700000000000007'],
                ['name' => 'Pedro Henrique', 'cpf' => '777.888.999-00', 'cns' => null],
            ],
        );

        // ==========================================
        // INSTITUIÇÃO 3: UBS Jardim das Flores
        // ==========================================
        $this->seedInstitution(
            tenantName: 'UBS Jardim das Flores',
            tenantSlug: 'ubs-jardim-flores',
            cnpj: '33333333000133',
            appName: 'Frontend UBS Jardim das Flores',
            clientId: 'frontend-app-ubs',
            clientSecret: 'secret789',
            professionals: [
                ['name' => 'Enfermeiro Ian Malcolm', 'email' => 'ian@ubsjardimflores.com.br', 'council_type' => 'COREN', 'council_number' => '33333'],
            ],
            patientPortalUsers: [],
            patients: [
                ['name' => 'Lucia Fernandes', 'cpf' => '888.999.000-11', 'cns' => '700000000000009'],
                ['name' => 'Roberto Dias', 'cpf' => '999.000.111-22', 'cns' => '700000000000010'],
            ],
        );
    }

    /**
     * Cria uma instituição (tenant) completa: aplicação whitelabel,
     * profissionais, contas de portal do paciente e pacientes (registros
     * clínicos, criptografados por registro).
     */
    private function seedInstitution(
        string $tenantName,
        string $tenantSlug,
        string $cnpj,
        string $appName,
        string $clientId,
        string $clientSecret,
        array $professionals,
        array $patientPortalUsers,
        array $patients,
    ): void {
        $tenant = Tenant::firstOrCreate(
            ['slug' => $tenantSlug],
            [
                'name' => $tenantName,
                'cnpj' => $cnpj,
                'status' => 'active',
            ]
        );

        Application::firstOrCreate(
            ['client_id' => $clientId],
            [
                'tenant_id' => $tenant->id,
                'name' => $appName,
                'client_secret' => Hash::make($clientSecret),
                'scopes' => ['*'],
                'status' => 'active',
            ]
        );

        foreach ($professionals as $professional) {
            User::firstOrCreate(
                ['email' => $professional['email']],
                [
                    'name' => $professional['name'],
                    'password' => 'password',
                    'user_type' => 'professional',
                    'tenant_id' => $tenant->id,
                    'council_type' => $professional['council_type'],
                    'council_number' => $professional['council_number'],
                    'mfa_enabled' => false,
                ]
            );
        }

        foreach ($patientPortalUsers as $portalUser) {
            User::firstOrCreate(
                ['email' => $portalUser['email']],
                [
                    'name' => $portalUser['name'],
                    'password' => 'password',
                    'user_type' => 'patient',
                    'tenant_id' => $tenant->id,
                    'mfa_enabled' => false,
                ]
            );
        }

        foreach ($patients as $patientData) {
            // Como cpf/cns são criptografados, não dá pra usar firstOrCreate
            // direto pela coluna (o valor em repouso é diferente a cada
            // execução). Usamos o blind index (cpf_token) pra checar se já
            // existe, evitando duplicar ao rodar o seeder mais de uma vez.
            $existing = Patient::findByCpf($patientData['cpf']);

            if ($existing) {
                continue;
            }

            Patient::create([
                'tenant_id' => $tenant->id,
                'name' => $patientData['name'],
                'cpf' => $patientData['cpf'],
                'cns' => $patientData['cns'],
            ]);
        }
    }
}
