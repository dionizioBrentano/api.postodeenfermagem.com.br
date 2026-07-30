<?php

namespace App\Models;

use App\Casts\EncryptedWithDek;
use App\Traits\HasEncryptedFields;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Log de auditoria append-only. Cada linha tem sua própria DEK (via
 * HasEncryptedFields), então old_values/new_values ficam criptografados em
 * repouso mesmo que contenham dados sensíveis do paciente (ex.: CPF).
 *
 * "Append-only" é reforçado no nível da aplicação (save()/delete() abaixo).
 * Isso não substitui controles de permissão no nível do banco de dados
 * (grants/triggers), que ficam fora do escopo deste sprint.
 */
class AuditLog extends Model
{
    use HasUuids, HasEncryptedFields;

    const UPDATED_AT = null;

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

    protected function casts(): array
    {
        return [
            'old_values' => EncryptedWithDek::class,
            'new_values' => EncryptedWithDek::class,
        ];
    }

    public function save(array $options = [])
    {
        if ($this->exists) {
            throw new LogicException('Registros de auditoria são append-only e não podem ser alterados.');
        }

        return parent::save($options);
    }

    public function delete()
    {
        throw new LogicException('Registros de auditoria são append-only e não podem ser excluídos.');
    }
}
