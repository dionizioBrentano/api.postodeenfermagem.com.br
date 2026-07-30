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
            // council_number passa a guardar o payload criptografado (o
            // valor em texto puro nunca é persistido). council_number_token
            // guarda o blind index para permitir busca exata.
            $table->text('council_number')->nullable()->change();
            $table->string('council_number_token', 64)->nullable()->after('council_number');
            $table->index('council_number_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['council_number_token']);
            $table->dropColumn('council_number_token');
            $table->string('council_number')->nullable()->change();
        });
    }
};
