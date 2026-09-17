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
        Schema::create('service_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignUuid('client_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('offering_id')->nullable()->constrained('offerings')->nullOnDelete();
            $table->foreignUuid('service_point_id')->nullable()->constrained('service_points')->nullOnDelete();
            $table->foreignUuid('procedure_id')->constrained('procedures')->onDelete('cascade');

            $table->string('cep_servico', 20);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 11, 7)->nullable();
            $table->date('slot_date');
            $table->string('slot_window'); // manha|tarde|noite
            $table->string('status')->default('requested'); // requested|accepted|done|cancelled
            $table->text('notes_cliente')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'client_user_id']);
            $table->index(['tenant_id', 'service_point_id']);
            $table->index(['tenant_id', 'slot_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
