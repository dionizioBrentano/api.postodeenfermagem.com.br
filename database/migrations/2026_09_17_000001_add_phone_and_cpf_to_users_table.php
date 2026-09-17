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
        Schema::table('users', function (Blueprint $table) {
            $table->text('phone')->nullable()->after('email');
            $table->string('phone_token', 64)->nullable()->after('phone')->index();
            $table->text('cpf')->nullable()->after('phone_token');
            $table->string('cpf_token', 64)->nullable()->after('cpf')->index();

            $table->unique(['tenant_id', 'cpf_token']);
            $table->unique(['tenant_id', 'phone_token']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'cpf_token']);
            $table->dropUnique(['tenant_id', 'phone_token']);
            $table->dropIndex(['phone_token']);
            $table->dropIndex(['cpf_token']);
            $table->dropColumn(['phone', 'phone_token', 'cpf', 'cpf_token']);
        });
    }
};
