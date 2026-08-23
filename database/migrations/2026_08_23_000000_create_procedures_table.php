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
        Schema::create('procedures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->onDelete('cascade');

            $table->string('title');
            $table->string('slug');
            $table->enum('category', [
                'aplicacao_medicamentos',
                'curativos_feridas',
                'eliminacoes',
                'vias_aereas',
                'sondas_alimentares',
                'outros',
            ])->default('outros');

            // Conteúdo editorial público — não é dado clínico de paciente,
            // portanto não passa pela criptografia por registro (DEK/KEK).
            $table->text('short_description')->nullable();
            $table->longText('content');

            $table->string('featured_image')->nullable();
            $table->json('gallery')->nullable();
            $table->integer('order')->default(0);
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');

            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Slug é único por tenant (dois tenants podem publicar o mesmo
            // procedimento com o mesmo slug sem colidir).
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status', 'category']);
            $table->index(['tenant_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procedures');
    }
};
