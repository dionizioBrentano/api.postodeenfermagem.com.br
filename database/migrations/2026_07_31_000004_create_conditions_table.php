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
        Schema::create('conditions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignUuid('patient_id')->constrained('patients')->onDelete('cascade');
            $table->foreignUuid('encounter_id')->constrained('encounters')->onDelete('cascade');
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade'); // Quem diagnosticou
            
            $table->string('code')->nullable(); // CID-10, não criptografado
            $table->text('description'); // CRIPTOGRAFADO
            $table->enum('status', ['active', 'resolved', 'inactive'])->default('active');

            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['tenant_id', 'patient_id', 'encounter_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conditions');
    }
};
