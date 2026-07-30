<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Tests\TestCase;

/**
 * Testes de verificação do Sprint 2 (Segurança de Dados):
 * - 2.1 Criptografia por registro (DEK/KEK, AES-256-GCM, crypto-shredding)
 * - 2.2 Tokenização (blind index) de CPF/CNS
 * - 2.3 Auditoria append-only
 *
 * Rode com: php artisan test --filter=Sprint2EncryptionAuditTest
 */
class Sprint2EncryptionAuditTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(): Tenant
    {
        return Tenant::create([
            'name' => 'Hospital Teste',
            'slug' => 'hospital-teste-'.uniqid(),
            'status' => 'active',
        ]);
    }

    public function test_patient_cpf_and_cns_are_never_stored_in_plaintext(): void
    {
        $tenant = $this->makeTenant();

        $patient = Patient::create([
            'tenant_id' => $tenant->id,
            'name' => 'Maria Silva',
            'cpf' => '123.456.789-00',
            'cns' => '898001234567890',
        ]);

        $rawCpf = DB::table('patients')->where('id', $patient->id)->value('cpf');
        $rawCns = DB::table('patients')->where('id', $patient->id)->value('cns');

        $this->assertStringNotContainsString('123456789', $rawCpf);
        $this->assertStringNotContainsString('898001234567890', $rawCns);
        $this->assertNotSame('123.456.789-00', $rawCpf);
    }

    public function test_patient_cpf_and_cns_round_trip_correctly_on_a_fresh_model_instance(): void
    {
        $tenant = $this->makeTenant();

        $patient = Patient::create([
            'tenant_id' => $tenant->id,
            'name' => 'Maria Silva',
            'cpf' => '123.456.789-00',
            'cns' => '898001234567890',
        ]);

        // Instância nova, forçando releitura do banco e da DEK persistida
        // (não reaproveita cache em memória da instância original).
        $fresh = Patient::find($patient->id);

        $this->assertSame('123.456.789-00', $fresh->cpf);
        $this->assertSame('898001234567890', $fresh->cns);
    }

    public function test_only_one_record_encryption_key_is_created_per_patient_even_with_multiple_encrypted_fields(): void
    {
        $tenant = $this->makeTenant();

        $patient = Patient::create([
            'tenant_id' => $tenant->id,
            'name' => 'Maria Silva',
            'cpf' => '123.456.789-00',
            'cns' => '898001234567890',
        ]);

        $keyCount = DB::table('record_encryption_keys')
            ->where('keyable_type', Patient::class)
            ->where('keyable_id', $patient->id)
            ->count();

        $this->assertSame(1, $keyCount, 'Deveria existir exatamente 1 DEK por paciente, compartilhada entre cpf e cns.');
    }

    public function test_blind_index_finds_patient_regardless_of_cpf_formatting(): void
    {
        $tenant = $this->makeTenant();

        Patient::create([
            'tenant_id' => $tenant->id,
            'name' => 'Maria Silva',
            'cpf' => '123.456.789-00',
            'cns' => '898001234567890',
        ]);

        $found = Patient::findByCpf('12345678900');

        $this->assertNotNull($found);
        $this->assertSame('Maria Silva', $found->name);
    }

    public function test_crypto_shredding_permanently_blocks_decryption(): void
    {
        $tenant = $this->makeTenant();

        $patient = Patient::create([
            'tenant_id' => $tenant->id,
            'name' => 'Maria Silva',
            'cpf' => '123.456.789-00',
            'cns' => '898001234567890',
        ]);

        $this->assertTrue($patient->shredEncryptionKey());

        $fresh = Patient::find($patient->id);

        $this->expectException(RuntimeException::class);
        $fresh->cpf; // deve lançar, pois a DEK foi destruída
    }

    public function test_user_council_number_is_encrypted_and_searchable_by_token(): void
    {
        $tenant = $this->makeTenant();

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Dr. House',
            'email' => 'house.'.uniqid().'@hospitalteste.com.br',
            'password' => 'password',
            'user_type' => 'professional',
            'council_type' => 'CRM',
            'council_number' => '12345',
        ]);

        $rawCouncilNumber = DB::table('users')->where('id', $user->id)->value('council_number');
        $this->assertStringNotContainsString('12345', $rawCouncilNumber);

        $found = User::findByCouncilNumber('12345');
        $this->assertNotNull($found);
        $this->assertSame($user->id, $found->id);

        $fresh = User::find($user->id);
        $this->assertSame('12345', $fresh->council_number);
    }

    public function test_creating_a_patient_automatically_writes_an_encrypted_audit_log(): void
    {
        $tenant = $this->makeTenant();

        $patient = Patient::create([
            'tenant_id' => $tenant->id,
            'name' => 'Maria Silva',
            'cpf' => '123.456.789-00',
            'cns' => '898001234567890',
        ]);

        $log = AuditLog::where('auditable_type', Patient::class)
            ->where('auditable_id', $patient->id)
            ->where('action', 'created')
            ->first();

        $this->assertNotNull($log, 'O evento de criação do paciente deveria ter gerado um AuditLog.');

        // Em repouso, o log não pode conter o CPF em texto puro.
        $rawNewValues = DB::table('audit_logs')->where('id', $log->id)->value('new_values');
        $this->assertStringNotContainsString('123456789', $rawNewValues);

        // Mas, através do model (DEK do próprio log), os valores decriptam corretamente.
        $decoded = json_decode($log->new_values, true);
        $this->assertSame('123.456.789-00', $decoded['cpf']);
        $this->assertArrayNotHasKey('cpf_token', $decoded, 'Tokens de blind index não devem vazar para o audit log.');
    }

    public function test_audit_logs_are_append_only(): void
    {
        $tenant = $this->makeTenant();

        Patient::create([
            'tenant_id' => $tenant->id,
            'name' => 'Maria Silva',
            'cpf' => '123.456.789-00',
            'cns' => '898001234567890',
        ]);

        $log = AuditLog::first();
        $this->assertNotNull($log);

        $this->expectException(LogicException::class);
        $log->delete();
    }
}
