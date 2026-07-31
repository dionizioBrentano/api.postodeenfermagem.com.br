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
        Schema::create('encounters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignUuid('patient_id')->constrained('patients')->onDelete('cascade');
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade'); // Profissional responsável
            
            $table->enum('status', ['planned', 'in-progress', 'finished', 'cancelled'])->default('in-progress');
            $table->string('reason')->nullable();
            
            $table->dateTime('start_time');
            $table->dateTime('end_time')->nullable();

            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['tenant_id', 'patient_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('encounters');
    }
};
