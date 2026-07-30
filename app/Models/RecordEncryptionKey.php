<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Armazena a DEK (Data Encryption Key) envelopada de cada registro sensível.
 *
 * Uma linha por registro (constraint unique keyable_type+keyable_id).
 * `revoked_at` + `encrypted_dek = null` representam o "crypto-shredding":
 * a chave foi destruída e os dados do registro relacionado tornam-se
 * permanentemente ilegíveis, mesmo que a linha física ainda exista no banco.
 */
class RecordEncryptionKey extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'keyable_type',
        'keyable_id',
        'encrypted_dek',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'revoked_at' => 'datetime',
        ];
    }

    public function keyable(): MorphTo
    {
        return $this->morphTo();
    }
}
