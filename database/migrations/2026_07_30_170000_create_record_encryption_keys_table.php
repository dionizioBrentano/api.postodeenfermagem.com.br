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
        Schema::create('record_encryption_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();

            // Relação polimórfica: cada registro sensível (Patient, AuditLog, etc.)
            // possui exatamente uma DEK.
            $table->string('keyable_type');
            $table->uuid('keyable_id');

            // DEK envelopada (encriptada) com a KEK da aplicação. Nula quando a
            // chave foi destruída via crypto-shredding (ver revoked_at).
            $table->text('encrypted_dek')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            $table->unique(['keyable_type', 'keyable_id']);
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('record_encryption_keys');
    }
};
