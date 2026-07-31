<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientUser extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasTenant, Auditable;

    protected $table = 'patient_user';

    protected $fillable = [
        'tenant_id',
        'patient_id',
        'user_id',
        'ativo',
        'data_inicio',
        'data_fim',
        'tipo_vinculo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'data_inicio' => 'datetime',
        'data_fim' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
