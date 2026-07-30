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
        Schema::create('patients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->onDelete('cascade');

            $table->string('name');

            // Nunca armazenados em texto puro: cpf/cns guardam o payload
            // criptografado (base64 iv+ciphertext+tag); *_token guarda o
            // blind index (HMAC) usado para busca exata sem descriptografar.
            $table->text('cpf')->nullable();
            $table->string('cpf_token', 64)->nullable();
            $table->text('cns')->nullable();
            $table->string('cns_token', 64)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('cpf_token');
            $table->index('cns_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
