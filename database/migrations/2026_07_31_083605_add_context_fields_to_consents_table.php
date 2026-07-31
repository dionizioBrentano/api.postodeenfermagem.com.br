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
        Schema::table('consents', function (Blueprint $table) {
            $table->string('context')->default('legacy');
            $table->string('consent_text_version')->nullable();
            $table->string('consent_text_hash')->nullable();
            $table->string('authenticated_with')->nullable();
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('denied_at')->nullable();
            $table->uuid('accepted_by_user_id')->nullable();
            $table->uuid('subject_user_id')->nullable();
            $table->uuid('professional_user_id')->nullable();
            $table->uuid('appointment_id')->nullable();
            $table->boolean('requires_dual_guardian')->default(false);
            $table->tinyInteger('guardian_slot')->nullable();
            $table->uuid('paired_consent_id')->nullable();
            $table->json('metadata')->nullable();
            
            $table->string('status')->default('valid')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consents', function (Blueprint $table) {
            $table->dropColumn([
                'context',
                'consent_text_version',
                'consent_text_hash',
                'authenticated_with',
                'accepted_at',
                'denied_at',
                'accepted_by_user_id',
                'subject_user_id',
                'professional_user_id',
                'appointment_id',
                'requires_dual_guardian',
                'guardian_slot',
                'paired_consent_id',
                'metadata',
            ]);
        });
    }
};
