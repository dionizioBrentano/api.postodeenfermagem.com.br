<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Application;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Criar o Tenant de Demonstração
        $tenant = Tenant::create([
            'name' => 'Hospital Vida',
            'slug' => 'hospital-vida',
            'cnpj' => '00000000000000',
            'status' => 'active',
        ]);

        // 2. Criar a Aplicação Whitelabel para o Tenant
        $app = Application::create([
            'tenant_id' => $tenant->id,
            'name' => 'Frontend React Principal',
            'client_id' => 'frontend-app-vida',
            'client_secret' => Hash::make('secret123'),
            'scopes' => ['*'],
            'status' => 'active',
        ]);

        // 3. Criar Super Admin (Global, sem Tenant)
        User::create([
            'name' => 'Super Admin',
            'email' => 'admin@postodeenfermagem.com.br',
            'password' => 'password', // Auto-hashed by Casts
            'user_type' => 'admin',
            'tenant_id' => null,
            'mfa_enabled' => false,
        ]);

        // 4. Criar Profissionais do Tenant
        User::create([
            'name' => 'Dr. House',
            'email' => 'house@hospitalvida.com.br',
            'password' => 'password',
            'user_type' => 'professional',
            'tenant_id' => $tenant->id,
            'council_type' => 'CRM',
            'council_number' => '12345',
            'mfa_enabled' => false,
        ]);

        User::create([
            'name' => 'Enfermeira Jackie',
            'email' => 'jackie@hospitalvida.com.br',
            'password' => 'password',
            'user_type' => 'professional',
            'tenant_id' => $tenant->id,
            'council_type' => 'COREN',
            'council_number' => '98765',
            'mfa_enabled' => false,
        ]);
    }
}
