<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consent extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasTenant, Auditable;

    protected $fillable = [
        'tenant_id',
        'patient_id',
        'status',
        'purposes',
        'data_categories',
        'valid_until',
        'revoked_at',
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
    ];

    protected $casts = [
        'purposes' => 'array',
        'data_categories' => 'array',
        'valid_until' => 'datetime',
        'revoked_at' => 'datetime',
        'metadata' => 'array',
        'requires_dual_guardian' => 'boolean',
        'accepted_at' => 'datetime',
        'denied_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function revoke(): void
    {
        $this->update([
            'status' => 'revoked',
            'revoked_at' => now(),
        ]);
    }

    public function isValid(): bool
    {
        if ($this->status !== 'valid') {
            return false;
        }

        if ($this->valid_until && $this->valid_until->isPast()) {
            // Se passou da validade, podemos autodefinir como expirado no momento da checagem,
            // ou apenas retornar falso.
            return false;
        }

        return true;
    }
}
