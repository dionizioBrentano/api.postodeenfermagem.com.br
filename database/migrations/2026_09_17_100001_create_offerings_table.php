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
        Schema::create('offerings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignUuid('service_point_id')->constrained('service_points')->onDelete('cascade');
            $table->foreignUuid('procedure_id')->constrained('procedures')->onDelete('cascade');

            $table->boolean('active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['service_point_id', 'procedure_id']);
            $table->index(['tenant_id', 'active']);
            $table->index(['tenant_id', 'procedure_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offerings');
    }
};
