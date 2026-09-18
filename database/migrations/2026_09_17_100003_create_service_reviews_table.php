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
        Schema::create('service_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignUuid('service_request_id')->unique()->constrained('service_requests')->onDelete('cascade');
            $table->foreignUuid('client_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('service_point_id')->nullable()->constrained('service_points')->nullOnDelete();

            $table->unsignedTinyInteger('stars'); // 1–5
            $table->text('body');
            $table->boolean('anonymous')->default(true);
            $table->boolean('publish_requested')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->foreignUuid('published_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'published_at']);
            $table->index(['tenant_id', 'service_point_id']);
            $table->index(['tenant_id', 'client_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_reviews');
    }
};
