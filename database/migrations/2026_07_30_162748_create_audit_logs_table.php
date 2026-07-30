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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Relacionamentos e Contexto
            $table->uuid('tenant_id')->nullable()->index();
            $table->uuid('user_id')->nullable()->index();
            
            // Acao
            $table->string('action')->index(); // created, updated, deleted, login, accessed
            
            // Polimorfismo UUID
            $table->string('auditable_type')->nullable()->index();
            $table->uuid('auditable_id')->nullable()->index();
            
            // Dados (Criptografados com a mesma DEK do auditable_id)
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            
            // Metadados
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            
            // Append-only
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
