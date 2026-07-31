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
        Schema::create('observations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignUuid('patient_id')->constrained('patients')->onDelete('cascade');
            $table->foreignUuid('encounter_id')->constrained('encounters')->onDelete('cascade');
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade'); // Quem registrou
            
            $table->string('type'); // 'vital-signs', 'evolution', 'other'
            $table->text('content'); // CRIPTOGRAFADO (JSON para vital-signs ou string para evolução)
            $table->dateTime('recorded_at');

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
        Schema::dropIfExists('observations');
    }
};
