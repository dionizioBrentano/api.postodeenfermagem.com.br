<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Patient;
use App\Models\PatientUser;
use App\Models\Consent;
use App\Models\Encounter;
use App\Models\Observation;
use App\Models\Condition;
use App\Models\MedicationRequest;
use Illuminate\Database\Seeder;

class ClinicalDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'hospital-vida')->first();
        if (!$tenant) return;

        // Retrieve users
        $house = User::where('email', 'house@hospitalvida.com.br')->first();
        $jackie = User::where('email', 'jackie@hospitalvida.com.br')->first();
        $marcos = User::where('email', 'marcos@hospitalvida.com.br')->first();

        // Retrieve patients (by CPF)
        $joao = Patient::findByCpf('222.333.444-55');
        $ana = Patient::findByCpf('333.444.555-66');
        $carlos = Patient::findByCpf('44455566677');
        $beatriz = Patient::findByCpf('555.666.777-88');

        if (!$joao || !$ana || !$carlos || !$beatriz || !$house || !$jackie || !$marcos) {
            return;
        }

        // 3.1 Vínculos (patient_user)
        $vinculos = [
            ['patient_id' => $joao->id, 'user_id' => $house->id, 'tipo_vinculo' => 'medico_assistente'],
            ['patient_id' => $ana->id, 'user_id' => $house->id, 'tipo_vinculo' => 'medico_assistente'],
            ['patient_id' => $joao->id, 'user_id' => $jackie->id, 'tipo_vinculo' => 'enfermeiro'],
            ['patient_id' => $carlos->id, 'user_id' => $jackie->id, 'tipo_vinculo' => 'enfermeiro'],
            ['patient_id' => $beatriz->id, 'user_id' => $marcos->id, 'tipo_vinculo' => 'enfermeiro'],
        ];

        foreach ($vinculos as $v) {
            PatientUser::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'patient_id' => $v['patient_id'],
                    'user_id' => $v['user_id'],
                    'ativo' => true,
                ],
                [
                    'tipo_vinculo' => $v['tipo_vinculo'],
                    'data_inicio' => now()->subMonths(1),
                    'data_fim' => null,
                ]
            );
        }

        // 3.2 Consentimentos LGPD
        $patients = [$joao, $ana, $carlos, $beatriz];
        foreach ($patients as $p) {
            Consent::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'patient_id' => $p->id,
                    'status' => 'valid',
                ],
                [
                    'purposes' => ['atendimento_clinico', 'prontuario', 'compartilhamento_equipe'],
                    'data_categories' => ['dados_cadastrais', 'dados_clinicos'],
                    'valid_until' => now()->addYear(),
                    'revoked_at' => null,
                ]
            );
        }

        // 1 consentimento revogado histórico para João
        Consent::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'patient_id' => $joao->id,
                'status' => 'revoked',
            ],
            [
                'purposes' => ['atendimento_clinico'],
                'data_categories' => ['dados_cadastrais'],
                'valid_until' => now()->subDays(10),
                'revoked_at' => now()->subDays(10),
            ]
        );

        // 3.3 Encounters
        // João 1 (finished)
        $encJoao1 = Encounter::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'patient_id' => $joao->id,
                'user_id' => $house->id,
                'status' => 'finished',
            ],
            [
                'reason' => 'Consulta de rotina',
                'start_time' => now()->subDays(2)->setTime(10, 0),
                'end_time' => now()->subDays(2)->setTime(10, 30),
            ]
        );

        // João 2 (in-progress)
        $encJoao2 = Encounter::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'patient_id' => $joao->id,
                'user_id' => $house->id,
                'status' => 'in-progress',
            ],
            [
                'reason' => 'Retorno e avaliação de sintomas',
                'start_time' => now()->subMinutes(30),
                'end_time' => null,
            ]
        );

        // Ana (finished)
        Encounter::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'patient_id' => $ana->id,
                'user_id' => $house->id,
                'status' => 'finished',
            ],
            [
                'reason' => 'Acompanhamento hipertensão',
                'start_time' => now()->subDays(5)->setTime(14, 0),
                'end_time' => now()->subDays(5)->setTime(14, 20),
            ]
        );

        // Carlos (in-progress)
        Encounter::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'patient_id' => $carlos->id,
                'user_id' => $jackie->id,
                'status' => 'in-progress',
            ],
            [
                'reason' => 'Triagem inicial',
                'start_time' => now()->subMinutes(15),
                'end_time' => null,
            ]
        );

        // 3.4 Observations
        // João 1 (finished)
        Observation::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'patient_id' => $joao->id,
                'encounter_id' => $encJoao1->id,
                'user_id' => $house->id,
                'type' => 'vital-signs',
            ],
            [
                'content' => json_encode([
                    'systolic' => 120,
                    'diastolic' => 80,
                    'heart_rate' => 72,
                    'respiratory_rate' => 16,
                    'temperature' => 36.5,
                    'spo2' => 98,
                ]),
                'recorded_at' => now()->subDays(2)->setTime(10, 5),
            ]
        );

        Observation::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'patient_id' => $joao->id,
                'encounter_id' => $encJoao1->id,
                'user_id' => $house->id,
                'type' => 'evolution',
            ],
            [
                'content' => 'Paciente refere cefaleia leve há 2 dias. Sem febre. Orientado hidratação e observação.',
                'recorded_at' => now()->subDays(2)->setTime(10, 20),
            ]
        );

        // João 2 (in-progress)
        Observation::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'patient_id' => $joao->id,
                'encounter_id' => $encJoao2->id,
                'user_id' => $house->id,
                'type' => 'vital-signs',
            ],
            [
                'content' => json_encode([
                    'systolic' => 125,
                    'diastolic' => 82,
                    'heart_rate' => 76,
                    'respiratory_rate' => 18,
                    'temperature' => 37.1,
                    'spo2' => 97,
                ]),
                'recorded_at' => now()->subMinutes(25),
            ]
        );

        // 3.5 Conditions
        Condition::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'patient_id' => $joao->id,
                'user_id' => $house->id,
                'code' => 'J06.9',
            ],
            [
                'encounter_id' => $encJoao1->id,
                'description' => 'Infecção aguda das vias aéreas superiores',
                'status' => 'active',
            ]
        );

        Condition::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'patient_id' => $ana->id,
                'user_id' => $house->id,
                'code' => 'I10',
            ],
            [
                'encounter_id' => null,
                'description' => 'Hipertensão essencial (primária)',
                'status' => 'active',
            ]
        );

        // 3.6 MedicationRequests
        MedicationRequest::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'patient_id' => $joao->id,
                'user_id' => $house->id,
                'medication_details' => 'Dipirona 500mg — 1 comprimido a cada 6h se dor/febre — por 3 dias',
            ],
            [
                'encounter_id' => $encJoao1->id,
                'status' => 'active',
            ]
        );

        MedicationRequest::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'patient_id' => $ana->id,
                'user_id' => $house->id,
                'medication_details' => 'Losartana 50mg — 1 comprimido pela manhã — uso contínuo',
            ],
            [
                'encounter_id' => null,
                'status' => 'active',
            ]
        );
    }
}
