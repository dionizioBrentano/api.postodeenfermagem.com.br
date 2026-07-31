<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasTenant;
use App\Traits\HasEncryptedFields;
use App\Casts\EncryptedWithDek;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Condition extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasTenant, Auditable, HasEncryptedFields;

    protected $fillable = [
        'tenant_id',
        'patient_id',
        'encounter_id',
        'user_id',
        'code',
        'description',
        'status',
    ];

    protected $casts = [
        'description' => EncryptedWithDek::class,
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
