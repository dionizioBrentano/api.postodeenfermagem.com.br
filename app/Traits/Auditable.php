<?php

namespace App\Traits;

use App\Services\AuditService;

/**
 * Registra automaticamente eventos de criação, atualização e exclusão do
 * model no log de auditoria (App\Models\AuditLog via App\Services\AuditService).
 */
trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(function ($model) {
            AuditService::log('created', $model, null, $model->auditableAttributes());
        });

        static::updated(function ($model) {
            $changes = $model->getChanges();
            unset($changes['updated_at']);

            if (empty($changes)) {
                return;
            }

            $original = array_intersect_key($model->getOriginal(), $changes);

            AuditService::log('updated', $model, $original, $changes);
        });

        static::deleted(function ($model) {
            $action = method_exists($model, 'isForceDeleting') && $model->isForceDeleting()
                ? 'deleted_permanently'
                : 'deleted';

            AuditService::log($action, $model, $model->auditableAttributes(), null);
        });
    }

    /**
     * Atributos "seguros" para o log de auditoria: usa os valores já
     * descriptografados pelos casts (attributesToArray), removendo apenas
     * os campos ocultos (tokens de blind index, senha, etc). O log em si
     * é criptografado por registro (ver App\Models\AuditLog).
     */
    public function auditableAttributes(): array
    {
        return collect($this->attributesToArray())
            ->except($this->hidden)
            ->toArray();
    }
}
