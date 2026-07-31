<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignUuid('patient_id')->constrained('patients')->onDelete('cascade');
            
            $table->enum('status', ['valid', 'revoked', 'expired'])->default('valid');
            $table->json('purposes')->nullable();
            $table->json('data_categories')->nullable();
            
            $table->dateTime('valid_until')->nullable();
            $table->dateTime('revoked_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
            
            // Um paciente pode ter vários históricos de consentimento, mas
            // índices para buscas rápidas são úteis.
            $table->index(['tenant_id', 'patient_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};
