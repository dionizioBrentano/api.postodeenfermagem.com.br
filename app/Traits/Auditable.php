<?php

namespace App\Traits;

use App\Services\AuditService;

trait Auditable
{
    public static function bootAuditable()
    {
        static::created(function ($model) {
            AuditService::log('created', $model, null, $model->getAttributes());
        });

        static::updated(function ($model) {
            AuditService::log('updated', $model, $model->getOriginal(), $model->getChanges());
        });

        static::deleted(function ($model) {
            AuditService::log('deleted', $model, $model->getAttributes(), null);
        });

        // Opcional: registrar leitura. 
        // Comente se o volume for muito alto para seu banco atual.
        static::retrieved(function ($model) {
            // Em registros sensiveis de prontuario, isso eh fundamental para LGPD
            AuditService::log('accessed', $model, null, null);
        });
    }
}
