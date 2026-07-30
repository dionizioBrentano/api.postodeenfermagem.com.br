<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Casts\EncryptedWithDek;

class AuditLog extends Model
{
    use HasUuids;

    public const UPDATED_AT = null; // Append-only

    protected $fillable = [
        'tenant_id',
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        // Usa a DEK do registro auditado (auditable_id)
        'old_values' => EncryptedWithDek::class.':auditable_id',
        'new_values' => EncryptedWithDek::class.':auditable_id',
    ];

    public function auditable()
    {
        return $this->morphTo();
    }
}
