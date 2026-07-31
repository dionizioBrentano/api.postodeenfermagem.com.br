<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Patient;
use App\Models\PatientUser;
use App\Models\Consent;
use App\Services\CareAuthorizationService;
use Illuminate\Support\Facades\Hash;

class ClinicalAccessSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['domain' => 'demo.postodeenfermagem.com.br'],
            ['name' => 'Demo Clinic']
        );

        $password = Hash::make('password123');

        // Profissionais
        $draAna = User::firstOrCreate(
            ['email' => 'ana@demo.com'],
            ['name' => 'Dra. Ana', 'tenant_id' => $tenant->id, 'password' => $password]
        );

        $drCarlos = User::firstOrCreate(
            ['email' => 'carlos@demo.com'],
            ['name' => 'Dr. Carlos', 'tenant_id' => $tenant->id, 'password' => $password]
        );

        $enfZ = User::firstOrCreate(
            ['email' => 'enfermeiraz@demo.com'],
            ['name' => 'Enfermeira Z', 'tenant_id' => $tenant->id, 'password' => $password]
        );

        // Pacientes
        $joao = Patient::firstOrCreate(
            ['cpf' => '111.111.111-11'],
            ['name' => 'João Silva', 'tenant_id' => $tenant->id, 'birth_date' => '1990-01-01']
        );

        $maria = Patient::firstOrCreate(
            ['cpf' => '222.222.222-22'],
            ['name' => 'Maria Souza', 'tenant_id' => $tenant->id, 'birth_date' => '1995-05-05']
        );

        // 1. Vínculo via PatientUser legado (Dra. Ana <-> João) -> Deve criar o espelho implicitamente
        $link = PatientUser::firstOrCreate([
            'patient_id' => $joao->id,
            'user_id' => $draAna->id,
            'ativo' => true
        ]);
        
        try {
            CareAuthorizationService::grant($joao, $draAna, $draAna, ['clinical:read', 'clinical:write', 'delegate'], null, null, now(), null, 'Seeder legacy compat');
        } catch (\Exception $e) {}

        // 2. Consentimento institutional (Maria -> Clínica) -> Acesso para todos
        $mariaConsent = Consent::firstOrCreate(
            ['patient_id' => $maria->id, 'context' => 'institutional_care', 'status' => 'valid'],
            [
                'purposes' => ['general_care'],
                'data_categories' => ['all'],
                'accepted_at' => now(),
            ]
        );

        // 3. Consentimento appointment (João -> Dr Carlos) -> Gera authorization direta para Dr Carlos.
        $joaoConsent = Consent::firstOrCreate(
            ['patient_id' => $joao->id, 'context' => 'appointment_care', 'professional_user_id' => $drCarlos->id, 'status' => 'valid'],
            [
                'purposes' => ['consultation'],
                'data_categories' => ['all'],
                'accepted_at' => now(),
            ]
        );
        try {
            CareAuthorizationService::grantFromConsent($joaoConsent, $drCarlos, ['clinical:read', 'clinical:write', 'delegate']);
        } catch (\Exception $e) {}

        // 4. CareAuthorization delegada: Dra. Ana delega leitura para Enfermeira Z (João)
        try {
            CareAuthorizationService::grant($joao, $draAna, $enfZ, ['clinical:read'], null, null, now(), null, 'Delegation to nurse');
        } catch (\Exception $e) {}

        $this->command->info('ClinicalAccessSeeder completed!');
    }
}
