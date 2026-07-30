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
            $table->uuid('record_id')->unique()->comment('UUID do registro criptografado (ex: paciente, prontuario)');
            $table->text('encrypted_dek')->comment('Data Encryption Key criptografada com KEK');
            $table->timestamps();
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
