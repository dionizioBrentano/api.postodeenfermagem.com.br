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
        Schema::create('care_authorizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignUuid('patient_id')->constrained('patients')->onDelete('cascade');
            
            $table->uuid('grantor_user_id');
            $table->uuid('grantee_user_id');
            
            $table->uuid('parent_authorization_id')->nullable();
            $table->uuid('source_consent_id')->nullable();
            
            $table->json('scope');
            $table->string('status')->default('active');
            
            $table->string('reason')->nullable();
            
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->uuid('revoked_by_user_id')->nullable();
            $table->string('revoke_reason')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['tenant_id', 'patient_id', 'grantee_user_id', 'status'], 'idx_care_auth_tenant_pat_grantee_status');
            $table->index(['grantee_user_id', 'status']);
            $table->index(['parent_authorization_id']);
            $table->index(['source_consent_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('care_authorizations');
    }
};
