<?php

namespace Database\Seeders;

use App\Models\CareAuthorization;
use App\Models\Consent;
use App\Models\Patient;
use App\Models\PatientUser;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CareAuthorizationService;
use Illuminate\Database\Seeder;

/**
 * Complementa o seed do Hospital Vida com care_authorizations e
 * consents contextuais, sem inventar coluna domain (tenants usam slug).
 */
class ClinicalAccessSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'hospital-vida')->first();

        if (! $tenant) {
            $this->command?->warn('Tenant hospital-vida não encontrado. Rode DatabaseSeeder antes.');

            return;
        }

        $house = User::where('email', 'house@hospitalvida.com.br')->first();
        $jackie = User::where('email', 'jackie@hospitalvida.com.br')->first();
        $marcos = User::where('email', 'marcos@hospitalvida.com.br')->first();

        $joao = Patient::findByCpf('222.333.444-55');
        $ana = Patient::findByCpf('333.444.555-66');
        $carlos = Patient::findByCpf('44455566677');
        $beatriz = Patient::findByCpf('555.666.777-88');

        if (! $house || ! $jackie || ! $marcos || ! $joao || ! $ana || ! $carlos || ! $beatriz) {
            $this->command?->warn('Usuários/pacientes do Hospital Vida ausentes. Rode DatabaseSeeder antes.');

            return;
        }

        // Vínculos legados (compat patient_user)
        $vinculos = [
            [$joao, $house, 'medico_assistente'],
            [$ana, $house, 'medico_assistente'],
            [$joao, $jackie, 'enfermeiro'],
            [$carlos, $jackie, 'enfermeiro'],
            [$beatriz, $marcos, 'enfermeiro'],
        ];

        foreach ($vinculos as [$patient, $user, $tipo]) {
            PatientUser::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'patient_id' => $patient->id,
                    'user_id' => $user->id,
                    'ativo' => true,
                ],
                [
                    'tipo_vinculo' => $tipo,
                    'data_inicio' => now()->subMonths(1),
                    'data_fim' => null,
                ]
            );
        }

        // Care authorizations (House com delegate em João e Ana)
        $houseJoao = $this->ensureAuth($joao, $house, $house, ['clinical:read', 'clinical:write', 'delegate'], 'Seed House→João');
        $this->ensureAuth($ana, $house, $house, ['clinical:read', 'clinical:write', 'delegate'], 'Seed House→Ana');
        $this->ensureAuth($beatriz, $marcos, $marcos, ['clinical:read', 'clinical:write'], 'Seed Marcos→Beatriz');

        // Cadeia: House delega leitura/escrita a Jackie em João
        if ($houseJoao) {
            $this->ensureAuth(
                $joao,
                $house,
                $jackie,
                ['clinical:read', 'clinical:write'],
                'Delegação House→Jackie',
                $houseJoao
            );
        }

        // Consent contextual appointment_care (já valid) ligado a House/João
        $consentJoao = Consent::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'patient_id' => $joao->id,
                'context' => 'appointment_care',
                'professional_user_id' => $house->id,
                'status' => 'valid',
            ],
            [
                'purposes' => ['atendimento_clinico', 'prontuario', 'compartilhamento_equipe'],
                'data_categories' => ['dados_cadastrais', 'dados_clinicos'],
                'consent_text_version' => 'appointment_care_v1',
                'accepted_at' => now()->subDay(),
                'valid_until' => now()->addYear(),
            ]
        );

        // Garante source_consent_id na authorization do House se ainda não houver
        if ($houseJoao && ! $houseJoao->source_consent_id) {
            $houseJoao->update(['source_consent_id' => $consentJoao->id]);
        }

        // research_anonymized NÃO gera care_authorization
        Consent::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'patient_id' => $joao->id,
                'context' => 'research_anonymized',
                'status' => 'valid',
            ],
            [
                'purposes' => ['pesquisa_anonimizada'],
                'data_categories' => ['dados_clinicos_anonimizados'],
                'accepted_at' => now()->subDays(3),
            ]
        );

        $this->command?->info('ClinicalAccessSeeder OK — Hospital Vida (House/Jackie/João).');
        $this->command?->info('Login demo: house@hospitalvida.com.br / password');
    }

    private function ensureAuth(
        Patient $patient,
        User $grantor,
        User $grantee,
        array $scopes,
        string $reason,
        ?CareAuthorization $parent = null
    ): ?CareAuthorization {
        $existing = CareAuthorization::where('patient_id', $patient->id)
            ->where('grantee_user_id', $grantee->id)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            return $existing;
        }

        try {
            return CareAuthorizationService::grant(
                $patient,
                $grantor,
                $grantee,
                $scopes,
                $parent,
                null,
                now()->subDay(),
                null,
                $reason
            );
        } catch (\Throwable $e) {
            $this->command?->warn("Auth skip ({$reason}): {$e->getMessage()}");

            // Fallback direto se grantorHasPower falhar no primeiro seed
            return CareAuthorization::create([
                'tenant_id' => $patient->tenant_id,
                'patient_id' => $patient->id,
                'grantor_user_id' => $grantor->id,
                'grantee_user_id' => $grantee->id,
                'parent_authorization_id' => $parent?->id,
                'scope' => $scopes,
                'status' => 'active',
                'reason' => $reason,
                'starts_at' => now()->subDay(),
            ]);
        }
    }
}
