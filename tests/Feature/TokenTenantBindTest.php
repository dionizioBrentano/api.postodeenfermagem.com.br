<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TokenTenantBindTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;

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
    }

    public function test_user_of_tenant_a_with_token_in_header_of_tenant_b_returns_403_tenant_forbidden(): void
    {
        $userA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Profissional Hospital Vida',
            'email' => 'vida@hospital.test',
            'password' => Hash::make('Senha@123456'),
            'user_type' => 'professional',
        ]);

        $tokenA = $userA->createToken('test-token')->plainTextToken;

        // Requisição para a Enfaci (tenant B) usando token emitido para usuário do Hospital Vida (tenant A)
        $response = $this->getJson('/api/v1/user', [
            'Authorization' => 'Bearer ' . $tokenA,
            'X-Tenant-ID' => $this->tenantB->id,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('code', 'tenant_forbidden');
        $response->assertJsonMissing([
            'email' => $userA->email,
            'name' => $userA->name,
        ]);
    }

    public function test_user_of_tenant_a_with_token_in_header_of_tenant_a_returns_200(): void
    {
        $userA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Profissional Hospital Vida',
            'email' => 'vida@hospital.test',
            'password' => Hash::make('Senha@123456'),
            'user_type' => 'professional',
        ]);

        $tokenA = $userA->createToken('test-token')->plainTextToken;

        // Requisição legítima para o Hospital Vida (tenant A)
        $response = $this->getJson('/api/v1/user', [
            'Authorization' => 'Bearer ' . $tokenA,
            'X-Tenant-ID' => $this->tenantA->id,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('id', $userA->id);
        $response->assertJsonPath('email', $userA->email);
    }

    public function test_super_admin_with_null_tenant_id_on_product_route_returns_403_tenant_forbidden(): void
    {
        $superAdmin = User::create([
            'tenant_id' => null,
            'name' => 'Super Administrador Global',
            'email' => 'superadmin@postodeenfermagem.test',
            'password' => Hash::make('Senha@123456'),
            'user_type' => 'admin',
        ]);

        $tokenSuper = $superAdmin->createToken('super-admin-token')->plainTextToken;

        // Rota de produto (/user) com tenant no header bloqueia super admin sem tenant vinculado
        $response = $this->getJson('/api/v1/user', [
            'Authorization' => 'Bearer ' . $tokenSuper,
            'X-Tenant-ID' => $this->tenantA->id,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('code', 'tenant_forbidden');
    }
}
