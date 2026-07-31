<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareAuthorization extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasTenant, Auditable;

    protected $fillable = [
        'tenant_id',
        'patient_id',
        'grantor_user_id',
        'grantee_user_id',
        'parent_authorization_id',
        'source_consent_id',
        'scope',
        'status',
        'reason',
        'starts_at',
        'ends_at',
        'revoked_at',
        'revoked_by_user_id',
        'revoke_reason',
    ];

    protected $casts = [
        'scope' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function grantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'grantor_user_id');
    }

    public function grantee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'grantee_user_id');
    }

    public function parentAuthorization(): BelongsTo
    {
        return $this->belongsTo(CareAuthorization::class, 'parent_authorization_id');
    }

    public function sourceConsent(): BelongsTo
    {
        return $this->belongsTo(Consent::class, 'source_consent_id');
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }

    public function scopeActiveFor(User $user, Patient $patient, string $requiredScope = 'clinical:read'): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        if ($this->tenant_id !== $patient->tenant_id || $this->tenant_id !== $user->tenant_id) {
            return false;
        }

        if ($this->grantee_user_id !== $user->id) {
            return false;
        }

        if ($this->patient_id !== $patient->id) {
            return false;
        }

        if (is_array($this->scope) && !in_array($requiredScope, $this->scope)) {
            return false;
        }

        return true;
    }

    public function revoke(User $by, ?string $reason = null): void
    {
        $this->update([
            'status' => 'revoked',
            'revoked_at' => now(),
            'revoked_by_user_id' => $by->id,
            'revoke_reason' => $reason,
        ]);
        
        // Em um cenário real, propagar revogação para os filhos em cadeia também.
        // CareAuthorization::where('parent_authorization_id', $this->id)->where('status', 'active')->get()->each(function($child) use ($by, $reason) {
        //     $child->revoke($by, $reason);
        // });
    }
}
