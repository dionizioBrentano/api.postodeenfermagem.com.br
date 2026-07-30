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
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('action'); // created, updated, deleted, login, logout, accessed...
            $table->string('auditable_type')->nullable();
            $table->uuid('auditable_id')->nullable();

            // Criptografados por registro (cada log tem sua própria DEK).
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();

            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();

            // Append-only: sem updated_at, sem soft deletes.
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['auditable_type', 'auditable_id']);
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
